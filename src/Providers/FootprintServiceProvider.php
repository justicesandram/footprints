<?php

namespace TNM\Footprints\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Routing\Router;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Support\Facades\Event;
use TNM\Footprints\Http\Middleware\CaptureFootprintsMiddleware;

class FootprintServiceProvider extends ServiceProvider
{
    public function boot(Router $router): void
    {
        $this->publishes([
            __DIR__ . '/../config/footprints.php' => config_path('footprints.php'),
        ], 'footprints-config');

        $this->publishes([
            __DIR__ . '/../../database/migrations/create_footprints_table.php' => database_path('migrations/create_footprints_table.php'),
        ], 'footprints-migrations');

        if ($this->app->runningInConsole()) {
            Event::listen(CommandFinished::class, function (CommandFinished $event) {
                if ($event->command === 'vendor:publish') {
                    $tags = $event->input->getOption('tag') ?: [];
                    if (empty($tags) || in_array('footprints-migrations', $tags)) {
                        $migrationsPath = database_path('migrations');
                        $oldFile = $migrationsPath . '/create_footprints_table.php';
                        
                        if (file_exists($oldFile)) {
                            $timestamp = date('Y_m_d_His');
                            $newFile = $migrationsPath . '/' . $timestamp . '_create_footprints_table.php';
                            rename($oldFile, $newFile);
                        }
                    }
                }
            });
        }

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
