@props([
    'icon' => null,
    'href' => null,
    'tone' => 'default',
    'shortcut' => null,
    'type' => 'button',
])

@php
    $classes = 'flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-left text-sm transition-colors duration-100 '
        .($tone === 'danger'
            ? 'text-danger hover:bg-danger-soft'
            : 'text-ink-soft hover:bg-subtle hover:text-ink');
@endphp

@if ($href)
    <a href="{{ $href }}" role="menuitem" wire:navigate {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)
            <x-icon :name="$icon" class="size-4 text-muted" />
        @endif
        <span class="flex-1 truncate">{{ $slot }}</span>
        @if ($shortcut)
            <x-kbd>{{ $shortcut }}</x-kbd>
        @endif
    </a>
@else
    <button type="{{ $type }}" role="menuitem" {{ $attributes->except('type')->merge(['class' => $classes]) }}>
        @if ($icon)
            <x-icon :name="$icon" class="size-4 text-muted" />
        @endif
        <span class="flex-1 truncate">{{ $slot }}</span>
        @if ($shortcut)
            <x-kbd>{{ $shortcut }}</x-kbd>
        @endif
    </button>
@endif
