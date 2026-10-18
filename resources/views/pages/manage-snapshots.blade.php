<x-filament-panels::page>
    @php($storage = $this->storageStatus())

    <div class="spiggle-snapshot">
        <section class="spiggle-snapshot-storage">
            <div>
                <p class="spiggle-snapshot-kicker">Storage location</p>
                <p class="spiggle-snapshot-path">{{ $storage['location'] }}</p>
                <p class="spiggle-snapshot-meta">
                    <span>Disk {{ $storage['disk'] }}</span>
                    <span aria-hidden="true">·</span>
                    <span>{{ $storage['driver'] }}</span>
                    <span class="spiggle-pill {{ ($storage['writable'] || $storage['driver'] !== 'local') ? 'is-ok' : 'is-bad' }}">
                        {{ $storage['label'] }}
                    </span>
                </p>
            </div>
            @if (\Spiggle\FilamentPortalSnapshot\FilamentPortalSnapshotPlugin::get()->hasSettings())
                <a class="spiggle-snapshot-link" href="{{ \Spiggle\FilamentPortalSnapshot\Filament\Pages\SnapshotSettingsPage::getUrl() }}">
                    Configure storage
                </a>
            @endif
        </section>

        <div class="spiggle-note-grid">
            <article class="spiggle-note">
                <span class="spiggle-note-icon" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 7.5l3 2.25-3 2.25m4.5 0h3m-9 8.25h13.5A2.25 2.25 0 0021 18V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6v12a2.25 2.25 0 002.25 2.25z" />
                    </svg>
                </span>
                <h2>How this works</h2>
                <p>Snapshots are SQL dumps stored on the disk shown above. Create one now, or let a schedule take one for you. Restore is how a demo portal returns to a known state overnight.</p>
            </article>
            <article class="spiggle-note">
                <span class="spiggle-note-icon" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.562.562 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.562.562 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" />
                    </svg>
                </span>
                <h2>Golden snapshot</h2>
                <p>Star one dump as the known-good baseline. A midnight restore schedule can always load that file, even as newer dumps accumulate.</p>
            </article>
            <article class="spiggle-note">
                <span class="spiggle-note-icon" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                    </svg>
                </span>
                <h2>Notifications</h2>
                <p>Completions land in the Filament bell. A queued email is sent when mail is configured, and that send fails quietly when it is not.</p>
            </article>
        </div>

        {{ $this->table }}

        @php($operations = $this->recentOperations())

        @if (count($operations))
            <section class="spiggle-activity">
                <header class="spiggle-activity-head">
                    <h3>Recent activity</h3>
                    <span>Last 8 operations</span>
                </header>
                @foreach ($operations as $operation)
                    <div class="spiggle-activity-row">
                        <div>
                            <p class="spiggle-activity-title">
                                {{ $operation->type?->label() ?? $operation->type }}
                                @if ($operation->snapshot_name)
                                    <span>· {{ $operation->snapshot_name }}</span>
                                @endif
                            </p>
                            @if ($operation->message)
                                <p class="spiggle-activity-message">{{ $operation->message }}</p>
                            @endif
                        </div>
                        <div class="spiggle-activity-meta">
                            <span class="spiggle-status spiggle-status-{{ $operation->status?->value ?? 'unknown' }}">
                                {{ $operation->status?->value ?? 'unknown' }}
                            </span>
                            <p>{{ optional($operation->created_at)->diffForHumans() }}</p>
                        </div>
                    </div>
                @endforeach
            </section>
        @endif
    </div>
</x-filament-panels::page>
