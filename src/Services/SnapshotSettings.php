<?php

namespace Spiggle\FilamentPortalSnapshot\Services;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spiggle\FilamentPortalSnapshot\Models\SnapshotSetting;
use Throwable;

class SnapshotSettings
{
    /** @var array<string, mixed>|null */
    protected ?array $baseline = null;

    /** @var array<string, array<string, mixed>>|null */
    protected ?array $baselineDisks = null;

    protected ?string $activeDisk = null;

    public function apply(): void
    {
        $this->rememberBaseline();
        $this->push($this->formData());
    }

    /** @return array<string, mixed> */
    public function formData(): array
    {
        $this->rememberBaseline();

        $stored = $this->stored();

        if ($stored === null) {
            return $this->baseline;
        }

        return array_replace($this->baseline, $stored);
    }

    /** @param  array<string, mixed>  $input */
    public function save(array $input): array
    {
        $this->rememberBaseline();
        $form = $this->normalize($input);
        $this->assertValid($form);
        $this->ensureTable();
        $this->prepareDirectory($form);

        $row = SnapshotSetting::query()->first();

        if ($row) {
            $row->update(['payload' => $form]);
        } else {
            SnapshotSetting::query()->create(['payload' => $form]);
        }

        $this->push($form);

        return $form;
    }

    public function reset(): array
    {
        $this->rememberBaseline();

        if ($this->tableReady()) {
            SnapshotSetting::query()->delete();
        }

        $this->push($this->baseline);

        return $this->baseline;
    }

    /** @return array<string, string> */
    public function diskChoices(): array
    {
        $this->rememberBaseline();

        $choices = [];

        foreach ($this->baselineDisks as $name => $disk) {
            $driver = (string) ($disk['driver'] ?? 'unknown');
            $choices[$name] = $name.' · '.$driver;
        }

        return $choices;
    }

    public function previewLocation(?string $disk, ?string $path): string
    {
        $this->rememberBaseline();

        $disk = $disk ?: (string) ($this->baseline['disk'] ?? 'snapshots');
        $definition = $this->baselineDisks[$disk] ?? [];
        $driver = (string) ($definition['driver'] ?? 'unknown');
        $custom = trim((string) $path);

        if ($custom !== '' && ($driver === 'local' || $driver === 'unknown')) {
            return $this->absolutePath($custom).' · local directory';
        }

        if ($driver === 'local') {
            $root = (string) ($definition['root'] ?? '');

            return ($root !== '' ? $root : 'Disk root is not set').' · '.$disk.' disk';
        }

        $bucket = (string) ($definition['bucket'] ?? $definition['root'] ?? '');

        return trim($disk.' · '.$driver.($bucket !== '' ? ' · '.$bucket : ''));
    }

    /** @return array{disk: string, driver: string, location: string, writable: bool, exists: bool, label: string} */
    public function status(): array
    {
        $disk = (string) config('db-snapshots.disk', 'snapshots');
        $definition = config("filesystems.disks.{$disk}", []);
        $definition = is_array($definition) ? $definition : [];
        $driver = (string) ($definition['driver'] ?? 'unknown');
        $location = $driver === 'local'
            ? (string) ($definition['root'] ?? '')
            : (string) ($definition['bucket'] ?? $definition['root'] ?? $disk);

        $exists = $driver === 'local' && $location !== '' && is_dir($location);
        $writable = $exists && is_writable($location);

        $label = $writable ? 'Writable' : ($exists ? 'Not writable' : ($driver === 'local' ? 'Directory missing' : 'Remote disk'));

        return [
            'disk' => $disk,
            'driver' => $driver,
            'location' => $location !== '' ? $location : $disk,
            'writable' => $writable,
            'exists' => $exists || $driver !== 'local',
            'label' => $label,
        ];
    }

    protected function rememberBaseline(): void
    {
        if ($this->baseline !== null) {
            return;
        }

        $disks = config('filesystems.disks', []);
        $this->baselineDisks = is_array($disks) ? $disks : [];

        $disk = (string) (config('filament-portal-snapshot.storage.disk')
            ?: config('db-snapshots.disk', 'snapshots'));

        $mail = config('filament-portal-snapshot.notifications.mail', []);
        $mail = is_array($mail) ? $mail : [];

        $this->baseline = [
            'disk' => $disk,
            'path' => (string) config('filament-portal-snapshot.storage.path', ''),
            'connection' => (string) (config('filament-portal-snapshot.default_connection')
                ?: config('database.default')),
            'compress' => (bool) config('filament-portal-snapshot.compress', true),
            'keep' => (int) config('filament-portal-snapshot.keep', 14),
            'excluded_tables' => $this->stringList(config('filament-portal-snapshot.excluded_tables', [])),
            'remote_enabled' => (bool) config('filament-portal-snapshot.remote.enabled', false),
            'remote_disks' => $this->stringList(config('filament-portal-snapshot.remote.disks', [])),
            'remote_directory' => (string) config('filament-portal-snapshot.remote.directory', 'portal-snapshots'),
            'remote_copy_on_create' => (bool) config('filament-portal-snapshot.remote.copy_on_create', false),
            'notify_database' => (bool) config('filament-portal-snapshot.notifications.database', true),
            'notify_mail' => (bool) ($mail['enabled'] ?? true),
            'mail_recipients' => $this->stringList($mail['recipients'] ?? []),
            'mail_queue' => (string) ($mail['queue'] ?? 'default'),
            'restore_environments' => $this->stringList(config('filament-portal-snapshot.allow_restore_environments', ['local'])),
            'restore_confirmation_phrase' => (string) config('filament-portal-snapshot.restore_confirmation_phrase', 'RESTORE'),
            'preserve_plugin_tables' => (bool) config('filament-portal-snapshot.preserve_plugin_tables', true),
            'queue' => (string) config('filament-portal-snapshot.queue', 'default'),
            'queue_connection' => (string) config('filament-portal-snapshot.queue_connection', ''),
        ];
    }

    /** @return array<string, mixed>|null */
    protected function stored(): ?array
    {
        if (! $this->tableReady()) {
            return null;
        }

        $payload = SnapshotSetting::query()->value('payload');

        if (! is_array($payload) || $payload === []) {
            return null;
        }

        return $this->normalize($payload);
    }

    /** @param  array<string, mixed>  $form */
    protected function push(array $form): void
    {
        $disk = (string) ($form['disk'] ?: 'snapshots');
        $previous = $this->activeDisk;
        $definition = $this->baselineDisks[$disk] ?? ['driver' => 'local', 'throw' => false, 'report' => false];
        $driver = (string) ($definition['driver'] ?? 'local');
        $path = trim((string) ($form['path'] ?? ''));

        if ($path !== '' && $driver === 'local') {
            $definition = array_merge($definition, [
                'driver' => 'local',
                'root' => $this->absolutePath($path),
                'throw' => false,
            ]);

            if (! is_dir($definition['root'])) {
                @mkdir($definition['root'], 0755, true);
            }
        }

        config([
            'db-snapshots.disk' => $disk,
            'filament-portal-snapshot.storage.disk' => $disk,
            'filament-portal-snapshot.storage.path' => $path,
            "filesystems.disks.{$disk}" => $definition,
            'filament-portal-snapshot.default_connection' => $form['connection'] ?: null,
            'filament-portal-snapshot.compress' => (bool) $form['compress'],
            'filament-portal-snapshot.keep' => (int) $form['keep'],
            'filament-portal-snapshot.excluded_tables' => $form['excluded_tables'],
            'filament-portal-snapshot.remote.enabled' => (bool) $form['remote_enabled'],
            'filament-portal-snapshot.remote.disks' => $form['remote_disks'],
            'filament-portal-snapshot.remote.directory' => $form['remote_directory'],
            'filament-portal-snapshot.remote.copy_on_create' => (bool) $form['remote_copy_on_create'],
            'filament-portal-snapshot.notifications.database' => (bool) $form['notify_database'],
            'filament-portal-snapshot.notifications.mail.enabled' => (bool) $form['notify_mail'],
            'filament-portal-snapshot.notifications.mail.recipients' => $form['mail_recipients'],
            'filament-portal-snapshot.notifications.mail.queue' => $form['mail_queue'] ?: 'default',
            'filament-portal-snapshot.allow_restore_environments' => $form['restore_environments'],
            'filament-portal-snapshot.restore_confirmation_phrase' => $form['restore_confirmation_phrase'],
            'filament-portal-snapshot.preserve_plugin_tables' => (bool) $form['preserve_plugin_tables'],
            'filament-portal-snapshot.queue' => $form['queue'] ?: 'default',
            'filament-portal-snapshot.queue_connection' => $form['queue_connection'] !== '' ? $form['queue_connection'] : null,
        ]);

        $this->forgetDisk($previous);
        $this->forgetDisk($disk);
        $this->activeDisk = $disk;
    }

    /** @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected function normalize(array $input): array
    {
        $this->rememberBaseline();

        $form = array_replace($this->baseline, $input);

        $form['disk'] = trim((string) ($form['disk'] ?? ''));
        $form['path'] = trim((string) ($form['path'] ?? ''));
        $form['connection'] = trim((string) ($form['connection'] ?? ''));
        $form['compress'] = (bool) ($form['compress'] ?? false);
        $form['keep'] = (int) ($form['keep'] ?? 14);
        $form['excluded_tables'] = $this->stringList($form['excluded_tables'] ?? []);
        $form['remote_enabled'] = (bool) ($form['remote_enabled'] ?? false);
        $form['remote_disks'] = $this->stringList($form['remote_disks'] ?? []);
        $form['remote_directory'] = trim((string) ($form['remote_directory'] ?? 'portal-snapshots'), " \t\n\r\0\x0B/");
        $form['remote_copy_on_create'] = (bool) ($form['remote_copy_on_create'] ?? false);
        $form['notify_database'] = (bool) ($form['notify_database'] ?? false);
        $form['notify_mail'] = (bool) ($form['notify_mail'] ?? false);
        $form['mail_recipients'] = $this->stringList($form['mail_recipients'] ?? []);
        $form['mail_queue'] = trim((string) ($form['mail_queue'] ?? 'default'));
        $form['restore_environments'] = array_values(array_unique(array_map(
            fn (string $environment): string => strtolower($environment),
            $this->stringList($form['restore_environments'] ?? []),
        )));
        $form['restore_confirmation_phrase'] = trim((string) ($form['restore_confirmation_phrase'] ?? 'RESTORE'));
        $form['preserve_plugin_tables'] = (bool) ($form['preserve_plugin_tables'] ?? true);
        $form['queue'] = trim((string) ($form['queue'] ?? 'default'));
        $form['queue_connection'] = trim((string) ($form['queue_connection'] ?? ''));

        $driver = (string) (($this->baselineDisks[$form['disk']]['driver'] ?? null));

        if ($driver !== '' && $driver !== 'local') {
            $form['path'] = '';
        }

        return $form;
    }

    /** @param  array<string, mixed>  $form */
    protected function assertValid(array $form): void
    {
        $errors = [];

        if ($form['disk'] === '' || ! array_key_exists($form['disk'], $this->baselineDisks ?? [])) {
            $errors['data.disk'] = 'Choose a filesystem disk that already exists in config/filesystems.php.';
        }

        if ($form['path'] !== '') {
            try {
                $this->guardPath($form['path']);
            } catch (ValidationException $exception) {
                $errors = array_merge($errors, $exception->errors());
            }
        }

        if ($form['keep'] < 1 || $form['keep'] > 365) {
            $errors['data.keep'] = 'Keep between 1 and 365 snapshots.';
        }

        if ($form['restore_confirmation_phrase'] === '') {
            $errors['data.restore_confirmation_phrase'] = 'A confirmation phrase is required before a restore can run.';
        }

        if ($form['restore_environments'] === []) {
            $errors['data.restore_environments'] = 'List at least one environment where restores are allowed.';
        }

        foreach ($form['remote_disks'] as $remoteDisk) {
            if (! array_key_exists($remoteDisk, $this->baselineDisks ?? [])) {
                $errors['data.remote_disks'] = "Remote disk [{$remoteDisk}] is not configured.";
                break;
            }

            if ($remoteDisk === $form['disk']) {
                $errors['data.remote_disks'] = 'Remote copies must use a different disk from the snapshot disk.';
                break;
            }
        }

        if ($form['remote_enabled'] && $form['remote_disks'] === []) {
            $errors['data.remote_disks'] = 'Choose at least one remote disk, or turn remote copies off.';
        }

        foreach ($form['mail_recipients'] as $recipient) {
            if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                $errors['data.mail_recipients'] = "{$recipient} is not an email address.";
                break;
            }
        }

        if ($form['connection'] !== '' && ! array_key_exists($form['connection'], config('database.connections', []))) {
            $errors['data.connection'] = 'Choose a database connection from config/database.php.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** @param  array<string, mixed>  $form */
    protected function prepareDirectory(array $form): void
    {
        if ($form['path'] === '') {
            return;
        }

        $root = $this->absolutePath($form['path']);

        if (is_dir($root)) {
            if (! is_writable($root)) {
                throw ValidationException::withMessages([
                    'data.path' => 'That directory exists, but the web server cannot write to it.',
                ]);
            }

            return;
        }

        $parent = dirname($root);

        if (! is_dir($parent) || ! is_writable($parent)) {
            throw ValidationException::withMessages([
                'data.path' => 'The parent directory must already exist and be writable.',
            ]);
        }

        if (! @mkdir($root, 0755, true) && ! is_dir($root)) {
            throw ValidationException::withMessages([
                'data.path' => 'The directory could not be created.',
            ]);
        }
    }

    protected function guardPath(string $path): void
    {
        if (str_contains($path, "\0")) {
            throw ValidationException::withMessages([
                'data.path' => 'That path is not valid.',
            ]);
        }

        $root = $this->absolutePath($path);
        $segments = array_values(array_filter(explode('/', $root), fn (string $segment): bool => $segment !== ''));

        if (count($segments) < 2) {
            throw ValidationException::withMessages([
                'data.path' => 'Use a specific directory, not a filesystem root.',
            ]);
        }

        $public = realpath(public_path()) ?: public_path();
        $vendor = base_path('vendor');

        foreach ([$public, $vendor] as $blocked) {
            $blocked = rtrim($blocked, '/');

            if ($root === $blocked || str_starts_with($root, $blocked.'/')) {
                throw ValidationException::withMessages([
                    'data.path' => 'Store dumps outside the public web root and the vendor directory.',
                ]);
            }
        }
    }

    protected function absolutePath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));

        if (! str_starts_with($path, '/')) {
            $path = base_path($path);
        }

        $real = realpath($path);

        if ($real !== false) {
            return $real;
        }

        $parts = [];

        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }

            if ($part === '..') {
                array_pop($parts);

                continue;
            }

            $parts[] = $part;
        }

        return '/'.implode('/', $parts);
    }

    protected function ensureTable(): void
    {
        if ($this->tableReady()) {
            return;
        }

        throw ValidationException::withMessages([
            'data.disk' => 'Run php artisan migrate so snapshot settings can be saved.',
        ]);
    }

    protected function tableReady(): bool
    {
        try {
            return Schema::hasTable('portal_snapshot_settings');
        } catch (Throwable) {
            return false;
        }
    }

    protected function forgetDisk(?string $disk): void
    {
        if (! is_string($disk) || $disk === '') {
            return;
        }

        try {
            Storage::forgetDisk($disk);
        } catch (Throwable) {
            // The disk may not have been resolved yet.
        }
    }

    /** @return list<string> */
    protected function stringList(mixed $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (mixed $item): string => trim((string) $item),
            $value,
        ), fn (string $item): bool => $item !== ''));
    }
}
