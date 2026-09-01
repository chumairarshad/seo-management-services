@props([
    'label' => null,
    'error' => null,
    'hint' => null,
    'filename' => null,
])

@php
    $wireModel = collect($attributes->getAttributes())
        ->first(fn ($value, $key) => str_starts_with($key, 'wire:model'));

    $id = $attributes->get('id')
        ?? str_replace(['.', '[', ']'], ['-', '-', ''], (string) ($wireModel ?? 'file-input'));
@endphp

<div {{ $attributes->only('class')->merge(['class' => 'space-y-1.5']) }}>
    @if ($label)
        <span class="block text-xs font-medium text-ink-soft">{{ $label }}</span>
    @endif

    <label
        for="{{ $id }}"
        class="group flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-line bg-subtle/50 px-4 py-6 text-center transition-colors duration-150 hover:border-accent-line hover:bg-accent-soft/40 has-[:focus-visible]:border-accent-solid has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-accent-solid/20"
    >
        <input
            type="file"
            id="{{ $id }}"
            {{ $attributes->except('class')->merge(['class' => 'sr-only']) }}
        >

        <span class="inline-flex items-center gap-1.5 rounded-lg border border-line bg-surface px-2.5 py-1.5 text-xs font-medium text-ink shadow-xs transition-colors group-hover:border-accent-line group-hover:text-accent">
            <x-icon name="upload" class="size-3.5" />
            Choose file
        </span>

        @if ($wireModel)
            <span wire:loading wire:target="{{ $wireModel }}" class="inline-flex items-center gap-1.5 text-sm text-muted">
                <x-spinner class="size-3.5" />
                Preparing file…
            </span>
            <span wire:loading.remove wire:target="{{ $wireModel }}" class="max-w-full truncate px-2 text-sm {{ $filename ? 'font-medium text-ink' : 'text-muted' }}">
                {{ $filename ?: 'No file chosen' }}
            </span>
        @else
            <span class="max-w-full truncate px-2 text-sm {{ $filename ? 'font-medium text-ink' : 'text-muted' }}">
                {{ $filename ?: 'No file chosen' }}
            </span>
        @endif

        @if ($hint)
            <span class="text-xs text-faint">{{ $hint }}</span>
        @endif
    </label>

    @if ($error)
        <p class="flex items-center gap-1 text-xs text-danger">
            <x-icon name="alert" class="size-3.5" />
            {{ $error }}
        </p>
    @endif
</div>
