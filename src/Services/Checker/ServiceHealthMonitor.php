<?php

namespace TNM\Footprints\Services\Checker;

use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Registry and runner for service checks.
 */
class ServiceHealthMonitor
{
    /** @var ServiceCheck[] */
    protected array $checks = [];

    public function addCheck(ServiceCheck $check): void
    {
        if (!in_array($check, $this->checks)) {
            $this->checks[] = $check;
        }
    }

    /**
     * Runs all checks and returns True only if every check passes.
     */
    public function runAll(): bool
    {
        Log::info("Starting Service Reachability Checks...");
        $allPassed = true;

        foreach ($this->checks as $check) {
            try {
                if (!$check->check()) {
                    $allPassed = false;
                }
            } catch (Exception $e) {
                Log::error("[ERROR] Unexpected error running check '{$check->name()}': " . $e->getMessage());
                $allPassed = false;
            }
        }

        return $allPassed;
    }

    /**
     * Runs all checks sequentially and returns an associative array
     * mapping the service name to its success status (true/false).
     * * @return array<string, bool>
     */
    public function start(): array
    {
        Log::info("Starting Detailed Service Health Report...");
        $results = [];

        foreach ($this->checks as $check) {
            try {
                $success = $check->check();
                $results[$check->name()] = $success;
            } catch (Exception $e) {
                Log::error("[ERROR] Critical failure in check '{$check->name()}': " . $e->getMessage());
                $results[$check->name()] = false;
            }
        }

        $total = count($results);
        $passed = count(array_filter($results));

        Log::info("Health Check Complete: $passed/$total services healthy.");

        return $results;
    }
}