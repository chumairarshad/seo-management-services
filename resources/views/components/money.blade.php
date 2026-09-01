@props([
    'paisa' => 0,
    'currency' => null,
    'signed' => false,
    'tone' => true,
    'size' => 'sm',
])

@php
    $amount = (int) $paisa;
    $formatted = \App\Support\Money::formatted(abs($amount), '');
    $prefix = $amount < 0 ? '−' : ($signed && $amount > 0 ? '+' : '');

    $colour = ! $tone ? 'text-ink-soft' : match (true) {
        $amount < 0 => 'text-danger',
        $signed && $amount > 0 => 'text-success',
        default => 'text-ink-soft',
    };

    $sizes = ['xs' => 'text-[11px]', 'sm' => 'text-xs', 'md' => 'text-sm', 'lg' => 'text-figure'];
@endphp

<span {{ $attributes->merge(['class' => 'font-mono tabular-nums whitespace-nowrap '.$colour.' '.($sizes[$size] ?? $sizes['sm'])]) }}>
    {{ $prefix }}{{ $formatted }}@if ($currency)<span class="ml-1 text-faint">{{ $currency }}</span>@endif
</span>
