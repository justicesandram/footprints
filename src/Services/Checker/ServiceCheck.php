<?php

namespace TNM\Footprints\Services\Checker;

/**
 * Base abstract class for service checks.
 */
abstract class ServiceCheck
{
    /**
     * The service name of the service we are checking.
     */
    abstract public function name(): string;

    /**
     * Returns true if the check passes, false otherwise.
     */
    abstract public function check(): bool;
}


