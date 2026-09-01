@props([
    'value' => 0,
    'max' => 100,
    'tone' => 'accent',
    'label' => null,
    'caption' => null,
])

@php
    $max = max(1, (float) $max);
    $pct = max(0, min(100, ((float) $value / $max) * 100));

    $tones = [
        'accent' => 'bg-accent-solid',
        'success' => 'bg-success',
        'warn' => 'bg-warn',
        'danger' => 'bg-danger',
        'neutral' => 'bg-line-strong',
    ];
@endphp

<div {{ $attributes->merge(['class' => 'space-y-1.5']) }}>
    @if ($label || $caption)
        <div class="flex items-baseline justify-between gap-2 text-xs">
            <span class="text-muted">{{ $label }}</span>
            <span class="font-mono text-ink-soft tabular-nums">{{ $caption }}</span>
        </div>
    @endif

    <div
        class="h-1.5 w-full overflow-hidden rounded-full bg-subtle"
        role="progressbar"
        aria-valuenow="{{ (int) $value }}"
        aria-valuemin="0"
        aria-valuemax="{{ (int) $max }}"
        @if ($label) aria-label="{{ $label }}" @endif
    >
        <div class="h-full rounded-full transition-[width] duration-300 ease-out {{ $tones[$tone] ?? $tones['accent'] }}" style="width: {{ $pct }}%"></div>
    </div>
</div>
