@props([
    'title' => '',
    'subtitle' => null,
    'eyebrow' => null,
    'breadcrumbs' => [],
    'back' => null,
])

@php
    $trail = collect($breadcrumbs)->map(fn ($crumb) => is_array($crumb) ? $crumb : ['label' => $crumb, 'href' => null]);
@endphp

<header {{ $attributes->merge(['class' => 'mb-6 flex flex-col gap-4 sm:mb-8 sm:flex-row sm:items-start sm:justify-between']) }}>
    <div class="min-w-0">
        @if ($trail->isNotEmpty() || $eyebrow)
            <nav aria-label="Breadcrumb" class="mb-1.5 flex items-center gap-1.5 font-mono text-eyebrow text-muted uppercase">
                @if ($trail->isEmpty())
                    <span>{{ $eyebrow }}</span>
                @else
                    @foreach ($trail as $index => $crumb)
                        @if ($crumb['href'] ?? null)
                            <a href="{{ $crumb['href'] }}" wire:navigate class="rounded transition-colors hover:text-ink">{{ $crumb['label'] }}</a>
                        @else
                            <span @if ($loop->last) aria-current="page" @endif>{{ $crumb['label'] }}</span>
                        @endif

                        @unless ($loop->last)
                            <span class="text-faint" aria-hidden="true">/</span>
                        @endunless
                    @endforeach
                @endif
            </nav>
        @endif

        <div class="flex items-center gap-2">
            @if ($back)
                <a
                    href="{{ $back }}"
                    wire:navigate
                    class="-ml-1 flex size-7 items-center justify-center rounded-lg text-muted transition-colors hover:bg-subtle hover:text-ink"
                    aria-label="Back"
                >
                    <x-icon name="chevron-left" class="size-4" />
                </a>
            @endif

            <h1 class="font-display text-display font-semibold text-ink">{{ $title }}</h1>
        </div>

        @if ($subtitle)
            <p class="mt-1.5 max-w-2xl text-sm text-muted">{{ $subtitle }}</p>
        @endif

        @if (isset($meta))
            <div class="mt-3 flex flex-wrap items-center gap-2">{{ $meta }}</div>
        @endif
    </div>

    @if (isset($actions))
        <div class="flex shrink-0 flex-wrap items-center gap-2">
            {{ $actions }}
        </div>
    @endif
</header>
