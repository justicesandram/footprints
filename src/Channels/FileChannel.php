<?php

namespace TNM\Footprints\Channels;

use Illuminate\Support\Facades\File;

class FileChannel extends BaseChannel
{
    public function log(array $footprint, array $config): void
    {
        $path = $config['path'] ?? storage_path('logs/footprints.log');
        $logEntry = $this->safeJsonEncode($footprint) . PHP_EOL;

        File::append($path, $logEntry);
    }
}
