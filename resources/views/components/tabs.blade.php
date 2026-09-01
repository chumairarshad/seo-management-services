@props([
    'tabs' => [],
    'current' => null,
    'action' => null,
])

{{--
    Tabs: array of ['key' => .., 'label' => .., 'href' => .., 'count' => ..].
    Pass `action` for a Livewire method (wire:click="action('key')"), or `href`
    per tab for navigation tabs.
--}}
<div {{ $attributes->merge(['class' => 'scroll-none -mb-px flex items-center gap-1 overflow-x-auto border-b border-line']) }} role="tablist">
    @foreach ($tabs as $tab)
        @php
            $key = $tab['key'] ?? $tab['label'];
            $active = $current === $key;
            $classes = 'relative -mb-px inline-flex shrink-0 items-center gap-1.5 border-b-2 px-3 py-2.5 text-sm font-medium transition-colors duration-150 '
                .($active
                    ? 'border-accent-solid text-ink'
                    : 'border-transparent text-muted hover:border-line-strong hover:text-ink');
        @endphp

        @if ($tab['href'] ?? null)
            <a href="{{ $tab['href'] }}" wire:navigate role="tab" aria-selected="{{ $active ? 'true' : 'false' }}" class="{{ $classes }}">
                {{ $tab['label'] }}
                @isset($tab['count'])
                    <span class="rounded-md bg-subtle px-1.5 font-mono text-[10px] text-muted tabular-nums">{{ $tab['count'] }}</span>
                @endisset
            </a>
        @else
            <button
                type="button"
                role="tab"
                aria-selected="{{ $active ? 'true' : 'false' }}"
                @if ($action) wire:click="{{ $action }}('{{ $key }}')" @endif
                class="{{ $classes }}"
            >
                {{ $tab['label'] }}
                @isset($tab['count'])
                    <span class="rounded-md bg-subtle px-1.5 font-mono text-[10px] text-muted tabular-nums">{{ $tab['count'] }}</span>
                @endisset
            </button>
        @endif
    @endforeach
</div>
