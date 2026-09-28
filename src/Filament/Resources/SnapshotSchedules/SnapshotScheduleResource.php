<?php

namespace Spiggle\FilamentPortalSnapshot\Filament\Resources\SnapshotSchedules;

use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Spiggle\FilamentPortalSnapshot\Enums\ScheduleAction;
use Spiggle\FilamentPortalSnapshot\Enums\ScheduleFrequency;
use Spiggle\FilamentPortalSnapshot\Filament\Resources\SnapshotSchedules\Pages\CreateSnapshotSchedule;
use Spiggle\FilamentPortalSnapshot\Filament\Resources\SnapshotSchedules\Pages\EditSnapshotSchedule;
use Spiggle\FilamentPortalSnapshot\Filament\Resources\SnapshotSchedules\Pages\ListSnapshotSchedules;
use Spiggle\FilamentPortalSnapshot\FilamentPortalSnapshotPlugin;
use Spiggle\FilamentPortalSnapshot\Models\SnapshotSchedule;
use Spiggle\FilamentPortalSnapshot\Services\RemoteStorageService;
use Spiggle\FilamentPortalSnapshot\Services\SnapshotManager;
use UnitEnum;

class SnapshotScheduleResource extends Resource
{
    protected static ?string $model = SnapshotSchedule::class;
    protected static ?string $slug = 'portal-snapshot-schedules';
    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return (string) config('filament-portal-snapshot.navigation.schedules_label', 'Snapshot schedules');
    }

    public static function getNavigationIcon(): string | BackedEnum | null
    {
        return 'heroicon-o-clock';
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return FilamentPortalSnapshotPlugin::get()->getNavigationGroup();
    }

    public static function getNavigationSort(): ?int
    {
        return FilamentPortalSnapshotPlugin::get()->getNavigationSort() + 1;
    }

    public static function getModelLabel(): string
    {
        return 'snapshot schedule';
    }

    public static function getPluralModelLabel(): string
    {
        return 'snapshot schedules';
    }

    public static function canAccess(): bool
    {
        return FilamentPortalSnapshotPlugin::get()->isAuthorized()
            && FilamentPortalSnapshotPlugin::get()->hasSchedules();
    }

    public static function form(Schema $schema): Schema
    {
        $manager = app(SnapshotManager::class);

        return $schema->components([
            TextInput::make('name')->label('Schedule name')->required()->maxLength(120)->placeholder('Midnight demo reset'),
            Select::make('action')->label('What should run')
                ->options(collect(ScheduleAction::cases())->mapWithKeys(fn (ScheduleAction $action) => [$action->value => $action->label()]))
                ->required()->live()
                ->helperText(fn ($state): ?string => ScheduleAction::tryFrom((string) $state)?->description()),
            Select::make('frequency')->label('When')
                ->options(collect(ScheduleFrequency::cases())->mapWithKeys(fn (ScheduleFrequency $frequency) => [$frequency->value => $frequency->label()]))
                ->required()->live()->default(ScheduleFrequency::Midnight->value),
            TimePicker::make('run_at')->label('Time')->seconds(false)
                ->visible(fn (callable $get): bool => in_array($get('frequency'), [
                    ScheduleFrequency::Daily->value,
                    ScheduleFrequency::Weekly->value,
                    ScheduleFrequency::Hourly->value,
                    ScheduleFrequency::EverySixHours->value,
                ], true)),
            Select::make('weekday')->label('Weekday')->options([
                0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday',
            ])->visible(fn (callable $get): bool => $get('frequency') === ScheduleFrequency::Weekly->value),
            TextInput::make('cron_expression')->label('Cron expression')->placeholder('0 0 * * *')
                ->visible(fn (callable $get): bool => $get('frequency') === ScheduleFrequency::Cron->value),
            TextInput::make('snapshot_name')->label('Snapshot name')
                ->helperText('Create: used as a name prefix. Restore: the snapshot to load unless use golden is on.')
                ->visible(fn (callable $get): bool => in_array($get('action'), [ScheduleAction::Create->value, ScheduleAction::Restore->value], true)),
            Toggle::make('use_golden')->label('Restore the golden snapshot')
                ->helperText('Mark a snapshot as golden on the Snapshots page. Perfect for a demo that must return to a known baseline every night.')
                ->visible(fn (callable $get): bool => $get('action') === ScheduleAction::Restore->value),
            Select::make('connection_name')->label('Database connection')->options($manager->connections())->default($manager->defaultConnection()),
            Toggle::make('compress')->label('Compress new snapshots')->default(true)
                ->visible(fn (callable $get): bool => $get('action') === ScheduleAction::Create->value),
            TextInput::make('keep')->label('Keep this many snapshots')->numeric()->minValue(1)
                ->default(config('filament-portal-snapshot.keep', 14))
                ->visible(fn (callable $get): bool => $get('action') === ScheduleAction::Cleanup->value),
            Toggle::make('copy_to_remote')->label('Copy new snapshots to remote storage')
                ->visible(fn (callable $get): bool => $get('action') === ScheduleAction::Create->value && app(RemoteStorageService::class)->isEnabled()),
            Select::make('remote_disk')->label('Remote disk')
                ->options(fn (): array => collect(app(RemoteStorageService::class)->disks())->mapWithKeys(fn ($disk) => [$disk => $disk])->all())
                ->visible(fn (callable $get): bool => (bool) $get('copy_to_remote')),
            Toggle::make('is_enabled')->label('Enabled')->default(true),
            Textarea::make('notes')->label('Notes')->rows(3)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable()->description(fn (SnapshotSchedule $record): string => $record->resolvedCron()),
                TextColumn::make('action')->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof ScheduleAction ? $state->label() : $state)
                    ->color(fn ($state) => $state instanceof ScheduleAction ? $state->color() : 'gray'),
                TextColumn::make('frequency')->formatStateUsing(fn ($state) => $state instanceof ScheduleFrequency ? $state->label() : $state),
                IconColumn::make('is_enabled')->label('On')->boolean(),
                TextColumn::make('last_ran_at')->since()->placeholder('Never'),
                TextColumn::make('last_status')->badge()->color(fn (?string $state): string => match ($state) {
                    'succeeded' => 'success',
                    'failed' => 'danger',
                    default => 'gray',
                }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                \Filament\Actions\Action::make('runNow')->label('Run now')->icon('heroicon-o-play')->requiresConfirmation()
                    ->action(function (SnapshotSchedule $record): void {
                        \Artisan::call('portal-snapshot:run-schedules', ['--force-id' => $record->id]);
                        \Filament\Notifications\Notification::make()->title("Ran \u201c{$record->name}\u201d")->success()->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSnapshotSchedules::route('/'),
            'create' => CreateSnapshotSchedule::route('/create'),
            'edit' => EditSnapshotSchedule::route('/{record}/edit'),
        ];
    }
}
