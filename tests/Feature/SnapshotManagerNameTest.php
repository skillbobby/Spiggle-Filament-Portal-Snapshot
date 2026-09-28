<?php

use Spiggle\FilamentPortalSnapshot\Services\NotificationDispatcher;
use Spiggle\FilamentPortalSnapshot\Services\RemoteStorageService;
use Spiggle\FilamentPortalSnapshot\Services\SnapshotManager;

it('sanitizes snapshot names', function () {
    $manager = $this->app->make(SnapshotManager::class);

    expect($manager->sanitizeName('Demo Baseline 01'))->toBe('Demo-Baseline-01')
        ->and($manager->sanitizeName('ok_name-2'))->toBe('ok_name-2');
});

it('resolves configured remote disks', function () {
    config()->set('filament-portal-snapshot.remote.enabled', true);
    config()->set('filament-portal-snapshot.remote.disks', ['s3', 'backups']);

    $remote = $this->app->make(RemoteStorageService::class);

    expect($remote->isEnabled())->toBeTrue()
        ->and($remote->disks())->toBe(['s3', 'backups']);
});

it('can construct the notification dispatcher', function () {
    expect($this->app->make(NotificationDispatcher::class))->toBeInstanceOf(NotificationDispatcher::class);
});
