@props([
    'numeric' => false,
    'align' => null,
    'muted' => false,
    'mono' => false,
    'tight' => false,
    'nowrap' => false,
])

@php
    $alignment = $align ?? ($numeric ? 'right' : 'left');

    $classes = 'row-y align-middle '
        .($tight ? 'px-3 ' : 'px-4 ')
        .($alignment === 'right' ? 'text-right ' : ($alignment === 'center' ? 'text-center ' : ''))
        .($numeric || $mono ? 'font-mono text-xs tabular-nums ' : '')
        .($muted ? 'text-muted ' : 'text-ink-soft ')
        .($nowrap ? 'whitespace-nowrap' : '');
@endphp

<td {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</td>
