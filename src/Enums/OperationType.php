<?php

namespace Spiggle\FilamentPortalSnapshot\Enums;

enum OperationType: string
{
    case Create = 'create';
    case Restore = 'restore';
    case Delete = 'delete';
    case Cleanup = 'cleanup';
    case Export = 'export';
    case RemoteCopy = 'remote_copy';

    public function label(): string
    {
        return match ($this) {
            self::Create => 'Create',
            self::Restore => 'Restore',
            self::Delete => 'Delete',
            self::Cleanup => 'Cleanup',
            self::Export => 'Export',
            self::RemoteCopy => 'Remote copy',
        };
    }
}
