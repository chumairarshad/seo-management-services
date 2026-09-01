@props([
    'options' => [],
    'current' => null,
    'action' => null,
    'label' => null,
])

<div
    {{ $attributes->merge(['class' => 'inline-flex items-center gap-0.5 rounded-lg border border-line bg-subtle p-0.5']) }}
    role="group"
    @if ($label) aria-label="{{ $label }}" @endif
>
    @foreach ($options as $value => $optionLabel)
        @php $active = (string) $current === (string) $value; @endphp
        <button
            type="button"
            @if ($action) wire:click="{{ $action }}('{{ $value }}')" @endif
            aria-pressed="{{ $active ? 'true' : 'false' }}"
            class="inline-flex h-7 items-center rounded-[7px] px-2.5 text-xs font-medium transition-[background-color,color,box-shadow] duration-150 {{ $active ? 'bg-surface text-ink shadow-xs' : 'text-muted hover:text-ink' }}"
        >
            {{ $optionLabel }}
        </button>
    @endforeach
</div>
