<?php

namespace Spiggle\FilamentPortalSnapshot\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Spiggle\FilamentPortalSnapshot\Services\SnapshotManager;

class CopySnapshotToRemoteJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $name,
        public ?string $disk = null,
        public ?int $actorId = null,
    ) {
        $this->onQueue(config('filament-portal-snapshot.queue', 'default'));

        if ($connectionName = config('filament-portal-snapshot.queue_connection')) {
            $this->onConnection($connectionName);
        }
    }

    public function handle(SnapshotManager $manager): void
    {
        $snapshot = $manager->findOrFail($this->name);

        $manager->copyToRemote($snapshot, $this->disk, $this->resolveActor());
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
