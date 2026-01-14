<?php

namespace TNM\Footprints\Services\Checker;

use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Checks connectivity via TCP socket.
 */
class TCPCheck extends ServiceCheck
{
    public function __construct(
        protected string $name,
        protected string $host,
        protected int    $port,
        protected int    $timeout = 3
    )
    {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function check(): bool
    {
        try {
            $connection = @fsockopen($this->host, $this->port, $errno, $errstr, $this->timeout);

            if (is_resource($connection)) {
                fclose($connection);
                Log::info("[OK] $this->name is reachable at $this->host:$this->port (TCP)");
                return true;
            }

            Log::error("[FAIL] $this->name is unreachable at $this->host:$this->port. Error: $errstr ($errno)");
            return false;

        } catch (Exception $e) {
            Log::error("[FAIL] $this->name check threw exception: " . $e->getMessage());
            return false;
        }
    }
}