@props([
    'show' => false,
    'title' => null,
    'subtitle' => null,
    'close' => null,
    'size' => 'md',
    'variant' => 'modal',
])

@php
    $widths = [
        'sm' => 'sm:max-w-md',
        'md' => 'sm:max-w-xl',
        'lg' => 'sm:max-w-3xl',
        'xl' => 'sm:max-w-5xl',
    ];

    $panelWidth = $widths[$size] ?? $widths['md'];
    $isPanel = $variant === 'panel';
@endphp

@if ($show)
    <div
        class="fixed inset-0 z-40 flex {{ $isPanel ? 'justify-end' : 'items-end justify-center sm:items-center' }}"
        x-data
        x-trap.noscroll="true"
        x-on:keydown.escape.window="@if ($close) $wire.{{ $close }}() @endif"
        role="dialog"
        aria-modal="true"
        @if ($title) aria-label="{{ $title }}" @endif
    >
        <div
            class="absolute inset-0 bg-ink/40 backdrop-blur-[2px] motion-safe:animate-fade-in dark:bg-black/60"
            @if ($close) wire:click="{{ $close }}" @endif
            aria-hidden="true"
        ></div>

        <div
            class="relative flex w-full flex-col border-line bg-raised shadow-pop motion-safe:animate-rise
                {{ $isPanel
                    ? 'h-full max-w-lg border-l'
                    : 'max-h-[92svh] rounded-t-2xl border sm:rounded-2xl '.$panelWidth }}"
        >
            @if ($title || $close)
                <header class="flex items-start justify-between gap-4 border-b border-line px-5 py-4">
                    <div class="min-w-0">
                        @if ($title)
                            <h2 class="font-display text-base font-semibold tracking-tight text-ink">{{ $title }}</h2>
                        @endif
                        @if ($subtitle)
                            <p class="mt-0.5 text-xs text-muted">{{ $subtitle }}</p>
                        @endif
                    </div>

                    @if ($close)
                        <button
                            type="button"
                            wire:click="{{ $close }}"
                            class="-mr-1 flex size-8 shrink-0 items-center justify-center rounded-lg text-muted transition-colors hover:bg-subtle hover:text-ink"
                            aria-label="Close"
                        >
                            <x-icon name="x" class="size-4" />
                        </button>
                    @endif
                </header>
            @endif

            <div class="min-h-0 flex-1 overflow-y-auto px-5 py-5">
                {{ $slot }}
            </div>

            @if (isset($footer))
                <footer class="flex flex-wrap items-center justify-end gap-2 border-t border-line bg-surface/60 px-5 py-3.5">
                    {{ $footer }}
                </footer>
            @endif
        </div>
    </div>
@endif
