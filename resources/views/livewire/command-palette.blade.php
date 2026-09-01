<div
    x-data="osPalette()"
    x-init="
        quickLabels = @js(collect($quickCreate)->pluck('label')->all());
        navLabels = @js(collect($commands)->map(fn ($command) => $command['label'].' '.$command['group'])->all());
    "
    x-on:palette:open.window="toggle()"
    x-on:keydown.escape.window="close()"
>
    <div x-cloak x-show="open" class="fixed inset-0 z-50 flex items-start justify-center px-4 pt-[12vh]" role="dialog" aria-modal="true" aria-label="Command palette">
        <div
            x-show="open"
            x-transition.opacity.duration.120ms
            class="absolute inset-0 bg-ink/40 backdrop-blur-[3px] dark:bg-black/65"
            x-on:click="close()"
            aria-hidden="true"
        ></div>

        <div
            x-show="open"
            x-trap.noscroll="open"
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 -translate-y-2 scale-[0.99]"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            class="relative flex max-h-[70svh] w-full max-w-[36rem] flex-col overflow-hidden rounded-2xl border border-line-strong bg-raised shadow-pop"
        >
            <div class="flex items-center gap-2.5 border-b border-line px-4">
                <x-icon name="search" class="size-4 shrink-0 text-faint" />

                <input
                    x-ref="input"
                    type="text"
                    wire:model.live.debounce.200ms="q"
                    x-on:input="query = $event.target.value; reset()"
                    x-on:keydown.down.prevent="move(1)"
                    x-on:keydown.up.prevent="move(-1)"
                    x-on:keydown.enter.prevent="choose()"
                    class="h-13 min-w-0 flex-1 border-0 bg-transparent py-4 text-sm text-ink placeholder:text-faint focus:outline-none"
                    placeholder="Search projects, tasks, articles, people…"
                    autocomplete="off"
                    spellcheck="false"
                    aria-label="Search or run a command"
                >

                <span wire:loading wire:target="q" class="shrink-0">
                    <x-spinner class="size-3.5 text-faint" />
                </span>

                <button
                    type="button"
                    x-on:click="close()"
                    class="shrink-0 rounded-md border border-line px-1.5 py-0.5 font-mono text-[10px] text-muted transition-colors hover:bg-subtle"
                >ESC</button>
            </div>

            <div x-ref="results" class="min-h-0 flex-1 overflow-y-auto p-2">
                @foreach ($results as $group)
                    <p class="px-2 pt-2 pb-1 font-mono text-[10px] tracking-[0.13em] text-faint uppercase">{{ $group['group'] }}</p>
                    @foreach ($group['rows'] as $row)
                        <a
                            href="{{ $row['href'] }}"
                            wire:navigate
                            data-palette-item
                            x-on:click="close()"
                            class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm text-ink-soft transition-colors hover:bg-subtle aria-selected:bg-subtle aria-selected:text-ink"
                        >
                            <x-icon :name="$group['icon']" class="size-4 shrink-0 text-faint" />
                            <span class="min-w-0 flex-1 truncate">{{ $row['label'] }}</span>
                            @if ($row['meta'])
                                <span class="shrink-0 truncate font-mono text-[10px] text-faint">{{ $row['meta'] }}</span>
                            @endif
                        </a>
                    @endforeach
                @endforeach

                @if ($term !== '' && $results->isEmpty())
                    <div wire:loading.remove wire:target="q" class="px-3 py-6 text-center">
                        <p class="text-sm text-muted">No records match “{{ $term }}”.</p>
                        <p class="mt-1 text-xs text-faint">Try a domain, a task title, or a person’s name.</p>
                    </div>
                @endif

                @if ($quickCreate !== [])
                    <p class="px-2 pt-2 pb-1 font-mono text-[10px] tracking-[0.13em] text-faint uppercase" x-show="quickVisible()">Create</p>
                    @foreach ($quickCreate as $action)
                        <a
                            href="{{ $action['href'] }}"
                            wire:navigate
                            data-palette-item
                            x-show="matches(@js($action['label']))"
                            x-on:click="close()"
                            class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm text-ink-soft transition-colors hover:bg-subtle aria-selected:bg-subtle aria-selected:text-ink"
                        >
                            <x-icon name="plus" class="size-4 shrink-0 text-faint" />
                            <span class="min-w-0 flex-1 truncate">{{ $action['label'] }}</span>
                        </a>
                    @endforeach
                @endif

                <p class="px-2 pt-2 pb-1 font-mono text-[10px] tracking-[0.13em] text-faint uppercase" x-show="navVisible()">Jump to</p>
                @foreach ($commands as $command)
                    <a
                        href="{{ $command['href'] }}"
                        wire:navigate
                        data-palette-item
                        x-show="matches(@js($command['label'].' '.$command['group']))"
                        x-on:click="close()"
                        class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm text-ink-soft transition-colors hover:bg-subtle aria-selected:bg-subtle aria-selected:text-ink"
                    >
                        <x-icon :name="$command['icon']" class="size-4 shrink-0 text-faint" />
                        <span class="min-w-0 flex-1 truncate">{{ $command['label'] }}</span>
                        <span class="shrink-0 font-mono text-[10px] text-faint">{{ $command['group'] }}</span>
                    </a>
                @endforeach
            </div>

            <div class="flex items-center gap-4 border-t border-line bg-surface/60 px-4 py-2 text-[10px] text-faint">
                <span class="flex items-center gap-1"><x-kbd>↑</x-kbd><x-kbd>↓</x-kbd> move</span>
                <span class="flex items-center gap-1"><x-kbd>↵</x-kbd> open</span>
                <span class="ml-auto flex items-center gap-1"><x-kbd>?</x-kbd> shortcuts</span>
            </div>
        </div>
    </div>
</div>
