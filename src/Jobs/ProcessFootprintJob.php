<?php

namespace TNM\Footprints\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use TNM\Footprints\Helpers\IdKeyGenerator;

class ProcessFootprintJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;


    public function __construct(public array $footprint)
    {
    }

    public function failed(\Throwable $exception): void
    {
        $this->fallback($exception->getMessage());
    }

    private function fallback(string $reason): void
    {
        Log::warning("[Footprint Package] Failed to log footprint: {$reason}");

        $path = storage_path('logs/footprints.log');
        $entry = json_encode(['error' => $reason, 'footprint' => $this->footprint]) . PHP_EOL;
        file_put_contents($path, $entry, FILE_APPEND);

    }

    public function handle(): void
    {
        $channels = config('footprints.channels', []);
        if (is_string($channels)) {
            $channels = explode(',', $channels);
        }
        $drivers = config('footprints.drivers');

        foreach ($channels as $channel) {
            try {
                switch ($channel) {
                    case 'database':
                        $this->logToDatabase($drivers['database']);
                        break;
                    case 'file':
                        $this->logToFile($drivers['file']);
                        break;
                    case 'kafka':
                        $this->logToKafka($drivers['kafka']);
                        break;
                    case 'elasticsearch':
                        $this->logToElasticsearch($drivers['elasticsearch']);
                        break;
                }
            } catch (\Throwable $e) {
                // If one channel fails, log error and continue to next channel
                // We do not want one failure to stop other persistence
                Log::error("Footprints: Failed to log to {$channel}: " . $e->getMessage());
            }
        }
    }

    protected function logToDatabase(array $config): void
    {
        DB::connection($config['connection'])
            ->table($config['table_name'])
            ->insert([
                'request_id' => $this->footprint['request_id'],
                'service_name' => $this->footprint['service_name'] ?? config('footprints.service_name', config('app.name', 'laravel-app')),
                'user_type' => $this->footprint['user_type'] ?? null,
                'user_id' => $this->footprint['user_id'] ?? null,
                'method' => $this->footprint['method'],
                'uri' => $this->footprint['uri'],
                'endpoint' => $this->footprint['endpoint'],
                'ip_address' => $this->footprint['ip_address'],
                'status_code' => $this->footprint['status_code'],
                'duration_ms' => $this->footprint['duration_ms'],
                'success' => $this->footprint['success'],
                'environment' => $this->footprint['environment'] ?? config('app.env', 'local'),
                'request_body' => $this->safeJsonEncode($this->footprint['request_body'] ?? null),
                'response_body' => $this->footprint['response_body'] ?? null,
                'request_headers' => $this->safeJsonEncode($this->footprint['request_headers'] ?? null),
                'requested_at' => $this->footprint['requested_at'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }

    protected function logToFile(array $config)
    {
        $logEntry = $this->safeJsonEncode($this->footprint) . PHP_EOL;

        File::append($config['path'], $logEntry);
    }

    protected function logToKafka(array $config)
    {
        if (!extension_loaded('rdkafka') || !class_exists(\RdKafka\Producer::class)) {
            throw new \Exception("RdKafka extension not installed or enabled.");
        }

        $conf = new \RdKafka\Conf();
        $conf->set('bootstrap.servers', $config['brokers']);
        $conf->set('socket.timeout.ms', (string)$config['timeout_ms']);

        $producer = new \RdKafka\Producer($conf);
        $topic = $producer->newTopic($config['topic']);


        $messageKey = null;
        if (isset($config['message_key'])) {
            try {
                $messageKey = IdKeyGenerator::generateMessageKey($this->footprint, $config['message_key']);
            } catch (\InvalidArgumentException $e) {
                throw new \Exception("Kafka message key generation failed: " . $e->getMessage());
            }
        }

        $messagePayload = $this->safeJsonEncode($this->footprint);
        $partition = RD_KAFKA_PARTITION_UA;
        $flags = 0;

        // Produce message with optional key (pass null if no key)
        $topic->produce($partition, $flags, $messagePayload, $messageKey);

        $producer->poll(0);
        $result = $producer->flush(1000);

        if ($result !== RD_KAFKA_RESP_ERR_NO_ERROR) {
            throw new \Exception("Kafka error code: {$result}");
        }
    }

    private function safeJsonEncode(mixed $value): string
    {
        $encoded = json_encode($value, JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        if ($encoded === false) {
            return json_encode(['error' => 'JSON encoding failed', 'message' => json_last_error_msg()]);
        }
        return $encoded;
    }

    protected function logToElasticsearch(array $config): void
    {
        if (!class_exists(\Elastic\Elasticsearch\ClientBuilder::class)) {
            throw new \Exception("Elasticsearch client not installed.");
        }

        $builder = \Elastic\Elasticsearch\ClientBuilder::create()
            ->setHosts($config['hosts']);


        if (!empty($config['api_key'])) {
            $builder->setApiKey($config['api_key']);
        } elseif (!empty($config['username']) && !empty($config['password'])) {
            $builder->setBasicAuthentication($config['username'], $config['password']);
        }

        $client = $builder->build();

        $operationType = $config['operation_type'] ?? 'index';
        if (!in_array($operationType, ['index', 'create'])) {
            throw new \Exception("Invalid Elasticsearch operation type: {$operationType}. Must be 'index' or 'create'");
        }

        $params = [
            'index' => $config['index'],
            'body' => $this->footprint
        ];

        if ($operationType === 'create') {
            $client->create($params);
        } else {
            $client->index($params);
        }
    }
}