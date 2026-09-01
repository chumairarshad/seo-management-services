@php
    $shortcutGroups = [
        'Anywhere' => [
            ['keys' => ['⌘', 'K'], 'label' => 'Open the command palette'],
            ['keys' => ['/'], 'label' => 'Focus search on this page'],
            ['keys' => ['?'], 'label' => 'Show this sheet'],
            ['keys' => ['Esc'], 'label' => 'Close a dialog or panel'],
        ],
        'Lists' => [
            ['keys' => ['j'], 'label' => 'Next row'],
            ['keys' => ['k'], 'label' => 'Previous row'],
            ['keys' => ['Enter'], 'label' => 'Open the focused row'],
        ],
        'Approval queue' => [
            ['keys' => ['a'], 'label' => 'Approve the current item'],
            ['keys' => ['r'], 'label' => 'Reject with a reason'],
            ['keys' => ['j', 'k'], 'label' => 'Move through the queue'],
        ],
        'Dialogs & forms' => [
            ['keys' => ['Enter'], 'label' => 'Submit the open form'],
            ['keys' => ['Esc'], 'label' => 'Discard and close'],
        ],
    ];
@endphp

<div
    x-data="{ open: false }"
    x-on:shortcuts:open.window="open = true"
    x-on:keydown.escape.window="open = false"
>
    <div x-cloak x-show="open" class="fixed inset-0 z-40 flex items-end justify-center sm:items-center" role="dialog" aria-modal="true" aria-label="Keyboard shortcuts">
        <div x-show="open" x-transition.opacity.duration.150ms class="absolute inset-0 bg-ink/40 backdrop-blur-[2px] dark:bg-black/60" x-on:click="open = false" aria-hidden="true"></div>

        <div
            x-show="open"
            x-trap.noscroll="open"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-3"
            x-transition:enter-end="opacity-100 translate-y-0"
            class="relative max-h-[88svh] w-full overflow-y-auto rounded-t-2xl border border-line bg-raised p-5 shadow-pop sm:max-w-2xl sm:rounded-2xl"
        >
            <div class="mb-5 flex items-start justify-between">
                <div>
                    <p class="font-mono text-eyebrow text-muted uppercase">Reference</p>
                    <h2 class="mt-1 font-display text-lg font-semibold tracking-tight">Keyboard shortcuts</h2>
                </div>
                <button type="button" x-on:click="open = false" class="flex size-8 items-center justify-center rounded-lg text-muted hover:bg-subtle hover:text-ink" aria-label="Close">
                    <x-icon name="x" class="size-4" />
                </button>
            </div>

            <div class="grid gap-x-8 gap-y-6 sm:grid-cols-2">
                @foreach ($shortcutGroups as $group => $rows)
                    <div>
                        <p class="mb-2 font-mono text-[10px] tracking-[0.13em] text-faint uppercase">{{ $group }}</p>
                        <ul class="divide-y divide-line">
                            @foreach ($rows as $row)
                                <li class="flex items-center justify-between gap-4 py-2 text-sm">
                                    <span class="text-ink-soft">{{ $row['label'] }}</span>
                                    <span class="flex shrink-0 items-center gap-1">
                                        @foreach ($row['keys'] as $key)
                                            <x-kbd>{{ $key }}</x-kbd>
                                        @endforeach
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
