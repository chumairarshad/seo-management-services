<div class="space-y-6">
    <x-page-header
        title="Work logs"
        subtitle="A short note per day. Optional IDs of the tasks, articles and links you touched."
        :breadcrumbs="[['label' => 'People']]"
    >
        <x-slot:actions>
            @if (auth()->user()?->hasPermission('scorecards.view'))
                <x-button variant="secondary" icon="scorecard" href="{{ route('people.scorecard') }}" wire:navigate>My scorecard</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($canWrite)
        <x-card title="Today’s log" subtitle="Saving again for the same date replaces that day’s note." icon="worklogs">
            <form wire:submit="save" class="space-y-4">
                <div class="grid gap-3 sm:grid-cols-2">
                    <x-input type="date" label="Date" wire:model.live="logDate" :error="$errors->first('logDate')" />
                </div>

                <x-textarea
                    label="What did you work on?"
                    wire:model="body"
                    rows="4"
                    placeholder="Brief summary of the day…"
                    :error="$errors->first('body')"
                />

                <div class="grid gap-3 sm:grid-cols-3">
                    <x-input label="Task IDs" wire:model="taskIdsInput" hint="Comma-separated" :error="$errors->first('taskIdsInput')" />
                    <x-input label="Article IDs" wire:model="articleIdsInput" hint="Optional" :error="$errors->first('articleIdsInput')" />
                    <x-input label="Link IDs" wire:model="linkIdsInput" hint="Optional" :error="$errors->first('linkIdsInput')" />
                </div>

                <div class="flex flex-wrap justify-end gap-2">
                    <x-button type="submit" target="save">Save log</x-button>
                </div>
            </form>
        </x-card>
    @endif

    <x-filter-bar target="userId">
        @if ($canFilterUsers)
            <x-select
                size="sm"
                class="w-auto"
                data-page-search
                wire:model.live="userId"
                aria-label="Filter by person"
            >
                @foreach ($viewableUsers as $u)
                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                @endforeach
            </x-select>
        @else
            <p class="px-1 text-xs text-muted">Your own logs, newest first.</p>
        @endif

        <x-slot:trailing>
            {{ $logs->total() }} {{ \Illuminate\Support\Str::plural('log', $logs->total()) }}
        </x-slot:trailing>
    </x-filter-bar>

    <div wire:loading.delay.long.flex wire:target="userId" class="hidden">
        <x-skeleton variant="table" :rows="5" :cols="4" class="w-full" />
    </div>

    @if ($logs->isEmpty())
        <x-empty-state
            icon="worklogs"
            title="No work logs yet"
            description="Write a couple of lines about the day above. Logs feed the scorecard and give supervisors context without a status meeting."
        />
    @else
        <div wire:loading.class="opacity-60" wire:target="userId" class="transition-opacity duration-150">
            <x-table :headers="['Date', 'Person', 'Log', ['label' => 'Refs', 'align' => 'right']]">
                @foreach ($logs as $log)
                    <x-table.row wire:key="log-{{ $log->id }}">
                        <x-table.cell mono nowrap>{{ $log->local_date->format('Y-m-d') }}</x-table.cell>

                        <x-table.cell nowrap>
                            <span class="flex items-center gap-2">
                                <x-avatar :name="$log->user?->name ?? '—'" size="sm" />
                                <span class="text-sm font-medium text-ink">{{ $log->user?->name ?? 'Deleted user' }}</span>
                            </span>
                        </x-table.cell>

                        <x-table.cell>
                            <p class="max-w-prose whitespace-pre-wrap text-sm text-ink-soft">{{ $log->body }}</p>
                        </x-table.cell>

                        <x-table.cell numeric muted>
                            @if (! empty($log->task_ids)) <span class="block">T:{{ implode(',', $log->task_ids) }}</span> @endif
                            @if (! empty($log->article_ids)) <span class="block">A:{{ implode(',', $log->article_ids) }}</span> @endif
                            @if (! empty($log->link_ids)) <span class="block">L:{{ implode(',', $log->link_ids) }}</span> @endif
                            @if (empty($log->task_ids) && empty($log->article_ids) && empty($log->link_ids))
                                <span class="text-faint">—</span>
                            @endif
                        </x-table.cell>
                    </x-table.row>
                @endforeach
            </x-table>
        </div>

        {{ $logs->links() }}
    @endif
</div>
