@if ($paginator->hasPages())
    <nav
        role="navigation"
        aria-label="Pagination"
        class="flex flex-wrap items-center justify-between gap-3 pt-1"
    >
        <p class="text-xs text-muted">
            Showing
            <span class="font-mono text-ink-soft tabular-nums">{{ $paginator->firstItem() ?? 0 }}</span>
            –<span class="font-mono text-ink-soft tabular-nums">{{ $paginator->lastItem() ?? 0 }}</span>
            of
            <span class="font-mono text-ink-soft tabular-nums">{{ $paginator->total() }}</span>
        </p>

        <div class="flex items-center gap-1">
            @if ($paginator->onFirstPage())
                <span class="inline-flex h-8 items-center gap-1 rounded-lg border border-line px-2.5 text-xs text-faint" aria-disabled="true">
                    Previous
                </span>
            @else
                <button
                    type="button"
                    wire:click="previousPage('{{ $paginator->getPageName() }}')"
                    wire:loading.attr="disabled"
                    class="inline-flex h-8 items-center gap-1 rounded-lg border border-line bg-surface px-2.5 text-xs font-medium text-ink-soft transition-colors hover:bg-subtle"
                    rel="prev"
                >
                    Previous
                </button>
            @endif

            <span class="px-2 font-mono text-xs text-muted tabular-nums">
                {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}
            </span>

            @if ($paginator->hasMorePages())
                <button
                    type="button"
                    wire:click="nextPage('{{ $paginator->getPageName() }}')"
                    wire:loading.attr="disabled"
                    class="inline-flex h-8 items-center gap-1 rounded-lg border border-line bg-surface px-2.5 text-xs font-medium text-ink-soft transition-colors hover:bg-subtle"
                    rel="next"
                >
                    Next
                </button>
            @else
                <span class="inline-flex h-8 items-center gap-1 rounded-lg border border-line px-2.5 text-xs text-faint" aria-disabled="true">
                    Next
                </span>
            @endif
        </div>
    </nav>
@endif
