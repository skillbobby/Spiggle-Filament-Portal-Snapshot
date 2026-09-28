<?php

namespace Spiggle\FilamentPortalSnapshot\Services;

use Spiggle\FilamentPortalSnapshot\Enums\ScheduleAction;
use Spiggle\FilamentPortalSnapshot\Models\SnapshotSchedule;
use Throwable;

class ScheduleRunner
{
    public function __construct(protected SnapshotManager $manager) {}

    public function runDue(?\DateTimeInterface $now = null): int
    {
        $ran = 0;

        SnapshotSchedule::query()->enabled()->get()
            ->filter(fn (SnapshotSchedule $schedule) => $schedule->isDue($now))
            ->each(function (SnapshotSchedule $schedule) use (&$ran) {
                $this->run($schedule);
                $ran++;
            });

        return $ran;
    }

    public function run(SnapshotSchedule $schedule): void
    {
        try {
            match ($schedule->action) {
                ScheduleAction::Create => $this->create($schedule),
                ScheduleAction::Restore => $this->restore($schedule),
                ScheduleAction::Cleanup => $this->cleanup($schedule),
            };
            $schedule->markResult('succeeded', 'Completed.');
        } catch (Throwable $exception) {
            $schedule->markResult('failed', $exception->getMessage());
            throw $exception;
        }
    }

    protected function create(SnapshotSchedule $schedule): void
    {
        $this->manager->create(
            name: $this->datedName($schedule),
            connection: $schedule->connection_name,
            compress: (bool) $schedule->compress,
            copyToRemote: (bool) $schedule->copy_to_remote,
            remoteDisk: $schedule->remote_disk,
            scheduleId: $schedule->id,
        );
    }

    protected function restore(SnapshotSchedule $schedule): void
    {
        $target = $this->manager->resolveRestoreTarget(
            name: $schedule->snapshot_name,
            useGolden: (bool) $schedule->use_golden,
        );

        $this->manager->restore(
            name: $target,
            connection: $schedule->connection_name,
            actor: null,
            scheduleId: $schedule->id,
        );
    }

    protected function cleanup(SnapshotSchedule $schedule): void
    {
        $keep = $schedule->keep ?: (int) config('filament-portal-snapshot.keep', 14);
        $this->manager->cleanup($keep, scheduleId: $schedule->id);
    }

    protected function datedName(SnapshotSchedule $schedule): string
    {
        $base = $this->manager->sanitizeName($schedule->snapshot_name ?: $schedule->name);

        return $base.'-'.now()->format('Ymd-His');
    }
}
