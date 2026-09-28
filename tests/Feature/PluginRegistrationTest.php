<?php

use Spiggle\FilamentPortalSnapshot\FilamentPortalSnapshotPlugin;

it('exposes a stable plugin id', function () {
    expect(FilamentPortalSnapshotPlugin::make()->getId())
        ->toBe('spiggle-filament-portal-snapshot');
});

it('uses config-backed navigation defaults', function () {
    $plugin = FilamentPortalSnapshotPlugin::make()
        ->navigationLabel('DB snapshots')
        ->navigationSort(12);

    expect($plugin->getNavigationLabel())->toBe('DB snapshots')
        ->and($plugin->getNavigationSort())->toBe(12)
        ->and($plugin->isAuthorized())->toBeTrue();
});

it('honours an authorize callback', function () {
    $plugin = FilamentPortalSnapshotPlugin::make()->authorize(fn () => false);

    expect($plugin->isAuthorized())->toBeFalse();
});
