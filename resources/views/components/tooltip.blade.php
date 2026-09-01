@props([
    'text' => '',
    'placement' => 'top',
])

@php
    $positions = [
        'top' => 'bottom-full left-1/2 mb-1.5 -translate-x-1/2',
        'bottom' => 'top-full left-1/2 mt-1.5 -translate-x-1/2',
        'right' => 'left-full top-1/2 ml-1.5 -translate-y-1/2',
        'left' => 'right-full top-1/2 mr-1.5 -translate-y-1/2',
    ];
@endphp

<span
    {{ $attributes->merge(['class' => 'group/tip relative inline-flex']) }}
    x-data="{ shown: false }"
    x-on:mouseenter="shown = true"
    x-on:mouseleave="shown = false"
    x-on:focusin="shown = true"
    x-on:focusout="shown = false"
>
    {{ $slot }}

    <span
        x-cloak
        x-show="shown"
        x-transition.opacity.duration.120ms
        role="tooltip"
        class="pointer-events-none absolute z-50 rounded-md border border-line-strong bg-raised px-2 py-1 text-[11px] whitespace-nowrap text-ink shadow-md {{ $positions[$placement] ?? $positions['top'] }}"
    >{{ $text }}</span>
</span>
