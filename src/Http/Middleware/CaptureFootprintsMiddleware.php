<?php

namespace TNM\Footprints\Http\Middleware;

use Closure;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Throwable;
use function array_map;
use function defined;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use TNM\Footprints\Jobs\ProcessFootprintJob;
use function is_array;
use function is_string;
use function str_replace;
use function strtolower;
use function trim;

final class CaptureFootprintsMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!defined('LARAVEL_START')) {
            define('LARAVEL_START', microtime(true));
        }

        if (!$request->headers->has('X-Request-ID')) {
            $request->headers->set('X-Request-ID', (string)Str::uuid());
        }

        return $next($request);
    }

    public function terminate(Request $request, $response): void
    {
        if (!config('footprints.enabled', true)) {
            return;
        }

        try {
            $requestId = $request->headers->get('X-Request-ID');
            $data = $this->collectData($request, $response, $requestId);

            ProcessFootprintJob::dispatch($data)
                ->onConnection(config('footprints.queue.connection'))
                ->onQueue(config('footprints.queue.name'));

        } catch (Throwable $e) {
            Log::error("Footprints Logging Error: " . $e->getMessage());
        }
    }

    protected function collectData(Request $request, $response, $id): array
    {
        return [
            'request_id' => $id,
            'service_name' => config('footprints.service_name', config('app.name', 'unnamed-laravel-app')),
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
            'request_body' => $this->maskInputs($this->cleanInputs($request->all())),
            'response_body' => $this->getResponseContent($response),
            "request_headers" => $this->maskInputs($request->headers->all()),
            "environment" => config('app.env'),
        ];
    }

    protected function cleanInputs(mixed $data): mixed
    {
        if (!is_array($data)) {
            return $data;
        }

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->cleanInputs($value);
            } elseif ($value instanceof UploadedFile) {
                $data[$key] = "[File: " . $value->getClientOriginalName() . "]";
            }
        }
        return $data;
    }

    protected function maskInputs(mixed $data): mixed
    {
        if (!is_array($data)) {
            return $data;
        }

        $masks = config('footprints.mask_fields', []);

        if (is_string($masks)) {
            $masks = explode(',', $masks);
        }

        $masks = array_map(fn($m) => strtolower(trim($m)), $masks);

        foreach ($data as $key => $value) {
            $normalizedKey = strtolower(str_replace('-', '_', $key));

            if (in_array($normalizedKey, $masks)) {
                $data[$key] = '<redacted>';
            } elseif (is_array($value)) {
                $data[$key] = $this->maskInputs($value);
            }
        }
        return $data;
    }

    protected function getResponseContent($response)
    {
        if (!$response) {
            return null;
        }

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