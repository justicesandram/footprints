<?php

namespace TNM\Footprints\Tests\Unit;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Queue;
use TNM\Footprints\Http\Middleware\CaptureFootprintsMiddleware;
use TNM\Footprints\Jobs\ProcessFootprintJob;
use TNM\Footprints\Tests\TestCase;

class CaptureFootprintsMiddlewareTest extends TestCase
{
    public function test_it_passes_request_and_generates_request_id()
    {
        $middleware = new CaptureFootprintsMiddleware();
        $request = Request::create('/test-url', 'GET');

        $response = $middleware->handle($request, function ($req) {
            return new Response('content');
        });

        $this->assertTrue($request->headers->has('X-Request-ID'));
        $this->assertEquals('content', $response->getContent());
    }

    public function test_it_dispatches_job_on_terminate()
    {
        Queue::fake();

        $middleware = new CaptureFootprintsMiddleware();
        $request = Request::create('/test-url', 'POST', ['foo' => 'bar']);
        $request->headers->set('X-Request-ID', 'test-uuid');
        
        // Define LARAVEL_START if not defined
        if (!defined('LARAVEL_START')) {
            define('LARAVEL_START', microtime(true));
        }

        $response = new Response('content', 200);

        $middleware->terminate($request, $response);

        Queue::assertPushed(ProcessFootprintJob::class, function ($job) {
            return $job->footprint['uri'] === 'test-url'
                && $job->footprint['method'] === 'POST'
                && $job->footprint['status_code'] === 200
                && $job->footprint['request_body']['foo'] === 'bar';
        });
    }

    public function test_it_masks_sensitive_inputs()
    {
        Queue::fake();

        $this->app['config']->set('footprints.mask_fields', ['password', 'credit_card']);

        $middleware = new CaptureFootprintsMiddleware();
        $request = Request::create('/login', 'POST', [
            'username' => 'johndoe',
            'password' => 'secret123',
            'nested' => [
                'credit_card' => '1234-5678-9012-3456',
                'other' => 'value'
            ]
        ]);
        $request->headers->set('X-Request-ID', 'test-uuid');

        if (!defined('LARAVEL_START')) {
            define('LARAVEL_START', microtime(true));
        }

        $response = new Response('ok');

        $middleware->terminate($request, $response);

        Queue::assertPushed(ProcessFootprintJob::class, function ($job) {
            $body = $job->footprint['request_body'];
            return $body['username'] === 'johndoe'
                && $body['password'] === '<redacted>'
                && $body['nested']['credit_card'] === '<redacted>'
                && $body['nested']['other'] === 'value';
        });
    }

    public function test_it_does_not_dispatch_job_when_disabled()
    {
        Queue::fake();
        $this->app['config']->set('footprints.enabled', false);

        $middleware = new CaptureFootprintsMiddleware();
        $request = Request::create('/test', 'GET');
        $response = new Response('ok');

        $middleware->terminate($request, $response);

        Queue::assertNotPushed(ProcessFootprintJob::class);
    }
    
    public function test_it_handles_file_uploads_gracefully()
    {
        Queue::fake();
        
        $middleware = new CaptureFootprintsMiddleware();
        
        $file = \Illuminate\Http\UploadedFile::fake()->create('document.pdf', 100);
        
        $request = Request::create('/upload', 'POST', [
            'name' => 'test upload',
            'file' => $file
        ]);
        $request->headers->set('X-Request-ID', 'test-uuid');

        if (!defined('LARAVEL_START')) {
            define('LARAVEL_START', microtime(true));
        }

        $response = new Response('ok');

        $middleware->terminate($request, $response);

        Queue::assertPushed(ProcessFootprintJob::class, function ($job) {
            $body = $job->footprint['request_body'];
            return $body['name'] === 'test upload'
                && $body['file'] === '[File: document.pdf]';
        });
    }
}