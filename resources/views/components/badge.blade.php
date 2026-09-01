@props([
    'tone' => 'neutral',
    'dot' => false,
    'size' => 'md',
    'icon' => null,
])

@php
    $tones = [
        'neutral' => 'bg-subtle text-muted ring-line',
        'accent' => 'bg-accent-soft text-accent ring-accent-line',
        'success' => 'bg-success-soft text-success ring-success-line',
        'danger' => 'bg-danger-soft text-danger ring-danger-line',
        'warn' => 'bg-warn-soft text-warn ring-warn-line',
        'info' => 'bg-info-soft text-info ring-info-line',
    ];

    $sizes = [
        'sm' => 'gap-1 px-1.5 py-px text-[10px]',
        'md' => 'gap-1.5 px-2 py-0.5 text-[11px]',
    ];
@endphp

<span {{ $attributes->merge([
    'class' => 'inline-flex items-center rounded-md font-medium whitespace-nowrap ring-1 ring-inset '
        .($tones[$tone] ?? $tones['neutral']).' '.($sizes[$size] ?? $sizes['md']),
]) }}>
    @if ($dot)
        <span class="size-1.5 shrink-0 rounded-full bg-current" aria-hidden="true"></span>
    @elseif ($icon)
        <x-icon :name="$icon" class="size-3" />
    @endif
    {{ $slot }}
</span>
