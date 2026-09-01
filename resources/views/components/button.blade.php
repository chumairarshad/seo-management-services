@props([
    'type' => 'button',
    'variant' => 'primary',
    'size' => 'md',
    'icon' => null,
    'iconRight' => null,
    'target' => null,
    'href' => null,
    'square' => false,
])

@php
    $base = 'group/btn relative inline-flex select-none items-center justify-center gap-1.5 rounded-lg font-medium whitespace-nowrap transition-[background-color,border-color,color,box-shadow] duration-150 ease-out disabled:pointer-events-none disabled:opacity-45';

    $variants = [
        'primary' => 'bg-accent-solid text-accent-fg shadow-xs hover:bg-accent-hover active:bg-accent-hover',
        'secondary' => 'border border-line bg-surface text-ink shadow-xs hover:border-line-strong hover:bg-subtle active:bg-subtle',
        'ghost' => 'text-muted hover:bg-subtle hover:text-ink active:bg-subtle',
        'subtle' => 'bg-subtle text-ink hover:bg-line active:bg-line',
        'danger' => 'bg-danger text-white shadow-xs hover:brightness-110 active:brightness-95 dark:text-[#2b0f10]',
        'danger-soft' => 'border border-danger-line bg-danger-soft text-danger hover:brightness-[0.97] active:brightness-95',
        'danger-ghost' => 'text-danger hover:bg-danger-soft active:bg-danger-soft',
        'link' => 'text-accent underline-offset-4 hover:underline',
    ];

    $sizes = [
        'xs' => $square ? 'size-7' : 'h-7 px-2 text-xs',
        'sm' => $square ? 'size-8' : 'h-8 px-2.5 text-xs',
        'md' => $square ? 'size-10 sm:size-9' : 'h-10 px-3.5 text-sm sm:h-9',
        'lg' => $square ? 'size-11' : 'h-11 px-5 text-sm',
    ];

    $classes = $base.' '.($variants[$variant] ?? $variants['primary']).' '.($sizes[$size] ?? $sizes['md']);

    // Any wire:click doubles as its own loading target unless one is given.
    $loading = $target ?? $attributes->get('wire:click') ?? $attributes->get('wire:click.prevent');
    $iconSize = in_array($size, ['xs', 'sm'], true) ? 'size-3.5' : 'size-4';
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)
            <x-icon :name="$icon" class="{{ $iconSize }}" />
        @endif
        {{ $slot }}
        @if ($iconRight)
            <x-icon :name="$iconRight" class="{{ $iconSize }} opacity-70" />
        @endif
    </a>
@else
    <button
        type="{{ $type }}"
        @if ($loading)
            wire:target="{{ $loading }}"
            wire:loading.attr="disabled"
        @endif
        {{ $attributes->merge(['class' => $classes]) }}
    >
        @if ($loading)
            <span wire:loading wire:target="{{ $loading }}" class="contents">
                <x-spinner class="{{ $iconSize }}" />
            </span>
            @if ($icon)
                <span wire:loading.remove wire:target="{{ $loading }}" class="contents">
                    <x-icon :name="$icon" class="{{ $iconSize }}" />
                </span>
            @endif
        @elseif ($icon)
            <x-icon :name="$icon" class="{{ $iconSize }}" />
        @endif

        {{ $slot }}

        @if ($iconRight)
            <x-icon :name="$iconRight" class="{{ $iconSize }} opacity-70" />
        @endif
    </button>
@endif
