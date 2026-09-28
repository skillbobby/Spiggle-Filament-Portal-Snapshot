<?php

use Spiggle\FilamentPortalSnapshot\Enums\ScheduleFrequency;

it('builds midnight cron', function () {
    expect(ScheduleFrequency::Midnight->toCron())->toBe('0 0 * * *');
});

it('builds daily cron from a clock time', function () {
    expect(ScheduleFrequency::Daily->toCron('14:30'))->toBe('30 14 * * *');
});

it('builds weekly cron', function () {
    expect(ScheduleFrequency::Weekly->toCron('09:15', 1))->toBe('15 9 * * 1');
});

it('builds hourly cron using the minute', function () {
    expect(ScheduleFrequency::Hourly->toCron('00:20'))->toBe('20 * * * *');
});

it('passes through a custom expression', function () {
    expect(ScheduleFrequency::Cron->toCron(expression: '5 4 * * 0'))->toBe('5 4 * * 0');
});

it('detects when a cron expression is due', function () {
    $now = new DateTimeImmutable('2026-09-28 00:00:00');

    expect(ScheduleFrequency::cronIsDue('0 0 * * *', $now))->toBeTrue()
        ->and(ScheduleFrequency::cronIsDue('30 14 * * *', $now))->toBeFalse();
});

it('supports step expressions', function () {
    $now = new DateTimeImmutable('2026-09-28 12:00:00');

    expect(ScheduleFrequency::cronIsDue('0 */6 * * *', $now))->toBeTrue()
        ->and(ScheduleFrequency::cronIsDue('0 */6 * * *', new DateTimeImmutable('2026-09-28 13:00:00')))->toBeFalse();
});

it('clamps invalid clock values', function () {
    expect(ScheduleFrequency::splitTime('25:99'))->toBe([23, 59])
        ->and(ScheduleFrequency::splitTime('bad'))->toBe([0, 0]);
});
