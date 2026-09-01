@props([
    'label' => null,
    'error' => null,
    'hint' => null,
    'rows' => 3,
    'required' => false,
])

@php
    $wireModel = collect($attributes->getAttributes())
        ->first(fn ($value, $key) => str_starts_with($key, 'wire:model'));

    $id = $attributes->get('id')
        ?? $attributes->get('name')
        ?? ($wireModel ? 'f-'.str_replace(['.', '[', ']'], ['-', '-', ''], (string) $wireModel) : null);

    $control = 'block w-full rounded-lg border bg-surface px-3 py-2 text-sm text-ink shadow-xs transition-[border-color,box-shadow] duration-150 placeholder:text-faint focus:outline-none focus:ring-2 disabled:cursor-not-allowed disabled:bg-subtle '
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

    <textarea
        rows="{{ $rows }}"
        @if ($id) id="{{ $id }}" @endif
        @if ($error) aria-invalid="true" @endif
        {{ $attributes->except(['class', 'rows'])->merge(['class' => $control]) }}
    >{{ $slot }}</textarea>

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
