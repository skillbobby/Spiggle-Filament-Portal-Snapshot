<?php

namespace Spiggle\FilamentPortalSnapshot\Enums;

enum OperationStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Running => 'info',
            self::Succeeded => 'success',
            self::Failed => 'danger',
        };
    }
}
