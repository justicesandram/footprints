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
        Log::warning("[Footprint Package] Failed to log footptrint: {$reason}");

        $path = storage_path('logs/footprints.log');
        $entry = json_encode(['error' => $reason, 'footprint' => $this->footprint]) . PHP_EOL;
        file_put_contents($path, $entry, FILE_APPEND);

    }

    public function handle(): void
    {
        $channels = config('footprints.channels', []);
        $drivers = config('footprints.drivers');

        foreach ($channels as $channel) {
            try {
                switch ($channel) {
                    case 'footprintbase':
                        $this->logToDatabase($drivers['footprintbase']);
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
                'user_type' => $this->footprint['user_type'],
                'user_id' => $this->footprint['user_id'],
                'method' => $this->footprint['method'],
                'uri' => $this->footprint['uri'],
                'endpoint' => $this->footprint['endpoint'],
                'ip_address' => $this->footprint['ip_address'],
                'status_code' => $this->footprint['status_code'],
                'duration_ms' => $this->footprint['milliseconds'],
                'success' => $this->footprint['success'],
                'request_body' => json_encode($this->footprint['request_body']),
                'response_body' => $this->footprint['response_body'],
                'request_headers' => json_encode($this->footprint['response_body']),
                'requested_at' => $this->footprint['requested_at'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }

    protected function logToFile(array $config)
    {
        $logEntry = json_encode($this->footprint) . PHP_EOL;

        File::append($config['path'], $logEntry);
    }

    protected function logToKafka(array $config)
    {
        if (!class_exists(\RdKafka\Producer::class)) {
            throw new \Exception("RdKafka extension not installed or enabled.");
        }

        $conf = new Conf();
        $conf->set('metafootprint.broker.list', $config['brokers']);
        $conf->set('socket.timeout.ms', (string)$config['timeout_ms']);

        $producer = new Producer($conf);
        $topic = $producer->newTopic($config['topic']);

        $topic->produce(
            RD_KAFKA_PARTITION_UA,
            0,
            json_encode($this->footprint, JSON_THROW_ON_ERROR)
        );

        $producer->poll(0);
        $result = $producer->flush(1000);

        if ($result !== RD_KAFKA_RESP_ERR_NO_ERROR) {
            throw new \Exception("Kafka error code: {$result}");
        }
    }

    protected function logToElasticsearch(array $config): void
    {
        if (!class_exists(\Elastic\Elasticsearch\ClientBuilder::class)) {
            throw new \Exception("Elasticsearch client not installed.");
        }

        $client = \Elastic\Elasticsearch\ClientBuilder::create()
            ->setHosts($config['hosts'])
            ->build();

        $params = [
            'index' => $config['index'],
            'body' => $this->footprint
        ];

        $client->index($params);
    }
}