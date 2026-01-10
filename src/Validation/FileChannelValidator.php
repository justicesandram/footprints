<?php

namespace TNM\Footprints\Validation;

use Illuminate\Support\Facades\Log;

class FileChannelValidator
{
    /**
     * Minimum free disk space required in bytes (default: 100MB)
     */
    protected int $minFreeSpaceBytes;

    public function __construct(int $minFreeSpaceBytes = 104857600)
    {
        $this->minFreeSpaceBytes = $minFreeSpaceBytes;
    }

    /**
     * Validate file channel configuration
     *
     * @param string $filePath
     * @return array{valid: bool, errors: array}
     */
    public function validate(string $filePath): array
    {
        $errors = [];

        $directory = dirname($filePath);

        if (!is_dir($directory)) {

            $parentDir = $directory;
            while (!is_dir($parentDir) && $parentDir !== dirname($parentDir)) {
                $parentDir = dirname($parentDir);
            }
            
            if (!is_dir($parentDir) || !is_writable($parentDir)) {
                $errors[] = "Directory does not exist and cannot be created (parent not writable): $directory";
            } else {

                if (!@mkdir($directory, 0755, true)) {
                    $errors[] = "Failed to create directory: $directory";
                }
            }
        }

        if (is_dir($directory) && !is_writable($directory)) {
            $errors[] = "Directory is not writable: $directory";
        }

        if (file_exists($filePath)) {
            if (!is_writable($filePath)) {
                $errors[] = "Log file exists but is not writable: $filePath";
            }
        } else {
            if (!is_writable($directory)) {
                $errors[] = "Cannot create log file: parent directory is not writable: $directory";
            }
        }

        $freeSpace = $this->getFreeDiskSpace($directory);
        if ($freeSpace !== false && $freeSpace < $this->minFreeSpaceBytes) {
            $freeSpaceMB = round($freeSpace / 1048576, 2);
            $requiredMB = round($this->minFreeSpaceBytes / 1048576, 2);
            $errors[] = "Insufficient disk space: {$freeSpaceMB}MB available, {$requiredMB}MB required";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Get free disk space for the given path
     *
     * @param string $path
     * @return int|false Bytes of free space, or false if unable to determine
     */
    protected function getFreeDiskSpace(string $path): int|false
    {
        $realPath = realpath($path) ?: $path;

        if (PHP_OS_FAMILY === 'Windows') {
            $drive = substr($realPath, 0, 2);
            $freeSpace = @disk_free_space($drive);
        } else {
            $freeSpace = @disk_free_space($realPath);
        }

        return $freeSpace;
    }

    /**
     * Log validation errors
     *
     * @param array $errors
     * @return void
     */
    public function logErrors(array $errors): void
    {
        foreach ($errors as $error) {
            Log::warning("[Footprints] File channel validation failed: $error");
        }
    }
}
