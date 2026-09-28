<?php

namespace Spiggle\FilamentPortalSnapshot\Services;

use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Spatie\DbSnapshots\Snapshot;

class RemoteStorageService
{
    /** @return list<string> */
    public function disks(): array
    {
        $configured = config('filament-portal-snapshot.remote.disks', []);
        $configured = is_array($configured) ? $configured : [];

        return array_values(array_filter($configured, fn ($disk) => is_string($disk) && $disk !== ''));
    }

    public function isEnabled(): bool
    {
        return (bool) config('filament-portal-snapshot.remote.enabled', false) && $this->disks() !== [];
    }

    public function copy(Snapshot $snapshot, ?string $disk = null): string
    {
        $disk = $disk ?: ($this->disks()[0] ?? null);

        if (! $disk) {
            throw new RuntimeException('No remote snapshot disk is configured.');
        }

        $directory = trim((string) config('filament-portal-snapshot.remote.directory', 'portal-snapshots'), '/');
        $destination = ($directory !== '' ? $directory.'/' : '').$snapshot->fileName;
        $stream = $snapshot->disk->readStream($snapshot->fileName);

        if ($stream === false) {
            throw new RuntimeException("Unable to read snapshot [{$snapshot->fileName}] from the snapshots disk.");
        }

        try {
            $written = Storage::disk($disk)->writeStream($destination, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if ($written === false) {
            throw new RuntimeException("Unable to write snapshot to remote disk [{$disk}].");
        }

        return $destination;
    }
}
