<?php

namespace TNM\Footprints\Tests\Unit;

use TNM\Footprints\Tests\TestCase;
use TNM\Footprints\Http\Middleware\CaptureFootprintsMiddleware;

class FootprintServiceProviderTest extends TestCase
{
    public function test_config_is_published()
    {
        $this->assertArrayHasKey('footprints', $this->app['config']);
        $this->assertTrue($this->app['config']->get('footprints.enabled'));
    }

    public function test_middleware_is_aliased()
    {
        $router = $this->app['router'];
        $middleware = $router->getMiddleware();
        
        $this->assertArrayHasKey('footprints', $middleware);
        $this->assertEquals(CaptureFootprintsMiddleware::class, $middleware['footprints']);
    }
}