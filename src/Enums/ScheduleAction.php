<?php

namespace Spiggle\FilamentPortalSnapshot\Enums;

enum ScheduleAction: string
{
    case Create = 'create';
    case Restore = 'restore';
    case Cleanup = 'cleanup';

    public function label(): string
    {
        return match ($this) {
            self::Create => 'Create snapshot',
            self::Restore => 'Restore snapshot',
            self::Cleanup => 'Cleanup old snapshots',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Create => 'Dump the database on a cadence (hourly, nightly, weekly).',
            self::Restore => 'Load a named or golden snapshot — ideal for demo-site resets at midnight.',
            self::Cleanup => 'Keep only the most recent N snapshots on the snapshots disk.',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Create => 'heroicon-o-plus-circle',
            self::Restore => 'heroicon-o-arrow-path',
            self::Cleanup => 'heroicon-o-trash',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Create => 'primary',
            self::Restore => 'warning',
            self::Cleanup => 'gray',
        };
    }
}
