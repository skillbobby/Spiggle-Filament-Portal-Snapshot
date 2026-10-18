<?php

namespace Spiggle\FilamentPortalSnapshot\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Spatie\DbSnapshots\Snapshot;
use Spiggle\FilamentPortalSnapshot\FilamentPortalSnapshotPlugin;
use Spiggle\FilamentPortalSnapshot\Jobs\CopySnapshotToRemoteJob;
use Spiggle\FilamentPortalSnapshot\Jobs\CreateSnapshotJob;
use Spiggle\FilamentPortalSnapshot\Jobs\RestoreSnapshotJob;
use Spiggle\FilamentPortalSnapshot\Models\SnapshotOperation;
use Spiggle\FilamentPortalSnapshot\Services\RemoteStorageService;
use Spiggle\FilamentPortalSnapshot\Services\SnapshotManager;
use Spiggle\FilamentPortalSnapshot\Services\SnapshotSettings;
use Spiggle\FilamentPortalSnapshot\Support\ManagerGate;
use UnitEnum;

class ManageSnapshots extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament-portal-snapshot::pages.manage-snapshots';

    protected static ?string $slug = 'portal-snapshots';

    protected static ?string $title = 'Portal snapshots';

    public static function getNavigationLabel(): string
    {
        return FilamentPortalSnapshotPlugin::get()->getNavigationLabel();
    }

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return FilamentPortalSnapshotPlugin::get()->getNavigationIcon();
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return FilamentPortalSnapshotPlugin::get()->getNavigationGroup();
    }

    public static function getNavigationSort(): ?int
    {
        return FilamentPortalSnapshotPlugin::get()->getNavigationSort();
    }

    public function getHeading(): string|Htmlable
    {
        return 'Portal snapshots';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Create, schedule, export and revert database snapshots. Restores drop tables on the target connection.';
    }

    public static function canAccess(): bool
    {
        return FilamentPortalSnapshotPlugin::get()->isAuthorized() && ManagerGate::allows();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('settings')
                ->label('Settings')
                ->icon('heroicon-o-cog-6-tooth')
                ->url(fn (): string => SnapshotSettingsPage::getUrl())
                ->visible(fn (): bool => FilamentPortalSnapshotPlugin::get()->hasSettings()),
        ];
    }

    /** @return array{disk: string, driver: string, location: string, writable: bool, exists: bool, label: string} */
    public function storageStatus(): array
    {
        return app(SnapshotSettings::class)->status();
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => $this->snapshotRecords())
            ->columns([
                TextColumn::make('name')->label('Snapshot')->searchable()->sortable()->weight('font-semibold')
                    ->description(fn (array $record): string => $record['file_name']),
                IconColumn::make('is_golden')->label('Golden')->boolean()
                    ->trueIcon('heroicon-s-star')->falseIcon('heroicon-o-star')
                    ->trueColor('warning')->falseColor('gray'),
                IconColumn::make('compressed')->label('Gzip')->boolean(),
                TextColumn::make('size_for_humans')->label('Size'),
                TextColumn::make('created_at')->label('Created')->since()->sortable(),
            ])
            ->headerActions([
                Action::make('createSnapshot')->label('Create snapshot')->icon('heroicon-o-camera')->color('primary')
                    ->modalHeading('Create a database snapshot')
                    ->modalDescription('Dumps the selected connection to the snapshots disk. Large databases should run on a queue.')
                    ->modalWidth('xl')->form($this->createForm())
                    ->action(fn (array $data) => $this->queueCreate($data)),
            ])
            ->recordActions([
                Action::make('restore')->label('Restore')->icon('heroicon-o-arrow-path')->color('warning')
                    ->requiresConfirmation()->modalHeading('Restore this snapshot?')
                    ->modalDescription('This drops existing tables on the target connection and loads the dump. Demo sites use this to reset overnight.')
                    ->form($this->restoreForm())
                    ->visible(fn (): bool => app(SnapshotManager::class)->canRestoreHere())
                    ->action(function (array $record, array $data): void {
                        $this->queueRestore($record['name'], $data);
                    }),
                Action::make('download')->label('Export')->icon('heroicon-o-arrow-down-tray')
                    ->action(fn (array $record) => $this->download($record['name'])),
                Action::make('copyRemote')->label('Send remote')->icon('heroicon-o-cloud-arrow-up')
                    ->visible(fn (): bool => app(RemoteStorageService::class)->isEnabled())
                    ->form([
                        Select::make('disk')->label('Remote disk')
                            ->options(fn (): array => collect(app(RemoteStorageService::class)->disks())->mapWithKeys(fn ($d) => [$d => $d])->all())
                            ->required(),
                    ])
                    ->action(function (array $record, array $data): void {
                        CopySnapshotToRemoteJob::dispatch(name: $record['name'], disk: $data['disk'] ?? null, actorId: auth()->id());
                        Notification::make()->title('Remote copy queued')->body('The snapshot will be copied to the selected disk.')->success()->send();
                    }),
                Action::make('markGolden')->label('Mark golden')->icon('heroicon-o-star')
                    ->visible(fn (array $record): bool => ! $record['is_golden'])
                    ->action(function (array $record): void {
                        app(SnapshotManager::class)->markGolden($record['name']);
                        Notification::make()->title("“{$record['name']}” is now the golden snapshot")
                            ->body('Demo reset schedules can restore this snapshot every night.')->success()->send();
                    }),
                Action::make('delete')->label('Delete')->icon('heroicon-o-trash')->color('danger')->requiresConfirmation()
                    ->action(function (array $record): void {
                        app(SnapshotManager::class)->delete($record['name'], auth()->user());
                        Notification::make()->title('Snapshot deleted')->success()->send();
                    }),
            ])
            ->paginated([10, 25, 50])
            ->defaultSort('created_at', 'desc')
            ->poll(FilamentPortalSnapshotPlugin::get()->getPollingInterval())
            ->emptyStateIcon('heroicon-o-camera')
            ->emptyStateHeading('No snapshots yet')
            ->emptyStateDescription('Create a snapshot now, or add a nightly schedule so a demo site can reset itself at midnight.')
            ->emptyStateActions([
                Action::make('createFirst')->label('Create snapshot')->icon('heroicon-o-camera')
                    ->form($this->createForm())->action(fn (array $data) => $this->queueCreate($data)),
            ]);
    }

    /** @return array<string, array<string, mixed>> */
    protected function snapshotRecords(): array
    {
        $manager = app(SnapshotManager::class);
        $golden = $manager->goldenName();

        return $manager->all()->mapWithKeys(function (Snapshot $snapshot) use ($golden) {
            return [$snapshot->name => [
                'id' => $snapshot->name,
                'name' => $snapshot->name,
                'file_name' => $snapshot->fileName,
                'compressed' => filled($snapshot->compressionExtension),
                'size' => $snapshot->size(),
                'size_for_humans' => $this->humanSize($snapshot->size()),
                'created_at' => $snapshot->createdAt(),
                'is_golden' => $golden === $snapshot->name,
            ]];
        })->all();
    }

    /** @return array<int, mixed> */
    protected function createForm(): array
    {
        $manager = app(SnapshotManager::class);

        return [
            TextInput::make('name')->label('Name')->placeholder('demo-baseline')
                ->helperText('Leave blank to use a timestamp. Letters, numbers, dashes and underscores only.')->maxLength(80),
            Select::make('connection')->label('Connection')->options($manager->connections())->default($manager->defaultConnection())->required(),
            TagsInput::make('tables')->label('Only these tables')->placeholder('users, posts')
                ->helperText('Optional. Leave empty to dump the whole database.'),
            TagsInput::make('exclude')->label('Exclude tables')->placeholder('jobs, cache')
                ->helperText('Ignored when only these tables is set.'),
            Toggle::make('compress')->label('Compress (.sql.gz)')->default((bool) config('filament-portal-snapshot.compress', true)),
            Toggle::make('copy_to_remote')->label('Also copy to remote storage')
                ->visible(fn (): bool => app(RemoteStorageService::class)->isEnabled())->default(false),
            Select::make('remote_disk')->label('Remote disk')
                ->options(fn (): array => collect(app(RemoteStorageService::class)->disks())->mapWithKeys(fn ($d) => [$d => $d])->all())
                ->visible(fn (callable $get): bool => (bool) $get('copy_to_remote')),
        ];
    }

    /** @return array<int, mixed> */
    protected function restoreForm(): array
    {
        $manager = app(SnapshotManager::class);
        $phrase = (string) config('filament-portal-snapshot.restore_confirmation_phrase', 'RESTORE');

        return [
            Select::make('connection')->label('Target connection')->options($manager->connections())->default($manager->defaultConnection())->required(),
            TextInput::make('confirmation')->label("Type {$phrase} to confirm")->required()->rules(["in:{$phrase}"]),
        ];
    }

    /** @param  array<string, mixed>  $data */
    protected function queueCreate(array $data): void
    {
        CreateSnapshotJob::dispatch(
            name: $data['name'] ?? null,
            connection: $data['connection'] ?? null,
            compress: (bool) ($data['compress'] ?? true),
            tables: $this->stringList($data['tables'] ?? null),
            exclude: $this->stringList($data['exclude'] ?? null),
            copyToRemote: (bool) ($data['copy_to_remote'] ?? false),
            remoteDisk: $data['remote_disk'] ?? null,
            actorId: auth()->id(),
        );

        Notification::make()->title('Snapshot queued')
            ->body('You will get a panel notification — and an email if mail is configured — when it finishes.')
            ->success()->send();
    }

    /** @param  array<string, mixed>  $data */
    protected function queueRestore(string $name, array $data): void
    {
        RestoreSnapshotJob::dispatch(name: $name, connection: $data['connection'] ?? null, actorId: auth()->id());
        Notification::make()->title('Restore queued')->body("“{$name}” will be loaded onto the selected connection.")->warning()->send();
    }

    protected function download(string $name)
    {
        $snapshot = app(SnapshotManager::class)->download($name);

        return $snapshot->disk->download($snapshot->fileName);
    }

    protected function humanSize(mixed $bytes): string
    {
        if (! is_numeric($bytes)) {
            return '—';
        }

        $bytes = (int) $bytes;

        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1048576) {
            return number_format($bytes / 1024, 1).' KB';
        }

        if ($bytes < 1073741824) {
            return number_format($bytes / 1048576, 1).' MB';
        }

        return number_format($bytes / 1073741824, 1).' GB';
    }

    /** @return list<string>|null */
    protected function stringList(mixed $value): ?array
    {
        if (! is_array($value) || $value === []) {
            return null;
        }

        return array_values(array_filter(array_map('strval', $value)));
    }

    /** @return array<int, SnapshotOperation> */
    public function recentOperations(): array
    {
        return SnapshotOperation::query()->latest()->limit(8)->get()->all();
    }
}
