@props([
    'target' => null,
])

{{--
    One filter row for every index page: search first, narrowing controls next,
    trailing slot for counts or a clear action. Busies only itself.
--}}
<div {{ $attributes->merge(['class' => 'relative flex flex-wrap items-center gap-2 rounded-xl border border-line bg-surface px-3 py-2.5 shadow-xs']) }}>
    {{ $slot }}

    @if (isset($trailing))
        <div class="ml-auto flex items-center gap-2 text-xs text-muted">
            {{ $trailing }}
        </div>
    @endif

    @if ($target)
        <span
            wire:loading.delay.shortest
            wire:target="{{ $target }}"
            class="pointer-events-none absolute -top-2 right-3 inline-flex items-center gap-1.5 rounded-full border border-line bg-raised px-2 py-0.5 text-[10px] text-muted shadow-sm"
        >
            <x-spinner class="size-2.5" />
            Filtering
        </span>
    @endif
</div>
