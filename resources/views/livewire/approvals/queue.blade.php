<div
    class="space-y-6"
    x-data
    x-on:keydown.window="
        if (['INPUT','TEXTAREA','SELECT'].includes($event.target.tagName)) return;
        if ($event.key === 'j' || $event.key === 'J') { $wire.next(); }
        if ($event.key === 'k' || $event.key === 'K') { $wire.prev(); }
        if ($event.key === 'a' || $event.key === 'A') { $event.preventDefault(); $wire.approve(); }
        if ($event.key === 'r' || $event.key === 'R') { $event.preventDefault(); $wire.openReject(); }
    "
>
    <x-page-header
        title="Approval queue"
        subtitle="Submitted tasks, article drafts, and links — oldest first. Decide one, the next loads in place."
        :breadcrumbs="[['label' => 'Work'], ['label' => 'Approvals']]"
    >
        <x-slot:meta>
            <span class="font-mono text-xs text-ink-soft tabular-nums">
                {{ $queueCount === 0 ? 'Queue empty' : "Item {$position} of {$queueCount}" }}
            </span>
            <span class="hidden h-4 w-px bg-line sm:block" aria-hidden="true"></span>
            <span class="flex items-center gap-1.5 text-xs text-muted">
                <x-kbd>j</x-kbd><x-kbd>k</x-kbd> navigate
            </span>
            <span class="flex items-center gap-1.5 text-xs text-muted">
                <x-kbd>a</x-kbd> approve
            </span>
            <span class="flex items-center gap-1.5 text-xs text-muted">
                <x-kbd>r</x-kbd> reject
            </span>
        </x-slot:meta>

        <x-slot:actions>
            <x-button size="sm" variant="secondary" icon="refresh" wire:click="refreshQueue">Refresh</x-button>
        </x-slot:actions>
    </x-page-header>

    @if (! $current)
        <x-empty-state
            icon="approvals"
            title="Nothing awaiting approval"
            description="Submitted tasks, article drafts, and links land here the moment someone sends them for review."
        >
            <x-button variant="secondary" icon="refresh" wire:click="refreshQueue">Check again</x-button>
        </x-empty-state>
    @else
        @php $item = $current['model']; @endphp

        <x-card>
            <div class="flex flex-wrap items-center gap-2">
                <x-badge tone="warn" dot>{{ strtoupper($current['type']) }}</x-badge>
                @if ($item->project)
                    <span class="font-mono text-xs text-muted">{{ $item->project->domain }}</span>
                @endif
            </div>

            @if ($current['type'] === 'task')
                <h2 class="mt-3 font-display text-xl font-semibold tracking-tight text-ink">{{ $item->title }}</h2>
                <p class="mt-2 text-sm whitespace-pre-wrap text-ink-soft">{{ $item->description ?: 'No description.' }}</p>

                <dl class="mt-5 grid gap-x-6 gap-y-3 border-t border-line pt-5 sm:grid-cols-2">
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-xs text-muted">Assignee</dt>
                        <dd class="text-sm font-medium text-ink">{{ $item->assignee?->name ?? '—' }}</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-xs text-muted">Time spent</dt>
                        <dd class="font-mono text-xs text-ink tabular-nums">{{ $item->time_spent_minutes }} min</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs text-muted">Evidence</dt>
                        <dd class="mt-1.5 flex flex-wrap gap-1.5">
                            @forelse ($item->media as $file)
                                <x-badge tone="neutral" icon="upload">{{ $file->original_name }}</x-badge>
                            @empty
                                <span class="text-sm text-faint">None attached</span>
                            @endforelse
                        </dd>
                    </div>
                </dl>

                <x-button
                    variant="link"
                    size="sm"
                    class="mt-4 px-0"
                    :href="route('tasks.show', $item)"
                    wire:navigate
                    iconRight="arrow-right"
                >Open full task</x-button>
            @elseif ($current['type'] === 'article')
                <h2 class="mt-3 font-display text-xl font-semibold tracking-tight text-ink">{{ $item->title }}</h2>
                <p class="mt-1 font-mono text-sm text-muted">{{ $item->target_keyword }}</p>

                <dl class="mt-5 grid gap-x-6 gap-y-3 border-t border-line pt-5 sm:grid-cols-2">
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-xs text-muted">Writer</dt>
                        <dd class="text-sm font-medium text-ink">{{ $item->writer?->name ?? '—' }}</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-xs text-muted">Cost</dt>
                        <dd><x-money :paisa="$item->cost_paisa" :currency="\App\Support\Currency::code()" /></dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-xs text-muted">Words (actual / target)</dt>
                        <dd class="font-mono text-xs text-ink tabular-nums">{{ $item->word_count_actual ?? '—' }} / {{ $item->word_count_target ?? '—' }}</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="shrink-0 text-xs text-muted">URL</dt>
                        <dd class="min-w-0 truncate font-mono text-xs text-ink">{{ $item->published_url ?: '—' }}</dd>
                    </div>
                </dl>
            @else
                <h2 class="mt-3 font-display text-xl font-semibold tracking-tight break-all text-ink">{{ $item->source_domain }}</h2>
                <p class="mt-1 truncate font-mono text-xs text-muted">{{ $item->source_url }}</p>

                <dl class="mt-5 grid gap-x-6 gap-y-3 border-t border-line pt-5 sm:grid-cols-2">
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="shrink-0 text-xs text-muted">Target</dt>
                        <dd class="min-w-0 text-sm break-all text-ink">{{ $item->target_page }}</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-xs text-muted">Anchor</dt>
                        <dd class="text-sm font-medium text-ink">“{{ $item->anchor_text }}”</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-xs text-muted">DR / DA</dt>
                        <dd class="font-mono text-xs text-ink tabular-nums">{{ $item->dr ?? '—' }} / {{ $item->da ?? '—' }}</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-xs text-muted">Cost</dt>
                        <dd><x-money :paisa="$item->cost_paisa" :currency="\App\Support\Currency::code()" /></dd>
                    </div>
                </dl>
            @endif
        </x-card>

        @if ($showReject)
            <x-card title="Rejection reason" subtitle="Required — this is what the owner sees." icon="alert">
                <div class="space-y-3">
                    <x-textarea
                        wire:model="rejection_reason"
                        rows="3"
                        placeholder="Explain what needs to change…"
                        :error="$errors->first('rejection_reason')"
                        aria-label="Rejection reason"
                    />
                    <div class="flex flex-wrap gap-2">
                        <x-button variant="danger" wire:click="reject">Confirm reject</x-button>
                        <x-button variant="ghost" wire:click="$set('showReject', false)">Cancel</x-button>
                    </div>
                </div>
            </x-card>
        @endif

        <div class="sticky bottom-16 z-20 -mx-4 flex flex-wrap items-center gap-2 border-t border-line bg-raised/95 px-4 py-3 backdrop-blur-md sm:static sm:mx-0 sm:border-0 sm:bg-transparent sm:px-0 sm:py-0 sm:backdrop-blur-none">
            <x-button icon="check" wire:click="approve">
                Approve <span class="font-mono text-[11px] opacity-70">(a)</span>
            </x-button>
            <x-button variant="danger-soft" wire:click="openReject">
                Reject <span class="font-mono text-[11px] opacity-70">(r)</span>
            </x-button>

            <span class="ml-auto flex items-center gap-2">
                <x-tooltip text="Previous item (k)">
                    <x-button variant="secondary" square icon="chevron-left" wire:click="prev" aria-label="Previous item" />
                </x-tooltip>
                <x-tooltip text="Next item (j)">
                    <x-button variant="secondary" square icon="chevron-right" wire:click="next" aria-label="Next item" />
                </x-tooltip>
            </span>
        </div>
    @endif
</div>
