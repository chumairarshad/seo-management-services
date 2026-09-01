@props([
    'name' => '',
    'size' => 'md',
])

@php
    $initials = collect(preg_split('/\s+/', trim($name)))
        ->filter()
        ->take(2)
        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');

    // Deterministic tint per person so faces stay recognisable across screens.
    $tints = [
        'bg-accent-soft text-accent',
        'bg-info-soft text-info',
        'bg-success-soft text-success',
        'bg-warn-soft text-warn',
        'bg-danger-soft text-danger',
        'bg-subtle text-ink-soft',
    ];
    $tint = $tints[crc32(mb_strtolower($name)) % count($tints)];

    $sizes = [
        'xs' => 'size-5 text-[9px]',
        'sm' => 'size-6 text-[10px]',
        'md' => 'size-8 text-[11px]',
        'lg' => 'size-10 text-sm',
    ];
@endphp

<span
    {{ $attributes->merge([
        'class' => 'inline-flex shrink-0 items-center justify-center rounded-full font-mono font-medium select-none '
            .$tint.' '.($sizes[$size] ?? $sizes['md']),
    ]) }}
    title="{{ $name }}"
    aria-hidden="true"
>{{ $initials ?: '—' }}</span>
