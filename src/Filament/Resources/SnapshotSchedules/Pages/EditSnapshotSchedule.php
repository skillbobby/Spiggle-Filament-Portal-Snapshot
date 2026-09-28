<?php

namespace Spiggle\FilamentPortalSnapshot\Filament\Resources\SnapshotSchedules\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Spiggle\FilamentPortalSnapshot\Filament\Resources\SnapshotSchedules\SnapshotScheduleResource;

class EditSnapshotSchedule extends EditRecord
{
    protected static string $resource = SnapshotScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
