<x-filament-panels::page>
    <div class="flex flex-col gap-6">
        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">How this works</p>
                <p class="mt-2 text-sm text-gray-700 dark:text-gray-200">
                    Snapshots are SQL dumps managed by
                    <span class="font-medium">spatie/laravel-db-snapshots</span>.
                    Create them on demand or on a schedule. Restore is how a demo portal resets itself every night.
                </p>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Golden snapshot</p>
                <p class="mt-2 text-sm text-gray-700 dark:text-gray-200">
                    Star one dump as the known-good baseline. A midnight restore schedule can always load that file, even as newer dumps accumulate.
                </p>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Notifications</p>
                <p class="mt-2 text-sm text-gray-700 dark:text-gray-200">
                    Completions land in the Filament bell. A queued email is sent when mail is configured and fails silently when it is not.
                </p>
            </div>
        </div>

        {{ $this->table }}

        @php($operations = $this->recentOperations())

        @if (count($operations))
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Recent activity</h3>
                    <span class="text-xs text-gray-500">Last 8 operations</span>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-white/10">
                    @foreach ($operations as $operation)
                        <div class="flex items-start justify-between gap-4 py-2.5">
                            <div>
                                <p class="text-sm font-medium text-gray-950 dark:text-white">
                                    {{ $operation->type?->label() ?? $operation->type }}
                                    @if ($operation->snapshot_name)
                                        <span class="font-normal text-gray-500">· {{ $operation->snapshot_name }}</span>
                                    @endif
                                </p>
                                @if ($operation->message)
                                    <p class="mt-0.5 text-xs text-gray-500">{{ $operation->message }}</p>
                                @endif
                            </div>
                            <div class="shrink-0 text-right">
                                <span @class([
                                    'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                                    'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-200' => $operation->status?->value === 'pending',
                                    'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300' => $operation->status?->value === 'running',
                                    'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' => $operation->status?->value === 'succeeded',
                                    'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300' => $operation->status?->value === 'failed',
                                ])>
                                    {{ $operation->status?->value ?? 'unknown' }}
                                </span>
                                <p class="mt-1 text-xs text-gray-400">{{ optional($operation->created_at)->diffForHumans() }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
