<?php

namespace TNM\Footprints\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;
use TNM\Footprints\Channels\ChannelInterface;
use TNM\Footprints\Channels\DatabaseChannel;
use TNM\Footprints\Channels\ElasticsearchChannel;
use TNM\Footprints\Channels\FileChannel;
use TNM\Footprints\Channels\KafkaChannel;

class ProcessFootprintJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public array $footprint)
    {
    }

    public function failed(Throwable $exception): void
    {
        $this->fallback($exception->getMessage());
    }

    private function fallback(string $reason): void
    {
        Log::warning("[Footprint Package] Failed to log footprint: $reason");

        $fileConfig = config('footprints.drivers.file', []);
        $path = $fileConfig['path'] ?? storage_path('logs/footprints.log');
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

        foreach ($channels as $channelName) {
            try {
                $channelInstance = $this->resolveChannel($channelName);
                if ($channelInstance) {
                    $channelConfig = $drivers[$channelName] ?? [];
                    $channelInstance->log($this->footprint, $channelConfig);
                }
            } catch (Throwable $e) {
                // If one channel fails, log error and continue to next channel
                // We do not want one failure to stop other persistence
                Log::error("Footprints: Failed to log to $channelName: " . $e->getMessage());
            }
        }
    }

    /**
     * @throws Exception
     */
    protected function resolveChannel(string $channel): ?ChannelInterface
    {
        return match (strtolower($channel)) {
            'database' => new DatabaseChannel(),
            'file' => new FileChannel(),
            'kafka' => new KafkaChannel(),
            'elasticsearch' => new ElasticsearchChannel(),
            default => throw new Exception("Unknown channel: $channel"),
        };
    }
}