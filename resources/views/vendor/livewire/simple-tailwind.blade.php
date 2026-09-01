@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination" class="flex items-center justify-end gap-1 pt-1">
        @if ($paginator->onFirstPage())
            <span class="inline-flex h-8 items-center rounded-lg border border-line px-2.5 text-xs text-faint" aria-disabled="true">Previous</span>
        @else
            <button
                type="button"
                wire:click="previousPage('{{ $paginator->getPageName() }}')"
                wire:loading.attr="disabled"
                class="inline-flex h-8 items-center rounded-lg border border-line bg-surface px-2.5 text-xs font-medium text-ink-soft transition-colors hover:bg-subtle"
            >Previous</button>
        @endif

        @if ($paginator->hasMorePages())
            <button
                type="button"
                wire:click="nextPage('{{ $paginator->getPageName() }}')"
                wire:loading.attr="disabled"
                class="inline-flex h-8 items-center rounded-lg border border-line bg-surface px-2.5 text-xs font-medium text-ink-soft transition-colors hover:bg-subtle"
            >Next</button>
        @else
            <span class="inline-flex h-8 items-center rounded-lg border border-line px-2.5 text-xs text-faint" aria-disabled="true">Next</span>
        @endif
    </nav>
@endif
