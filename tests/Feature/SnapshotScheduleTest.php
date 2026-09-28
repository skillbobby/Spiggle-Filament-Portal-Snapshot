<?php

use Spiggle\FilamentPortalSnapshot\Enums\ScheduleAction;
use Spiggle\FilamentPortalSnapshot\Enums\ScheduleFrequency;
use Spiggle\FilamentPortalSnapshot\Models\SnapshotSchedule;

it('persists a midnight restore schedule', function () {
    $schedule = SnapshotSchedule::query()->create([
        'name' => 'Demo reset',
        'action' => ScheduleAction::Restore,
        'frequency' => ScheduleFrequency::Midnight,
        'use_golden' => true,
        'is_enabled' => true,
    ]);

    expect($schedule->resolvedCron())->toBe('0 0 * * *')
        ->and($schedule->isDue(new DateTimeImmutable('2026-09-28 00:00:00')))->toBeTrue()
        ->and($schedule->isDue(new DateTimeImmutable('2026-09-28 00:01:00')))->toBeFalse();
});

it('disabled schedules are never due', function () {
    $schedule = SnapshotSchedule::query()->create([
        'name' => 'Paused',
        'action' => ScheduleAction::Create,
        'frequency' => ScheduleFrequency::Midnight,
        'is_enabled' => false,
    ]);

    expect($schedule->isDue(new DateTimeImmutable('2026-09-28 00:00:00')))->toBeFalse();
});

it('uses a custom cron expression', function () {
    $schedule = SnapshotSchedule::query()->create([
        'name' => 'Custom',
        'action' => ScheduleAction::Cleanup,
        'frequency' => ScheduleFrequency::Cron,
        'cron_expression' => '15 3 * * *',
        'keep' => 7,
        'is_enabled' => true,
    ]);

    expect($schedule->resolvedCron())->toBe('15 3 * * *')
        ->and($schedule->isDue(new DateTimeImmutable('2026-09-28 03:15:00')))->toBeTrue();
});
