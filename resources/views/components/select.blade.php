@props([
    'label' => null,
    'error' => null,
    'hint' => null,
    'size' => 'md',
    'placeholder' => null,
    'options' => null,
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

    $control = 'block w-full appearance-none rounded-lg border bg-surface pr-9 pl-3 text-ink shadow-xs transition-[border-color,box-shadow] duration-150 focus:outline-none focus:ring-2 disabled:cursor-not-allowed disabled:bg-subtle disabled:text-muted '
        .($heights[$size] ?? $heights['md']).' '
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
        <select
            @if ($id) id="{{ $id }}" @endif
            @if ($error) aria-invalid="true" @endif
            {{ $attributes->except('class')->merge(['class' => $control]) }}
        >
            @if ($placeholder !== null)
                <option value="">{{ $placeholder }}</option>
            @endif

            @if (is_array($options))
                @foreach ($options as $value => $optionLabel)
                    <option value="{{ $value }}">{{ $optionLabel }}</option>
                @endforeach
            @endif

            {{ $slot }}
        </select>

        <x-icon name="chevron-down" class="pointer-events-none absolute top-1/2 right-2.5 size-4 -translate-y-1/2 text-faint" />
    </div>

    @if ($hint && ! $error)
        <p class="text-xs text-muted">{{ $hint }}</p>
    @endif

    @if ($error)
        <p class="flex items-center gap-1 text-xs text-danger">
            <x-icon name="alert" class="size-3.5" />
            {{ $error }}
        </p>
    @endif
</div>
