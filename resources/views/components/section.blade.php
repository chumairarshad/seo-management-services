@props([
    'title' => null,
    'description' => null,
])

<div {{ $attributes->merge(['class' => 'space-y-3']) }}>
    @if ($title || $description || isset($actions))
        <div class="flex flex-wrap items-end justify-between gap-2">
            <div>
                @if ($title)
                    <h2 class="font-mono text-eyebrow text-muted uppercase">{{ $title }}</h2>
                @endif
                @if ($description)
                    <p class="mt-1 text-sm text-muted">{{ $description }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="flex items-center gap-2">{{ $actions }}</div>
            @endisset
        </div>
    @endif

    {{ $slot }}
</div>
