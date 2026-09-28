# Spiggle Filament Portal Snapshot

A Filament **4.x and 5.x** panel plugin for database snapshots. It sits on top of [`spatie/laravel-db-snapshots`](https://github.com/spatie/laravel-db-snapshots) and gives operators a calm place to:

- take a snapshot now
- restore / revert a snapshot
- export (download) a dump
- copy a dump to remote storage (S3, GCS, SFTP, Google Drive via a Flysystem disk, …)
- schedule recurring snapshots
- schedule a **midnight demo reset** against a golden baseline
- clean up old dumps
- get a Filament bell notification and a queued email when work finishes

Built for portal and demo sites that need a known-good database at 00:00 every night — and for teams that want on-demand dumps without leaving the admin panel.

## Requirements

| Package | Version |
| --- | --- |
| PHP | ^8.2 |
| Laravel | 11, 12 or 13 |
| Filament | ^4.0 or ^5.0 |
| `spatie/laravel-db-snapshots` | ^2.6 |

MySQL, MariaDB, PostgreSQL and SQLite are supported — whatever Spatie can dump.

## Installation

```bash
composer require spiggle/filament-portal-snapshot
```

The Spatie package is pulled in automatically. Add a `snapshots` disk if you do not already have one:

```php
// config/filesystems.php
'disks' => [
    'snapshots' => [
        'driver' => 'local',
        'root' => database_path('snapshots'),
    ],
],
```

```bash
php artisan vendor:publish --tag="filament-portal-snapshot-config"
php artisan vendor:publish --tag="filament-portal-snapshot-migrations"
php artisan migrate
# or
php artisan filament-portal-snapshot:install
```

Register the plugin on each panel:

```php
use Spiggle\FilamentPortalSnapshot\FilamentPortalSnapshotPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugin(
            FilamentPortalSnapshotPlugin::make()
                ->authorize(fn (): bool => auth()->user()?->is_admin ?? false)
                ->navigationGroup('Portal')
                ->navigationSort(80)
        );
}
```

Ensure the Laravel scheduler is running. The plugin registers `portal-snapshot:run-schedules` every minute.

## Demo reset

1. Create a snapshot while the portal looks correct.
2. Mark it golden.
3. Add a schedule: Restore snapshot / Every night at 00:00 / Restore the golden snapshot.

## License

MIT © Spiggle

See the published `config/filament-portal-snapshot.php` for remote disks, mail recipients, restore environments, retention and queue settings.
