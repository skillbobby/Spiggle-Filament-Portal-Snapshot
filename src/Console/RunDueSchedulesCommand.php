<?php

namespace Spiggle\FilamentPortalSnapshot\Console;

use Illuminate\Console\Command;
use Spiggle\FilamentPortalSnapshot\Services\ScheduleRunner;
use Throwable;

class RunDueSchedulesCommand extends Command
{
    protected $signature = 'portal-snapshot:run-schedules {--force-id= : Run a specific schedule immediately}';

    protected $description = 'Run due Portal Snapshot schedules (create, restore, cleanup).';

    public function handle(ScheduleRunner $runner): int
    {
        $forceId = $this->option('force-id');

        if ($forceId) {
            $schedule = \Spiggle\FilamentPortalSnapshot\Models\SnapshotSchedule::query()->find($forceId);

            if (! $schedule) {
                $this->error("Schedule [{$forceId}] was not found.");

                return self::FAILURE;
            }

            try {
                $runner->run($schedule);
                $this->info("Ran schedule [{$schedule->name}].");

                return self::SUCCESS;
            } catch (Throwable $exception) {
                $this->error($exception->getMessage());

                return self::FAILURE;
            }
        }

        try {
            $ran = $runner->runDue();
            $this->info("Ran {$ran} due snapshot schedule(s).");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
