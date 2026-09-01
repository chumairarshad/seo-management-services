@props([
    'headers' => [],
    'sort' => null,
    'direction' => 'asc',
    'sortAction' => 'sortBy',
    'sticky' => false,
    'flush' => false,
])

@php
    // A header is either a plain label or ['label' => ..., 'align' => right|center,
    // 'sort' => 'column', 'width' => 'w-10', 'sr' => true].
    $columns = collect($headers)->map(function ($header) {
        $column = is_array($header) ? $header : ['label' => $header];

        return array_merge(['label' => '', 'align' => 'left', 'sort' => null, 'width' => null, 'sr' => false], $column);
    });
@endphp

{{-- min-w-0 keeps a wide table from stretching its grid/flex parent on narrow screens. --}}
<div {{ $attributes->merge(['class' => ($flush ? '' : 'rounded-xl border border-line bg-surface shadow-xs ').'min-w-0 overflow-hidden']) }}>
    <div class="min-w-0 {{ $sticky ? 'max-h-[68vh] overflow-auto' : 'overflow-x-auto' }}">
        <table class="min-w-full border-separate border-spacing-0 text-left text-sm">
            @if ($columns->isNotEmpty())
                <thead class="{{ $sticky ? 'sticky top-0 z-20' : '' }}">
                    <tr>
                        @foreach ($columns as $column)
                            <th
                                scope="col"
                                {{-- relative: an sr-only label is absolutely positioned, and without a
                                     positioned cell it anchors to the page and widens the document. --}}
                                class="relative border-b border-line bg-subtle px-4 py-2.5 font-mono text-[10px] font-medium tracking-[0.1em] whitespace-nowrap text-muted uppercase {{ $column['width'] ?? '' }} {{ $column['align'] === 'right' ? 'text-right' : ($column['align'] === 'center' ? 'text-center' : '') }}"
                            >
                                @if ($column['sr'])
                                    <span class="sr-only">{{ $column['label'] }}</span>
                                @elseif ($column['sort'])
                                    <button
                                        type="button"
                                        wire:click="{{ $sortAction }}('{{ $column['sort'] }}')"
                                        class="group/sort inline-flex items-center gap-1 rounded transition-colors hover:text-ink {{ $column['align'] === 'right' ? 'flex-row-reverse' : '' }}"
                                        aria-label="Sort by {{ $column['label'] }}"
                                    >
                                        {{ $column['label'] }}
                                        @if ($sort === $column['sort'])
                                            <x-icon :name="$direction === 'asc' ? 'arrow-up' : 'arrow-down'" class="size-3 text-accent" />
                                        @else
                                            <x-icon name="sort" class="size-3 opacity-0 transition-opacity group-hover/sort:opacity-60" />
                                        @endif
                                    </button>
                                @else
                                    {{ $column['label'] }}
                                @endif
                            </th>
                        @endforeach
                    </tr>
                </thead>
            @endif

            {{-- Row rules live on the cells so sticky headers and border-separate stay honest. --}}
            <tbody class="[&>tr:last-child>td]:border-b-0 [&>tr:hover>td]:bg-subtle/60 [&>tr>td]:border-b [&>tr>td]:border-line [&>tr>td]:transition-colors">
                {{ $slot }}
            </tbody>
        </table>
    </div>

    @if (isset($footer))
        <div class="border-t border-line bg-subtle/60 px-4 py-2.5 text-xs text-muted">
            {{ $footer }}
        </div>
    @endif
</div>
