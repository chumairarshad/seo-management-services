@props([
    'label' => null,
    'hint' => null,
])

@php
    $wireModel = collect($attributes->getAttributes())
        ->first(fn ($value, $key) => str_starts_with($key, 'wire:model'));

    $id = $attributes->get('id')
        ?? ($wireModel ? 'c-'.str_replace(['.', '[', ']'], ['-', '-', ''], (string) $wireModel).'-'.($attributes->get('value') ?? '') : null);
@endphp

<label {{ $attributes->only('class')->merge(['class' => 'group inline-flex cursor-pointer items-start gap-2 text-sm text-ink-soft select-none']) }}>
    <input
        type="checkbox"
        @if ($id) id="{{ $id }}" @endif
        {{ $attributes->except('class')->merge([
            'class' => 'mt-0.5 size-4 shrink-0 cursor-pointer rounded-[5px] border-line-strong accent-accent-solid',
        ]) }}
    >

    @if ($label || $slot->isNotEmpty())
        <span class="leading-5">
            <span class="transition-colors group-hover:text-ink">{{ $label ?? $slot }}</span>
            @if ($hint)
                <span class="block text-xs text-muted">{{ $hint }}</span>
            @endif
        </span>
    @endif
</label>
