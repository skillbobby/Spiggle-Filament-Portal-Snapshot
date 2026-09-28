<?php

namespace Spiggle\FilamentPortalSnapshot\Filament\Resources\SnapshotSchedules\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Spiggle\FilamentPortalSnapshot\Filament\Resources\SnapshotSchedules\SnapshotScheduleResource;

class ListSnapshotSchedules extends ListRecords
{
    protected static string $resource = SnapshotScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New schedule'),
        ];
    }
}
