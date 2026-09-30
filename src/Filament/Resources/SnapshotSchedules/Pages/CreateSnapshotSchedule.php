<?php

namespace Spiggle\FilamentPortalSnapshot\Filament\Resources\SnapshotSchedules\Pages;

use Filament\Resources\Pages\CreateRecord;
use Spiggle\FilamentPortalSnapshot\Filament\Resources\SnapshotSchedules\SnapshotScheduleResource;

class CreateSnapshotSchedule extends CreateRecord
{
    protected static string $resource = SnapshotScheduleResource::class;

    public static function canAccess(array $parameters = []): bool
    {
        return SnapshotScheduleResource::canViewAny();
    }
}
