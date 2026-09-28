<?php

namespace Spiggle\FilamentPortalSnapshot\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Spiggle\FilamentPortalSnapshot\Services\SnapshotManager;

class CreateSnapshotJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  list<string>|null  $tables
     * @param  list<string>|null  $exclude
     */
    public function __construct(
        public ?string $name = null,
        public ?string $connection = null,
        public bool $compress = true,
        public ?array $tables = null,
        public ?array $exclude = null,
        public bool $copyToRemote = false,
        public ?string $remoteDisk = null,
        public ?int $actorId = null,
        public ?int $scheduleId = null,
    ) {
        $this->onQueue(config('filament-portal-snapshot.queue', 'default'));

        if ($connectionName = config('filament-portal-snapshot.queue_connection')) {
            $this->onConnection($connectionName);
        }
    }

    public function handle(SnapshotManager $manager): void
    {
        $actor = $this->resolveActor();

        $manager->create(
            name: $this->name,
            connection: $this->connection,
            compress: $this->compress,
            tables: $this->tables,
            exclude: $this->exclude,
            copyToRemote: $this->copyToRemote,
            remoteDisk: $this->remoteDisk,
            actor: $actor,
            scheduleId: $this->scheduleId,
        );
    }

    protected function resolveActor(): ?Authenticatable
    {
        if (! $this->actorId) {
            return null;
        }

        $model = config('auth.providers.users.model');

        if (! is_string($model) || ! class_exists($model)) {
            return null;
        }

        return $model::query()->find($this->actorId);
    }
}
