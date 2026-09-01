@props([
    'title' => 'Nothing here yet',
    'description' => null,
    'icon' => 'inbox',
    'compact' => false,
])

<div {{ $attributes->merge([
    'class' => 'flex flex-col items-center rounded-xl border border-dashed border-line bg-surface/50 text-center '
        .($compact ? 'px-6 py-8' : 'px-6 py-14'),
]) }}>
    @if ($icon)
        <span class="mb-3 flex size-10 items-center justify-center rounded-xl border border-line bg-subtle text-muted">
            <x-icon :name="$icon" class="size-5" />
        </span>
    @endif

    <h3 class="font-display text-base font-semibold tracking-tight text-ink">{{ $title }}</h3>

    @if ($description)
        <p class="mt-1 max-w-sm text-sm text-balance text-muted">{{ $description }}</p>
    @endif

    @if ($slot->isNotEmpty())
        <div class="mt-5 flex flex-wrap items-center justify-center gap-2">
            {{ $slot }}
        </div>
    @endif
</div>
