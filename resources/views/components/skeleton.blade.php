@props([
    'lines' => 4,
    'variant' => 'text',
    'rows' => 5,
    'cols' => 4,
])

@if ($variant === 'table')
    <div {{ $attributes->merge(['class' => 'overflow-hidden rounded-xl border border-line bg-surface']) }} aria-hidden="true">
        <div class="h-9 border-b border-line bg-subtle"></div>
        @for ($row = 0; $row < $rows; $row++)
            <div class="flex items-center gap-4 border-b border-line px-4 py-3 last:border-b-0">
                @for ($col = 0; $col < $cols; $col++)
                    <div class="skeleton h-3 rounded" style="width: {{ [38, 22, 16, 12, 10][$col] ?? 12 }}%"></div>
                @endfor
            </div>
        @endfor
    </div>
@elseif ($variant === 'cards')
    <div {{ $attributes->merge(['class' => 'grid gap-4 sm:grid-cols-2 xl:grid-cols-4']) }} aria-hidden="true">
        @for ($i = 0; $i < $rows; $i++)
            <div class="rounded-xl border border-line bg-surface p-4">
                <div class="skeleton h-2.5 w-20 rounded"></div>
                <div class="skeleton mt-4 h-6 w-24 rounded"></div>
            </div>
        @endfor
    </div>
@else
    <div {{ $attributes->merge(['class' => 'space-y-2.5']) }} aria-hidden="true">
        @for ($i = 0; $i < $lines; $i++)
            <div class="skeleton h-3 rounded" style="width: {{ 100 - ($i * 12) }}%"></div>
        @endfor
    </div>
@endif
