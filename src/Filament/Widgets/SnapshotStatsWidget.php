<?php

namespace Spiggle\FilamentPortalSnapshot\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Spiggle\FilamentPortalSnapshot\Enums\OperationStatus;
use Spiggle\FilamentPortalSnapshot\FilamentPortalSnapshotPlugin;
use Spiggle\FilamentPortalSnapshot\Models\SnapshotOperation;
use Spiggle\FilamentPortalSnapshot\Models\SnapshotSchedule;
use Spiggle\FilamentPortalSnapshot\Services\SnapshotManager;

class SnapshotStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    public static function canView(): bool
    {
        try {
            return FilamentPortalSnapshotPlugin::get()->isAuthorized();
        } catch (\Throwable) {
            return false;
        }
    }

    protected function getStats(): array
    {
        $manager = app(SnapshotManager::class);
        $snapshots = $manager->all();
        $golden = $manager->goldenName() ?? 'Not set';

        $failed = SnapshotOperation::query()
            ->where('status', OperationStatus::Failed)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        $enabled = SnapshotSchedule::query()->enabled()->count();

        return [
            Stat::make('Snapshots', (string) $snapshots->count())
                ->description('On the snapshots disk')
                ->icon('heroicon-o-camera'),
            Stat::make('Golden baseline', $golden)
                ->description('Used by demo reset schedules')
                ->icon('heroicon-o-star'),
            Stat::make('Active schedules', (string) $enabled)
                ->description($failed ? "{$failed} failed in the last 24 hours" : 'No failures in the last 24 hours')
                ->icon('heroicon-o-clock'),
        ];
    }
}
