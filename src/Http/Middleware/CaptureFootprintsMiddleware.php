<?php

namespace TNM\Footprints\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use TNM\Footprints\Jobs\ProcessFootprintJob;

final class CaptureFootprintsMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!config('footprints.enabled', true)) {
            return $next($request);
        }

        if (!defined('LARAVEL_START'))
            define('LARAVEL_START', microtime(true));

        $requestId = (string)Str::uuid();

        $request->headers->set('X-Request-ID', $requestId);

        $response = $next($request);

        try {
            $data = $this->collectData($request, $response, $requestId);

            ProcessFootprintJob::dispatch($data)
                ->onConnection(config('footprints.queue.connection'))
                ->onQueue(config('footprints.queue.name'));

        } catch (\Throwable $e) {
            Log::error("Footprints Logging Error: " . $e->getMessage());
        }

        return $response;
    }

    protected function collectData(Request $request, $response, $id): array
    {
        return [
            'request_id' => $id,
            'user_type' => $request->user() ? get_class($request->user()) : null,
            'user_id' => $request->user() ? $request->user()->getAuthIdentifier() : null,
            'method' => $request->method(),
            'uri' => $request->path(),
            'endpoint' => $request->fullUrl(),
            'ip_address' => $request->ip(),
            'duration_ms' => $this->getTurnAroundTime(),
            'success' => $response->isSuccessful(),
            'status_code' => $response->getStatusCode(),
            'requested_at' => date('Y-m-d H:i:s', (int)LARAVEL_START),
            'request_body' => $this->maskInputs($request->all()),
            'response_body' => $this->getResponseContent($response),
        ];
    }

    protected function maskInputs(array $data): array
    {
        $masks = config('footprints.mask_fields', []);
        foreach ($data as $key => $value) {
            if (in_array($key, $masks)) {
                $data[$key] = '<redacted>';
            } elseif (is_array($value)) {
                $data[$key] = $this->maskInputs($value);
            }
        }
        return $data;
    }

    protected function getResponseContent($response)
    {
        $contentType = $response->headers->get('Content-Type');
        if (Str::startsWith($contentType, ['text/', 'application/json', 'application/xml'])) {
            return $response->getContent();
        }
        return '(binary/stream content)';
    }

    private function getTurnAroundTime(): float|int
    {
        return round(round(microtime(true) - LARAVEL_START, 4) * 1000);
    }
}