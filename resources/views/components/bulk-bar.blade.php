@props([
    'count' => 0,
    'clear' => null,
    'label' => 'selected',
])

@if ($count > 0)
    <div
        {{-- Clears the mobile thumb bar, hugs the viewport bottom on desktop. --}}
        class="sticky bottom-20 z-30 mx-auto flex w-fit max-w-full flex-wrap items-center gap-2 rounded-xl border border-line-strong bg-raised px-3 py-2 shadow-lg motion-safe:animate-rise sm:bottom-4"
        role="region"
        aria-label="Bulk actions"
    >
        <span class="inline-flex items-center gap-1.5 rounded-lg bg-accent-soft px-2 py-1 font-mono text-xs font-medium text-accent tabular-nums">
            {{ $count }}
            <span class="text-[10px] tracking-wide uppercase">{{ $label }}</span>
        </span>

        <span class="h-5 w-px bg-line" aria-hidden="true"></span>

        {{ $slot }}

        @if ($clear)
            <x-button size="sm" variant="ghost" wire:click="{{ $clear }}">Clear</x-button>
        @endif
    </div>
@endif
