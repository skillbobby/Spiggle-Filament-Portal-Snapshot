<?php

namespace Spiggle\FilamentPortalSnapshot\Models;

use Illuminate\Database\Eloquent\Model;

class SnapshotSetting extends Model
{
    protected $table = 'portal_snapshot_settings';

    protected $fillable = [
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }
}
