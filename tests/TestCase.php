<?php

namespace TNM\Footprints\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use TNM\Footprints\Providers\FootprintServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            FootprintServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        // Default footprint config for tests
        $app['config']->set('footprints.enabled', true);
        $app['config']->set('footprints.channels', ['database']);
        $app['config']->set('footprints.drivers.database', [
            'connection' => 'sqlite',
            'table_name' => 'footprints',
        ]);
        $app['config']->set('footprints.queue', [
            'connection' => 'sync',
            'queue' => 'default',
        ]);
    }

    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}