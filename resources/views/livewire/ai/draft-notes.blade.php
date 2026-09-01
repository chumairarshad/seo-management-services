@php
    $canReview = auth()->user()?->isAdmin() || auth()->user()?->hasPermission('settings.update');
@endphp

<div class="space-y-6">
    <x-page-header
        title="Monthly notes for review"
        subtitle="Auto-drafted portfolio and performance notes plus revenue/cost anomaly flags. Nothing is ever auto-emailed."
        :breadcrumbs="[['label' => 'Workspace']]"
        back="{{ route('ai.ask') }}"
    >
        <x-slot:meta>
            <x-badge tone="accent" size="sm" dot>Drafts only</x-badge>
            <x-badge tone="neutral" size="sm">{{ $notes->count() }} in view</x-badge>
        </x-slot:meta>
    </x-page-header>

    @if ($notes->isEmpty())
        <x-empty-state
            icon="ai"
            title="No draft notes yet"
            description="Run php artisan ai:draft-monthly-summaries after AI is configured. Drafts land here for a human to read, keep or dismiss."
        />
    @else
        <div class="space-y-4">
            @foreach ($notes as $note)
                <x-card wire:key="note-{{ $note->id }}">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <x-badge size="sm" tone="accent">AI-generated draft</x-badge>
                                <x-badge size="sm" :tone="match($note->status) {
                                    'reviewed' => 'success',
                                    'dismissed' => 'warn',
                                    default => 'info',
                                }">{{ $note->status }}</x-badge>
                                <span class="font-mono text-[10px] text-muted uppercase">{{ $note->type }} · {{ $note->period }}</span>
                            </div>
                            <h2 class="mt-2 font-display text-base font-semibold tracking-tight text-ink">{{ $note->title }}</h2>
                        </div>

                        @if ($note->status === 'draft' && $canReview)
                            <div class="flex shrink-0 flex-wrap items-center gap-1">
                                <x-tooltip text="Mark reviewed">
                                    <x-button size="sm" variant="ghost" icon="check-circle" wire:click="markReviewed({{ $note->id }})" aria-label="Mark {{ $note->title }} reviewed">
                                        Reviewed
                                    </x-button>
                                </x-tooltip>
                                <x-tooltip text="Dismiss">
                                    <x-button size="sm" square variant="danger-ghost" wire:click="dismiss({{ $note->id }})" aria-label="Dismiss {{ $note->title }}">
                                        <x-icon name="x" class="size-3.5" />
                                    </x-button>
                                </x-tooltip>
                            </div>
                        @endif
                    </div>

                    <p class="mt-3 text-sm whitespace-pre-wrap text-ink-soft">{{ $note->body }}</p>

                    @if ($note->source_figures)
                        <details class="mt-4 rounded-lg border border-line px-3 py-2">
                            <summary class="cursor-pointer text-xs font-medium text-muted">Source figures</summary>
                            <pre class="mt-2 overflow-x-auto font-mono text-[10px] text-muted">{{ json_encode($note->source_figures, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                        </details>
                    @endif
                </x-card>
            @endforeach
        </div>
    @endif
</div>
