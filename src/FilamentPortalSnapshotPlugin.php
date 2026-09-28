<?php

namespace Spiggle\FilamentPortalSnapshot;

use BackedEnum;
use Closure;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Concerns\EvaluatesClosures;
use LogicException;
use Spiggle\FilamentPortalSnapshot\Filament\Pages\ManageSnapshots;
use Spiggle\FilamentPortalSnapshot\Filament\Resources\SnapshotSchedules\SnapshotScheduleResource;
use Spiggle\FilamentPortalSnapshot\Filament\Widgets\SnapshotStatsWidget;
use UnitEnum;

class FilamentPortalSnapshotPlugin implements Plugin
{
    use EvaluatesClosures;

    protected static ?self $registeringPlugin = null;

    protected bool | Closure $authorizeUsing = true;

    /** @var class-string<ManageSnapshots> */
    protected string $page = ManageSnapshots::class;

    /** @var class-string<SnapshotScheduleResource> */
    protected string $scheduleResource = SnapshotScheduleResource::class;

    protected bool $hasSchedules = true;

    protected bool $hasStatsWidget = true;

    protected ?string $queue = null;

    protected ?string $pollingInterval = null;

    protected Closure | string | BackedEnum | null $navigationIcon = null;

    protected string | Closure | null $navigationLabel = null;

    protected Closure | string | UnitEnum | null $navigationGroup = null;

    protected bool $navigationGroupSet = false;

    protected Closure | int | null $navigationSort = null;

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        if (static::$registeringPlugin instanceof static) {
            return static::$registeringPlugin;
        }

        $registered = filament()->getPlugin((new static)->getId());

        if (! $registered instanceof static) {
            throw new LogicException('The Portal Snapshot plugin is not registered on the current panel.');
        }

        return $registered;
    }

    public function getId(): string
    {
        return 'spiggle-filament-portal-snapshot';
    }

    public function register(Panel $panel): void
    {
        static::$registeringPlugin = $this;

        try {
            $panel->pages([$this->page]);

            if ($this->hasSchedules) {
                $panel->resources([$this->scheduleResource]);
            }

            if ($this->hasStatsWidget) {
                $panel->widgets([SnapshotStatsWidget::class]);
            }
        } finally {
            static::$registeringPlugin = null;
        }
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public function authorize(bool | Closure $callback = true): static
    {
        $this->authorizeUsing = $callback;

        return $this;
    }

    public function isAuthorized(): bool
    {
        return (bool) $this->evaluate($this->authorizeUsing);
    }

    public function usingPage(string $page): static
    {
        $this->page = $page;

        return $this;
    }

    public function getPage(): string
    {
        return $this->page;
    }

    public function usingScheduleResource(string $resource): static
    {
        $this->scheduleResource = $resource;

        return $this;
    }

    public function schedules(bool $condition = true): static
    {
        $this->hasSchedules = $condition;

        return $this;
    }

    public function hasSchedules(): bool
    {
        return $this->hasSchedules;
    }

    public function statsWidget(bool $condition = true): static
    {
        $this->hasStatsWidget = $condition;

        return $this;
    }

    public function usingQueue(string $queue): static
    {
        $this->queue = $queue;

        return $this;
    }

    public function getQueue(): string
    {
        return $this->queue ?: (string) config('filament-portal-snapshot.queue', 'default');
    }

    public function usingPollingInterval(?string $interval): static
    {
        $this->pollingInterval = $interval;

        return $this;
    }

    public function getPollingInterval(): ?string
    {
        return $this->pollingInterval ?? config('filament-portal-snapshot.polling_interval');
    }

    public function navigationIcon(string | BackedEnum | Closure | null $icon): static
    {
        $this->navigationIcon = $icon;

        return $this;
    }

    public function getNavigationIcon(): string | BackedEnum | null
    {
        $icon = $this->evaluate($this->navigationIcon);

        return $icon ?? config('filament-portal-snapshot.navigation.icon', 'heroicon-o-camera');
    }

    public function navigationLabel(string | Closure | null $label): static
    {
        $this->navigationLabel = $label;

        return $this;
    }

    public function getNavigationLabel(): string
    {
        return $this->evaluate($this->navigationLabel)
            ?? (string) config('filament-portal-snapshot.navigation.label', 'Snapshots');
    }

    public function navigationGroup(string | Closure | UnitEnum | null $group): static
    {
        $this->navigationGroup = $group;
        $this->navigationGroupSet = true;

        return $this;
    }

    public function getNavigationGroup(): string | UnitEnum | null
    {
        if ($this->navigationGroupSet) {
            return $this->evaluate($this->navigationGroup);
        }

        return config('filament-portal-snapshot.navigation.group', 'Portal');
    }

    public function navigationSort(int | Closure | null $sort): static
    {
        $this->navigationSort = $sort;

        return $this;
    }

    public function getNavigationSort(): int
    {
        $sort = $this->evaluate($this->navigationSort);

        return is_int($sort) ? $sort : (int) config('filament-portal-snapshot.navigation.sort', 80);
    }
}
