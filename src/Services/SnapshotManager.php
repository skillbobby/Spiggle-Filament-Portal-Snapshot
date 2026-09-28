<?php

namespace Spiggle\FilamentPortalSnapshot\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\DbSnapshots\Snapshot;
use Spatie\DbSnapshots\SnapshotRepository;
use Spiggle\FilamentPortalSnapshot\Enums\OperationStatus;
use Spiggle\FilamentPortalSnapshot\Enums\OperationType;
use Spiggle\FilamentPortalSnapshot\Models\SnapshotOperation;
use Throwable;

class SnapshotManager
{
    public const GOLDEN_CACHE_KEY = 'portal-snapshot.golden';

    public function __construct(
        protected SnapshotRepository $snapshots,
        protected NotificationDispatcher $notifications,
        protected RemoteStorageService $remote,
    ) {}

    /** @return Collection<int, Snapshot> */
    public function all(): Collection
    {
        return $this->snapshots->getAll();
    }

    public function find(string $name): ?Snapshot
    {
        return $this->snapshots->findByName($name);
    }

    public function findOrFail(string $name): Snapshot
    {
        $snapshot = $this->find($name);

        if (! $snapshot) {
            throw new RuntimeException("Snapshot [{$name}] was not found.");
        }

        return $snapshot;
    }

    /**
     * @param  list<string>|null  $tables
     * @param  list<string>|null  $exclude
     */
    public function create(
        ?string $name = null,
        ?string $connection = null,
        bool $compress = true,
        ?array $tables = null,
        ?array $exclude = null,
        bool $copyToRemote = false,
        ?string $remoteDisk = null,
        ?Authenticatable $actor = null,
        ?int $scheduleId = null,
    ): Snapshot {
        $name = $this->sanitizeName($name ?: now()->format('Y-m-d_H-i-s'));
        $connection = $connection ?: $this->defaultConnection();

        $operation = $this->startOperation(OperationType::Create, $name, $connection, $actor, $scheduleId);

        try {
            $parameters = ['name' => $name, '--connection' => $connection];

            if ($compress) {
                $parameters['--compress'] = true;
            }

            if ($tables) {
                $parameters['--table'] = implode(',', $tables);
            } elseif ($exclude) {
                $parameters['--exclude'] = implode(',', $exclude);
            }

            $exit = Artisan::call('snapshot:create', $parameters);

            if ($exit !== 0) {
                throw new RuntimeException(trim(Artisan::output()) ?: 'snapshot:create failed.');
            }

            $snapshot = $this->findOrFail($name);

            if ($copyToRemote || config('filament-portal-snapshot.remote.copy_on_create')) {
                $this->copyToRemote($snapshot, $remoteDisk, $actor, $scheduleId);
            }

            $operation->markSucceeded('Snapshot created.', [
                'file' => $snapshot->fileName,
                'size' => $snapshot->size(),
            ]);

            $this->notifications->completed(
                title: "Snapshot \u201c{$name}\u201d created",
                body: 'The database dump finished successfully.',
                success: true,
                actor: $actor,
                meta: ['snapshot' => $name, 'file' => $snapshot->fileName],
            );

            return $snapshot;
        } catch (Throwable $exception) {
            $operation->markFailed($exception->getMessage());

            $this->notifications->completed(
                title: "Snapshot \u201c{$name}\u201d failed",
                body: $exception->getMessage(),
                success: false,
                actor: $actor,
                meta: ['snapshot' => $name],
            );

            throw $exception;
        }
    }

    public function restore(
        string $name,
        ?string $connection = null,
        bool $dropTables = true,
        bool $stream = true,
        ?Authenticatable $actor = null,
        ?int $scheduleId = null,
    ): Snapshot {
        $this->guardRestoreEnvironment();

        $snapshot = $this->findOrFail($name);
        $connection = $connection ?: $this->defaultConnection();

        $operation = $this->startOperation(OperationType::Restore, $name, $connection, $actor, $scheduleId);
        $preserved = [];

        try {
            if (config('filament-portal-snapshot.preserve_plugin_tables', true)) {
                $preserved = $this->exportPluginTables($connection);
            }

            $parameters = [
                'name' => $name,
                '--connection' => $connection,
                '--drop-tables' => $dropTables ? '1' : '0',
            ];

            if ($stream) {
                $parameters['--stream'] = true;
            }

            $exit = Artisan::call('snapshot:load', $parameters);

            if ($exit !== 0) {
                throw new RuntimeException(trim(Artisan::output()) ?: 'snapshot:load failed.');
            }

            if ($preserved !== []) {
                $this->importPluginTables($connection, $preserved);
            }

            $operation->markSucceeded('Snapshot restored.');

            $this->notifications->completed(
                title: "Snapshot \u201c{$name}\u201d restored",
                body: 'The database was reverted to this snapshot.',
                success: true,
                actor: $actor,
                meta: ['snapshot' => $name],
            );

            return $snapshot;
        } catch (Throwable $exception) {
            $operation->markFailed($exception->getMessage());

            $this->notifications->completed(
                title: "Restore of \u201c{$name}\u201d failed",
                body: $exception->getMessage(),
                success: false,
                actor: $actor,
                meta: ['snapshot' => $name],
            );

            throw $exception;
        }
    }

    public function delete(string $name, ?Authenticatable $actor = null): void
    {
        $snapshot = $this->findOrFail($name);
        $operation = $this->startOperation(OperationType::Delete, $name, null, $actor);

        try {
            $snapshot->delete();

            if ($this->goldenName() === $name) {
                $this->clearGolden();
            }

            $operation->markSucceeded('Snapshot deleted.');
        } catch (Throwable $exception) {
            $operation->markFailed($exception->getMessage());
            throw $exception;
        }
    }

    public function cleanup(int $keep, ?Authenticatable $actor = null, ?int $scheduleId = null): int
    {
        $keep = max(1, $keep);
        $operation = $this->startOperation(OperationType::Cleanup, null, null, $actor, $scheduleId);

        try {
            $before = $this->all()->count();
            Artisan::call('snapshot:cleanup', ['--keep' => $keep]);
            $after = $this->all()->count();
            $removed = max(0, $before - $after);

            $operation->markSucceeded("Kept {$keep} snapshot(s), removed {$removed}.", [
                'keep' => $keep,
                'removed' => $removed,
            ]);

            return $removed;
        } catch (Throwable $exception) {
            $operation->markFailed($exception->getMessage());
            throw $exception;
        }
    }

    public function copyToRemote(
        Snapshot $snapshot,
        ?string $disk = null,
        ?Authenticatable $actor = null,
        ?int $scheduleId = null,
    ): string {
        $operation = $this->startOperation(OperationType::RemoteCopy, $snapshot->name, null, $actor, $scheduleId, $disk);

        try {
            $path = $this->remote->copy($snapshot, $disk);
            $operation->markSucceeded("Copied to {$path}.", ['path' => $path, 'disk' => $disk]);

            $this->notifications->completed(
                title: "Snapshot \u201c{$snapshot->name}\u201d copied remotely",
                body: "Stored at {$path}.",
                success: true,
                actor: $actor,
                meta: ['snapshot' => $snapshot->name, 'path' => $path],
            );

            return $path;
        } catch (Throwable $exception) {
            $operation->markFailed($exception->getMessage());

            $this->notifications->completed(
                title: "Remote copy of \u201c{$snapshot->name}\u201d failed",
                body: $exception->getMessage(),
                success: false,
                actor: $actor,
                meta: ['snapshot' => $snapshot->name],
            );

            throw $exception;
        }
    }

    public function download(string $name): Snapshot
    {
        $snapshot = $this->findOrFail($name);

        SnapshotOperation::query()->create([
            'type' => OperationType::Export,
            'status' => OperationStatus::Succeeded,
            'snapshot_name' => $name,
            'message' => 'Download started.',
            'finished_at' => now(),
        ]);

        return $snapshot;
    }

    public function markGolden(string $name): void
    {
        $this->findOrFail($name);
        Cache::forever(self::GOLDEN_CACHE_KEY, $name);
    }

    public function clearGolden(): void
    {
        Cache::forget(self::GOLDEN_CACHE_KEY);
    }

    public function goldenName(): ?string
    {
        $name = Cache::get(self::GOLDEN_CACHE_KEY);

        return is_string($name) && $name !== '' ? $name : null;
    }

    public function resolveRestoreTarget(?string $name, bool $useGolden = false): string
    {
        if ($useGolden) {
            $golden = $this->goldenName();

            if (! $golden) {
                throw new RuntimeException('No golden snapshot is marked. Mark one before scheduling a demo reset.');
            }

            return $golden;
        }

        if (filled($name)) {
            return $name;
        }

        $latest = $this->all()->first();

        if (! $latest) {
            throw new RuntimeException('There are no snapshots to restore.');
        }

        return $latest->name;
    }

    public function defaultConnection(): string
    {
        return (string) (config('filament-portal-snapshot.default_connection')
            ?: config('db-snapshots.default_connection')
            ?: config('database.default'));
    }

    public function connections(): array
    {
        return collect(config('database.connections', []))
            ->keys()
            ->mapWithKeys(fn ($name) => [$name => $name])
            ->all();
    }

    public function canRestoreHere(): bool
    {
        $allowed = config('filament-portal-snapshot.allow_restore_environments', ['local']);

        return in_array(app()->environment(), $allowed, true);
    }

    public function guardRestoreEnvironment(): void
    {
        if (! $this->canRestoreHere()) {
            throw new RuntimeException(
                'Restoring snapshots is disabled in the ['.app()->environment().'] environment.'
            );
        }
    }

    public function sanitizeName(?string $name): string
    {
        $name = Str::of((string) $name)
            ->trim()
            ->replaceMatches('/[^A-Za-z0-9\-_]+/', '-')
            ->trim('-')
            ->limit(80, '')
            ->toString();

        return $name !== '' ? $name : now()->format('Y-m-d_H-i-s');
    }

    protected function startOperation(
        OperationType $type,
        ?string $snapshotName,
        ?string $connection,
        ?Authenticatable $actor,
        ?int $scheduleId = null,
        ?string $disk = null,
    ): SnapshotOperation {
        $operation = SnapshotOperation::query()->create([
            'type' => $type,
            'status' => OperationStatus::Pending,
            'snapshot_name' => $snapshotName,
            'connection_name' => $connection,
            'disk' => $disk,
            'schedule_id' => $scheduleId,
            'user_id' => $actor?->getAuthIdentifier(),
        ]);

        $operation->markRunning();

        return $operation;
    }

    /** @return array<string, list<array<string, mixed>>> */
    protected function exportPluginTables(string $connection): array
    {
        $tables = ['portal_snapshot_schedules', 'portal_snapshot_operations'];
        $payload = [];

        foreach ($tables as $table) {
            if (! Schema::connection($connection)->hasTable($table)) {
                continue;
            }

            $payload[$table] = DB::connection($connection)->table($table)->get()->map(fn ($row) => (array) $row)->all();
        }

        return $payload;
    }

    /** @param  array<string, list<array<string, mixed>>>  $payload */
    protected function importPluginTables(string $connection, array $payload): void
    {
        foreach ($payload as $table => $rows) {
            if (! Schema::connection($connection)->hasTable($table)) {
                continue;
            }

            $query = DB::connection($connection)->table($table);
            $query->delete();

            foreach (array_chunk($rows, 100) as $chunk) {
                $query->insert($chunk);
            }
        }
    }
}
