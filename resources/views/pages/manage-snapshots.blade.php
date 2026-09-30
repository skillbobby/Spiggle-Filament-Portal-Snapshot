<x-filament-panels::page>
    <div class="spiggle-note-grid">
        <article class="spiggle-note">
            <h2>How this works</h2>
            <p>
                Snapshots are SQL dumps stored in your configured storage disk.
                Create them on demand or on a schedule. Restore is how a demo portal resets itself every night.
            </p>
        </article>
        <article class="spiggle-note">
            <h2>Golden snapshot</h2>
            <p>Star one dump as the known-good baseline. A midnight restore schedule can always load that file, even as newer dumps accumulate.</p>
        </article>
        <article class="spiggle-note">
            <h2>Notifications</h2>
            <p>Completions land in the Filament bell. A queued email is sent when mail is configured and fails silently when it is not.</p>
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
</x-filament-panels::page>
