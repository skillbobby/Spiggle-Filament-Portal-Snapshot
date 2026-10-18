<?php

namespace Spiggle\FilamentPortalSnapshot\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\ValidationException;
use Spiggle\FilamentPortalSnapshot\FilamentPortalSnapshotPlugin;
use Spiggle\FilamentPortalSnapshot\Services\SnapshotManager;
use Spiggle\FilamentPortalSnapshot\Services\SnapshotSettings;
use Spiggle\FilamentPortalSnapshot\Support\ManagerGate;
use UnitEnum;

class SnapshotSettingsPage extends Page
{
    protected static ?string $slug = 'portal-snapshot-settings';

    protected static ?string $title = 'Snapshot settings';

    protected Width | string | null $maxContentWidth = Width::ScreenExtraLarge;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(app(SnapshotSettings::class)->formData());
    }

    public function getHeading(): string|Htmlable
    {
        return 'Snapshot settings';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Choose where dumps are stored, which disks receive copies, and how restores and notifications behave.';
    }

    public static function getNavigationLabel(): string
    {
        return (string) config('filament-portal-snapshot.navigation.settings_label', 'Snapshot settings');
    }

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return 'heroicon-o-cog-6-tooth';
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return FilamentPortalSnapshotPlugin::get()->getNavigationGroup();
    }

    public static function getNavigationSort(): ?int
    {
        return FilamentPortalSnapshotPlugin::get()->getNavigationSort() + 2;
    }

    public static function canAccess(): bool
    {
        return FilamentPortalSnapshotPlugin::get()->hasSettings()
            && FilamentPortalSnapshotPlugin::get()->isAuthorized()
            && ManagerGate::allows();
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->operation('edit');
    }

    public function form(Schema $schema): Schema
    {
        $settings = app(SnapshotSettings::class);
        $manager = app(SnapshotManager::class);

        return $schema->components([
            Section::make('Storage')
                ->description('Dumps are written to a filesystem disk. Set a directory when that disk is local.')
                ->icon('heroicon-o-circle-stack')
                ->columns(2)
                ->schema([
                    Select::make('disk')
                        ->label('Snapshot disk')
                        ->options($settings->diskChoices())
                        ->required()
                        ->live()
                        ->helperText('Defined in config/filesystems.php. The default disk is snapshots.'),
                    TextInput::make('path')
                        ->label('Local directory')
                        ->placeholder('/var/backups/portal-snapshots')
                        ->live(onBlur: true)
                        ->visible(fn (Get $get): bool => $this->diskIsLocal((string) $get('disk')))
                        ->helperText('Leave blank to keep the disk root from config/filesystems.php. Existing dumps stay in the previous folder.'),
                    Placeholder::make('location')
                        ->label('Resolved location')
                        ->dehydrated(false)
                        ->columnSpanFull()
                        ->content(fn (Get $get): string => $settings->previewLocation(
                            is_string($get('disk')) ? $get('disk') : null,
                            is_string($get('path')) ? $get('path') : null,
                        )),
                ]),
            Section::make('Defaults')
                ->description('Used when someone creates a snapshot or a cleanup schedule does not set its own value.')
                ->icon('heroicon-o-adjustments-horizontal')
                ->columns(2)
                ->schema([
                    Select::make('connection')
                        ->label('Default connection')
                        ->options($manager->connections())
                        ->required(),
                    TextInput::make('keep')
                        ->label('Snapshots to keep')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(365)
                        ->required()
                        ->helperText('Cleanup schedules delete older dumps beyond this count.'),
                    Toggle::make('compress')
                        ->label('Compress new snapshots')
                        ->helperText('Writes .sql.gz files.'),
                    TagsInput::make('excluded_tables')
                        ->label('Tables left out of new dumps')
                        ->placeholder('jobs, cache')
                        ->columnSpanFull(),
                ]),
            Section::make('Remote copies')
                ->description('Optional extra copy on S3, SFTP, or any other Flysystem disk.')
                ->icon('heroicon-o-cloud-arrow-up')
                ->columns(2)
                ->schema([
                    Toggle::make('remote_enabled')
                        ->label('Enable remote copies')
                        ->live()
                        ->columnSpanFull(),
                    Select::make('remote_disks')
                        ->label('Remote disks')
                        ->multiple()
                        ->options(fn (Get $get): array => collect($settings->diskChoices())
                            ->except([(string) $get('disk')])
                            ->all())
                        ->visible(fn (Get $get): bool => (bool) $get('remote_enabled'))
                        ->dehydratedWhenHidden()
                        ->helperText('Must be a different disk from the one that stores the working copy.'),
                    TextInput::make('remote_directory')
                        ->label('Remote folder')
                        ->placeholder('portal-snapshots')
                        ->visible(fn (Get $get): bool => (bool) $get('remote_enabled'))
                        ->dehydratedWhenHidden(),
                    Toggle::make('remote_copy_on_create')
                        ->label('Copy every new snapshot automatically')
                        ->visible(fn (Get $get): bool => (bool) $get('remote_enabled'))
                        ->dehydratedWhenHidden()
                        ->columnSpanFull(),
                ]),
            Section::make('Notifications')
                ->description('Finished jobs can ring the Filament bell and send mail.')
                ->icon('heroicon-o-bell')
                ->columns(2)
                ->schema([
                    Toggle::make('notify_database')
                        ->label('Panel notification'),
                    Toggle::make('notify_mail')
                        ->label('Email notification')
                        ->live(),
                    TagsInput::make('mail_recipients')
                        ->label('Mail recipients')
                        ->placeholder('ops@example.com')
                        ->visible(fn (Get $get): bool => (bool) $get('notify_mail'))
                        ->dehydratedWhenHidden()
                        ->helperText('Leave empty to email the person who started the job, when they have an address.'),
                    TextInput::make('mail_queue')
                        ->label('Mail queue')
                        ->visible(fn (Get $get): bool => (bool) $get('notify_mail'))
                        ->dehydratedWhenHidden(),
                ]),
            Section::make('Restore safety')
                ->description('Restores drop tables on the target connection.')
                ->icon('heroicon-o-shield-check')
                ->columns(2)
                ->schema([
                    TagsInput::make('restore_environments')
                        ->label('Environments where restore is allowed')
                        ->placeholder('local')
                        ->required()
                        ->helperText('This app is running in the '.app()->environment().' environment. Restores are refused everywhere else.')
                        ->columnSpanFull(),
                    TextInput::make('restore_confirmation_phrase')
                        ->label('Confirmation phrase')
                        ->required()
                        ->maxLength(40)
                        ->helperText('An operator must type this before a restore is queued.'),
                    Toggle::make('preserve_plugin_tables')
                        ->label('Keep snapshot schedules and settings across a restore')
                        ->columnSpanFull(),
                ]),
            Section::make('Queue')
                ->description('Large dumps should run on a queue worker, not in the web request.')
                ->icon('heroicon-o-queue-list')
                ->columns(2)
                ->schema([
                    TextInput::make('queue')
                        ->label('Queue name')
                        ->required(),
                    TextInput::make('queue_connection')
                        ->label('Queue connection')
                        ->placeholder('database')
                        ->helperText('Leave blank to use the application default.'),
                ]),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('snapshot-settings')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')
                            ->label('Save settings')
                            ->submit('save')
                            ->keyBindings(['mod+s']),
                        Action::make('reset')
                            ->label('Reset to config defaults')
                            ->color('gray')
                            ->requiresConfirmation()
                            ->modalHeading('Reset snapshot settings?')
                            ->modalDescription('Clears the saved settings and uses config/filament-portal-snapshot.php and your environment variables again.')
                            ->action(fn () => $this->resetDefaults()),
                    ])->alignment(Alignment::Start)->key('snapshot-settings-actions'),
                ]),
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        try {
            $saved = app(SnapshotSettings::class)->save($data);
        } catch (ValidationException $exception) {
            $this->setErrorBag(new MessageBag($exception->errors()));
            $this->notifyValidation($exception);

            return;
        }

        $this->form->fill($saved);

        Notification::make()
            ->title('Snapshot settings saved')
            ->body('New snapshots will use this storage location.')
            ->success()
            ->send();
    }

    public function resetDefaults(): void
    {
        $this->form->fill(app(SnapshotSettings::class)->reset());

        Notification::make()
            ->title('Snapshot settings reset')
            ->body('Config and environment values are in use again.')
            ->success()
            ->send();
    }

    protected function diskIsLocal(string $disk): bool
    {
        if ($disk === '') {
            return true;
        }

        $driver = config("filesystems.disks.{$disk}.driver");

        return $driver === null || $driver === 'local';
    }

    protected function notifyValidation(ValidationException $exception): void
    {
        $message = collect($exception->errors())->flatten()->first();

        Notification::make()
            ->title('Settings were not saved')
            ->body(is_string($message) ? $message : 'Check the form and try again.')
            ->danger()
            ->send();
    }
}
