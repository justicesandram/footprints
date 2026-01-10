<?php

namespace TNM\Footprints\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Routing\Router;
use TNM\Footprints\Http\Middleware\CaptureFootprintsMiddleware;

class FootprintServiceProvider extends ServiceProvider
{
    public function boot(Router $router): void
    {
        $this->publishes([
            __DIR__ . '/../config/footprints.php' => config_path('footprints.php'),
        ], 'footprints-config');

        $this->publishes([
            __DIR__ . '/../../database/migrations' => database_path('migrations'),
        ], 'footprints-migrations');

        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        $router->aliasMiddleware('footprints', CaptureFootprintsMiddleware::class);
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/footprints.php', 'footprints'
        );
    }
}
