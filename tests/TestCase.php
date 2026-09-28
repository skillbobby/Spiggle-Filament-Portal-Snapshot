<?php

namespace Spiggle\FilamentPortalSnapshot\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\DbSnapshots\DbSnapshotsServiceProvider;
use Spiggle\FilamentPortalSnapshot\FilamentPortalSnapshotServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            DbSnapshotsServiceProvider::class,
            FilamentPortalSnapshotServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('mail.default', 'array');
        $app['config']->set('filesystems.disks.snapshots', [
            'driver' => 'local',
            'root' => storage_path('framework/testing/snapshots'),
        ]);
        $app['config']->set('db-snapshots.disk', 'snapshots');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
