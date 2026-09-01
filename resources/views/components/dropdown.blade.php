@props([
    'align' => 'right',
    'width' => 'w-56',
    'label' => 'Open menu',
    'placement' => 'bottom',
])

@php
    $alignment = $align === 'left' ? 'left-0' : 'right-0';
    $vertical = $placement === 'top' ? 'bottom-full mb-1.5 origin-bottom' : 'top-full mt-1.5 origin-top';
@endphp

<div
    {{ $attributes->merge(['class' => 'relative']) }}
    x-data="{ open: false }"
    x-on:keydown.escape.stop="open = false; $refs.trigger?.focus()"
    x-on:click.outside="open = false"
>
    <button
        type="button"
        x-ref="trigger"
        x-on:click="open = ! open"
        x-on:keydown.down.prevent="open = true; $nextTick(() => $refs.menu?.querySelector('[role=menuitem]')?.focus())"
        :aria-expanded="open ? 'true' : 'false'"
        aria-haspopup="menu"
        aria-label="{{ $label }}"
        class="contents"
    >
        {{ $trigger }}
    </button>

    <div
        x-cloak
        x-show="open"
        x-ref="menu"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        x-on:keydown.down.prevent="$focus.wrap().next()"
        x-on:keydown.up.prevent="$focus.wrap().previous()"
        x-on:click="open = false"
        role="menu"
        class="absolute z-30 {{ $vertical }} {{ $alignment }} {{ $width }} overflow-hidden rounded-xl border border-line bg-raised p-1 shadow-lg"
    >
        {{ $slot }}
    </div>
</div>
