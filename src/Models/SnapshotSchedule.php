<?php

namespace Spiggle\FilamentPortalSnapshot\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spiggle\FilamentPortalSnapshot\Enums\ScheduleAction;
use Spiggle\FilamentPortalSnapshot\Enums\ScheduleFrequency;

class SnapshotSchedule extends Model
{
    protected $table = 'portal_snapshot_schedules';

    protected $fillable = [
        'name', 'action', 'frequency', 'cron_expression', 'run_at', 'weekday',
        'snapshot_name', 'connection_name', 'compress', 'use_golden', 'keep',
        'copy_to_remote', 'remote_disk', 'is_enabled', 'notes',
        'last_ran_at', 'last_status', 'last_message',
    ];

    protected function casts(): array
    {
        return [
            'action' => ScheduleAction::class,
            'frequency' => ScheduleFrequency::class,
            'compress' => 'boolean',
            'use_golden' => 'boolean',
            'copy_to_remote' => 'boolean',
            'is_enabled' => 'boolean',
            'keep' => 'integer',
            'weekday' => 'integer',
            'last_ran_at' => 'datetime',
        ];
    }

    public function operations(): HasMany
    {
        return $this->hasMany(SnapshotOperation::class, 'schedule_id');
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }

    public function resolvedCron(): string
    {
        $frequency = $this->frequency instanceof ScheduleFrequency
            ? $this->frequency
            : ScheduleFrequency::tryFrom((string) $this->frequency) ?? ScheduleFrequency::Daily;

        $time = $this->run_at ? substr((string) $this->run_at, 0, 5) : '00:00';

        return $frequency->toCron($time, $this->weekday, $this->cron_expression);
    }

    public function isDue(?\DateTimeInterface $now = null): bool
    {
        if (! $this->is_enabled) {
            return false;
        }

        return ScheduleFrequency::cronIsDue($this->resolvedCron(), $now);
    }

    public function markResult(string $status, ?string $message = null): void
    {
        $this->forceFill([
            'last_ran_at' => now(),
            'last_status' => $status,
            'last_message' => $message,
        ])->save();
    }
}
