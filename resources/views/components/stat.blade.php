@props([
    'label' => '',
    'value' => null,
    'hint' => null,
    'tone' => 'neutral',
    'icon' => null,
    'href' => null,
    'delta' => null,
    'deltaTone' => 'neutral',
])

@php
    $rails = [
        'neutral' => 'text-line-strong',
        'accent' => 'text-accent',
        'success' => 'text-success',
        'warn' => 'text-warn',
        'danger' => 'text-danger',
        'info' => 'text-info',
    ];

    $deltaTones = [
        'neutral' => 'text-muted',
        'success' => 'text-success',
        'warn' => 'text-warn',
        'danger' => 'text-danger',
    ];

    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }}
    @if ($href) href="{{ $href }}" wire:navigate @endif
    {{ $attributes->merge([
        'class' => 'group relative block overflow-hidden rounded-xl border border-line bg-surface px-4 py-3.5 shadow-xs transition-[border-color,box-shadow] duration-150 '
            .($href ? 'hover:border-line-strong hover:shadow-sm' : ''),
    ]) }}
>
    <span class="absolute inset-y-3 left-0 w-0.5 rounded-r-full bg-current {{ $rails[$tone] ?? $rails['neutral'] }}" aria-hidden="true"></span>

    <div class="flex items-start justify-between gap-2">
        <p class="font-mono text-eyebrow text-muted uppercase">{{ $label }}</p>
        @if ($icon)
            <x-icon :name="$icon" class="size-4 text-faint transition-colors group-hover:text-muted" />
        @endif
    </div>

    <p class="mt-2 font-mono text-figure font-medium text-ink tabular-nums">
        {{ $value ?? $slot }}
    </p>

    @if ($hint || $delta)
        <p class="mt-1 flex items-center gap-1.5 text-xs text-muted">
            @if ($delta)
                <span class="font-mono font-medium tabular-nums {{ $deltaTones[$deltaTone] ?? $deltaTones['neutral'] }}">{{ $delta }}</span>
            @endif
            {{ $hint }}
        </p>
    @endif
</{{ $tag }}>
