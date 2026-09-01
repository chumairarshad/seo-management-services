@props([
    'label' => null,
    'type' => 'text',
    'error' => null,
    'hint' => null,
    'icon' => null,
    'size' => 'md',
    'suffix' => null,
    'required' => false,
])

@php
    $wireModel = collect($attributes->getAttributes())
        ->first(fn ($value, $key) => str_starts_with($key, 'wire:model'));

    $id = $attributes->get('id')
        ?? $attributes->get('name')
        ?? ($wireModel ? 'f-'.str_replace(['.', '[', ']'], ['-', '-', ''], (string) $wireModel) : null);

    $heights = [
        'sm' => 'h-8 text-xs',
        'md' => 'h-10 text-sm sm:h-9',
        'lg' => 'h-11 text-sm',
    ];

    $control = 'block w-full rounded-lg border bg-surface text-ink shadow-xs transition-[border-color,box-shadow] duration-150 placeholder:text-faint focus:outline-none focus:ring-2 disabled:cursor-not-allowed disabled:bg-subtle disabled:text-muted '
        .($heights[$size] ?? $heights['md']).' '
        .($icon ? 'pl-9 pr-3' : 'px-3').' '
        .($suffix ? 'pr-12' : '').' '
        .($error
            ? 'border-danger focus:border-danger focus:ring-danger/25'
            : 'border-line hover:border-line-strong focus:border-accent-solid focus:ring-accent-solid/20');
@endphp

<div {{ $attributes->only('class')->merge(['class' => 'space-y-1.5']) }}>
    @if ($label)
        <label @if ($id) for="{{ $id }}" @endif class="flex items-center gap-1 text-xs font-medium text-ink-soft">
            {{ $label }}
            @if ($required)
                <span class="text-danger" aria-hidden="true">*</span>
            @endif
        </label>
    @endif

    <div class="relative">
        @if ($icon)
            <x-icon :name="$icon" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-faint" />
        @endif

        <input
            type="{{ $type }}"
            @if ($id) id="{{ $id }}" @endif
            @if ($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
            {{ $attributes->except(['class', 'type'])->merge(['class' => $control]) }}
        >

        @if ($suffix)
            <span class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 font-mono text-xs text-faint">{{ $suffix }}</span>
        @endif
    </div>

    @if ($hint && ! $error)
        <p class="text-xs text-muted">{{ $hint }}</p>
    @endif

    @if ($error)
        <p id="{{ $id }}-error" class="flex items-center gap-1 text-xs text-danger">
            <x-icon name="alert" class="size-3.5" />
            {{ $error }}
        </p>
    @endif
</div>
