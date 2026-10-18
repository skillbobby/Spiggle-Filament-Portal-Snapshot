<?php

namespace Spiggle\FilamentPortalSnapshot;

use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Console\Scheduling\Schedule;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Spiggle\FilamentPortalSnapshot\Console\RunDueSchedulesCommand;
use Spiggle\FilamentPortalSnapshot\Services\NotificationDispatcher;
use Spiggle\FilamentPortalSnapshot\Services\RemoteStorageService;
use Spiggle\FilamentPortalSnapshot\Services\ScheduleRunner;
use Spiggle\FilamentPortalSnapshot\Services\SnapshotManager;
use Spiggle\FilamentPortalSnapshot\Services\SnapshotSettings;

class FilamentPortalSnapshotServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-portal-snapshot';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(static::$name)
            ->hasConfigFile()
            ->hasViews()
            ->hasTranslations()
            ->hasMigrations([
                'create_portal_snapshot_schedules_table',
                'create_portal_snapshot_operations_table',
                'create_portal_snapshot_settings_table',
            ])
            ->hasCommands([
                RunDueSchedulesCommand::class,
            ])
            ->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->publishConfigFile()
                    ->publishMigrations()
                    ->askToRunMigrations()
                    ->endWith(function (InstallCommand $command) {
                        $command->info('Next steps:');
                        $command->line('  1. Add a `snapshots` disk to config/filesystems.php (see the README).');
                        $command->line('  2. Register FilamentPortalSnapshotPlugin::make() on your panel.');
                        $command->line('  3. Open Snapshot settings in the panel to choose the storage directory.');
                        $command->line('  4. Ensure the Laravel scheduler is running so snapshot schedules fire.');
                    });
            });
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(NotificationDispatcher::class);
        $this->app->singleton(RemoteStorageService::class);
        $this->app->singleton(SnapshotManager::class);
        $this->app->singleton(SnapshotSettings::class);
        $this->app->singleton(ScheduleRunner::class);
    }

    public function packageBooted(): void
    {
        FilamentAsset::register([
            Css::make('portal-snapshot', __DIR__.'/../resources/css/portal-snapshot.css'),
        ], 'spiggle/filament-portal-snapshot');

        $this->app->make(SnapshotSettings::class)->apply();

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule
                ->command('portal-snapshot:run-schedules')
                ->everyMinute()
                ->name('spiggle-portal-snapshot-schedules')
                ->withoutOverlapping();
        });
    }
}
