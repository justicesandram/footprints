<?php

namespace TNM\Footprints\Services\Checker;


use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Checks connectivity via HTTP request using Laravel's Http Client.
 */
class HTTPCheck extends ServiceCheck
{
    public function __construct(
        protected string $name,
        protected string $url,
        protected int    $timeout = 3
    )
    {
    }

    public function name(): string
    {
        return $this->name;
    }

    protected function performRequest(string $url, string $checkName, bool $logError = true): bool
    {
        try {
            $response = Http::timeout($this->timeout)->get($url);

            if ($response->successful()) {
                Log::info("[OK] $checkName responded with HTTP {$response->status()} at $url");
                return true;
            } else {
                if ($logError) {
                    Log::error("[FAIL] $checkName HTTP check returned status {$response->status()} at $url");
                }
                return false;
            }
        } catch (ConnectionException $e) {
            if ($logError) {
                Log::error("[FAIL] $checkName HTTP check connection failed at $url. Error: " . $e->getMessage());
            }
            return false;
        } catch (Exception $e) {
            if ($logError) {
                Log::error("[FAIL] $checkName HTTP check failed at $url. Error: " . $e->getMessage());
            }
            return false;
        }
    }

    public function check(): bool
    {
        return $this->performRequest($this->url, $this->name);
    }
}