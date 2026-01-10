<?php

namespace TNM\Footprints\Tests\Unit;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Queue;
use TNM\Footprints\Http\Middleware\CaptureFootprintsMiddleware;
use TNM\Footprints\Jobs\ProcessFootprintJob;
use TNM\Footprints\Tests\TestCase;

class RedactHeadersTest extends TestCase
{
    public function test_it_masks_sensitive_headers()
    {
        Queue::fake();

        $this->app['config']->set('footprints.mask_fields', ['api_key', 'authorization']);

        $middleware = new CaptureFootprintsMiddleware();
        $request = Request::create('/test-url', 'GET');
        $request->headers->set('X-Request-ID', 'test-uuid');
        $request->headers->set('api_key', 'secret-api-key');
        $request->headers->set('Authorization', 'Bearer secret-token');
        $request->headers->set('X-Custom-Header', 'public-value');

        if (!defined('LARAVEL_START')) {
            define('LARAVEL_START', microtime(true));
        }

        $response = new Response('ok');

        $middleware->terminate($request, $response);

        Queue::assertPushed(ProcessFootprintJob::class, function ($job) {
            $headers = $job->footprint['request_headers'];
            
            $apiKeyRedacted = false;
            $authRedacted = false;
            
            foreach($headers as $key => $value) {
                if ($key === 'api_key' || $key === 'api-key') {
                   if ($value === '<redacted>') $apiKeyRedacted = true;
                }
                if ($key === 'authorization') {
                   if ($value === '<redacted>') $authRedacted = true;
                }
            }
            
            if (!$apiKeyRedacted || !$authRedacted) {
                echo "\nActual headers in job: " . json_encode($headers) . "\n";
            }

            return $apiKeyRedacted && $authRedacted;
        });
    }
}
