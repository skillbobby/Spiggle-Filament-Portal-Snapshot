<p align="center">
  <img src="art/banner.png" alt="Spiggle Filament Portal Snapshot" width="100%">
</p>

# Spiggle Filament Portal Snapshot

A Filament **4.x and 5.x** panel plugin for creating, scheduling, restoring, exporting, and remotely storing database snapshots with automated demo resets. It gives operators a calm, powerful dashboard to:

- take a snapshot now
- restore / revert a snapshot
- export (download) a dump
- copy a dump to remote storage (S3, GCS, SFTP, Google Drive via a Flysystem disk)
- schedule recurring snapshots
- schedule a **midnight demo reset** against a golden baseline
- clean up old dumps
- get a Filament bell notification and a queued email when work finishes

## Requirements

| Package | Version |
| --- | --- |
| PHP | ^8.3 |
| Laravel | 11, 12 or 13 |
| Filament | ^4.0 or ^5.0 |

## Installation

```bash
composer require spiggle/filament-portal-snapshot
php artisan filament-portal-snapshot:install
```

Add a `snapshots` disk to `config/filesystems.php`:

```php
'snapshots' => [
    'driver' => 'local',
    'root' => database_path('snapshots'),
],
```

Register the plugin:

```php
use Spiggle\FilamentPortalSnapshot\FilamentPortalSnapshotPlugin;

$panel->plugin(
    FilamentPortalSnapshotPlugin::make()
        ->authorize(fn (): bool => auth()->user()?->is_admin ?? false)
        ->navigationGroup('Portal')
        ->navigationSort(80)
);
```

Ensure Laravel's scheduler is running. The plugin registers `portal-snapshot:run-schedules` every minute.

## Demo reset

1. Create a snapshot while the portal looks correct.
2. Mark it golden on **Portal \u2192 Snapshots**.
3. Add a schedule: Restore snapshot / Every night at 00:00 / Restore the golden snapshot.

## Configuration

See `config/filament-portal-snapshot.php` for restore environments, confirmation phrase, remote disks, mail recipients, retention and queue settings.

```env
PORTAL_SNAPSHOT_COMPRESS=true
PORTAL_SNAPSHOT_KEEP=14
PORTAL_SNAPSHOT_QUEUE=default
PORTAL_SNAPSHOT_REMOTE_ENABLED=true
PORTAL_SNAPSHOT_REMOTE_DISKS=s3,backups
PORTAL_SNAPSHOT_MAIL=true
PORTAL_SNAPSHOT_MAIL_RECIPIENTS=ops@example.com
```

## License

MIT \ Spiggle - @iamspiggle
