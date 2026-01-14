<?php

namespace TNM\Footprints\Channels;

use Illuminate\Support\Facades\DB;

class DatabaseChannel extends BaseChannel
{
    public function log(array $footprint, array $config): void
    {
        DB::connection($config['connection'])
            ->table($config['table_name'])
            ->insert([
                'request_id' => $footprint['request_id'],
                'service_name' => $footprint['service_name'] ?? config('footprints.service_name', config('app.name', 'laravel-app')),
                'user_type' => $footprint['user_type'] ?? null,
                'user_id' => $footprint['user_id'] ?? null,
                'method' => $footprint['method'],
                'uri' => $footprint['uri'],
                'endpoint' => $footprint['endpoint'],
                'ip_address' => $footprint['ip_address'],
                'status_code' => $footprint['status_code'],
                'duration_ms' => $footprint['duration_ms'],
                'success' => $footprint['success'],
                'environment' => $footprint['environment'] ?? config('app.env', 'local'),
                'request_body' => $this->safeJsonEncode($footprint['request_body'] ?? null),
                'response_body' => $footprint['response_body'] ?? null,
                'request_headers' => $this->safeJsonEncode($footprint['request_headers'] ?? null),
                'requested_at' => $footprint['requested_at'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }
}