@props([
    'title' => null,
    'subtitle' => null,
    'padding' => 'md',
    'flush' => false,
    'actions' => null,
    'icon' => null,
])

@php
    $paddings = [
        'none' => '',
        'sm' => 'p-4',
        'md' => 'p-4 sm:p-6',
        'lg' => 'p-6 sm:p-8',
    ];

    $pad = $flush ? '' : ($paddings[$padding] ?? $paddings['md']);
    $hasHeader = $title || $subtitle || $actions;
@endphp

<section {{ $attributes->merge(['class' => 'min-w-0 rounded-xl border border-line bg-surface shadow-xs']) }}>
    @if ($hasHeader)
        <header class="flex flex-wrap items-start justify-between gap-3 border-b border-line px-4 py-3.5 sm:px-6">
            <div class="min-w-0">
                <h2 class="flex items-center gap-2 text-sm font-semibold tracking-tight text-ink">
                    @if ($icon)
                        <x-icon :name="$icon" class="size-4 text-muted" />
                    @endif
                    {{ $title }}
                </h2>
                @if ($subtitle)
                    <p class="mt-0.5 text-xs text-muted">{{ $subtitle }}</p>
                @endif
            </div>
            @if ($actions)
                <div class="flex shrink-0 items-center gap-2">{{ $actions }}</div>
            @endif
        </header>
    @endif

    <div class="{{ $hasHeader && ! $flush ? ($padding === 'none' ? '' : 'px-4 py-4 sm:px-6') : $pad }}">
        {{ $slot }}
    </div>
</section>
