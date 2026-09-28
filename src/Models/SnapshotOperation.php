<?php

namespace Spiggle\FilamentPortalSnapshot\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spiggle\FilamentPortalSnapshot\Enums\OperationStatus;
use Spiggle\FilamentPortalSnapshot\Enums\OperationType;

class SnapshotOperation extends Model
{
    protected $table = 'portal_snapshot_operations';

    protected $fillable = [
        'type', 'status', 'snapshot_name', 'connection_name', 'disk',
        'schedule_id', 'user_id', 'message', 'meta', 'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => OperationType::class,
            'status' => OperationStatus::class,
            'meta' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(SnapshotSchedule::class, 'schedule_id');
    }

    public function markRunning(): void
    {
        $this->forceFill([
            'status' => OperationStatus::Running,
            'started_at' => now(),
        ])->save();
    }

    public function markSucceeded(?string $message = null, array $meta = []): void
    {
        $this->forceFill([
            'status' => OperationStatus::Succeeded,
            'message' => $message,
            'meta' => array_merge($this->meta ?? [], $meta),
            'finished_at' => now(),
        ])->save();
    }

    public function markFailed(string $message, array $meta = []): void
    {
        $this->forceFill([
            'status' => OperationStatus::Failed,
            'message' => $message,
            'meta' => array_merge($this->meta ?? [], $meta),
            'finished_at' => now(),
        ])->save();
    }
}
