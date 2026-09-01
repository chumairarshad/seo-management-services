{{-- Drawer: full navigation parity with the desktop rail. --}}
<div class="md:hidden">
    <div
        x-cloak
        x-show="drawer"
        class="fixed inset-0 z-40"
        x-on:keydown.escape.window="closeDrawer()"
        role="dialog"
        aria-modal="true"
        aria-label="Navigation"
    >
        <div
            x-show="drawer"
            x-transition.opacity.duration.150ms
            class="absolute inset-0 bg-ink/40 backdrop-blur-[2px] dark:bg-black/60"
            x-on:click="closeDrawer()"
            aria-hidden="true"
        ></div>

        <div
            x-show="drawer"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="-translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="-translate-x-full"
            x-trap.noscroll="drawer"
            class="relative flex h-svh w-[280px] max-w-[85vw] flex-col border-r border-line bg-surface shadow-pop"
        >
            <div class="flex h-14 items-center justify-between border-b border-line px-3">
                <div class="flex min-w-0 items-center gap-2.5">
                    <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-ink font-mono text-[13px] font-medium text-canvas">
                        {{ mb_strtoupper(mb_substr($orgName, 0, 1)) }}
                    </span>
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-semibold tracking-tight">{{ $orgName }}</span>
                        <span class="block font-mono text-[10px] tracking-[0.13em] text-faint uppercase">Portfolio OS</span>
                    </span>
                </div>
                <button
                    type="button"
                    x-on:click="closeDrawer()"
                    class="flex size-9 items-center justify-center rounded-lg text-muted hover:bg-subtle hover:text-ink"
                    aria-label="Close navigation"
                >
                    <x-icon name="x" class="size-5" />
                </button>
            </div>

            <nav class="flex-1 overflow-y-auto px-2.5 py-3" aria-label="Mobile">
                @foreach ($navGroups as $group)
                    <div class="{{ $loop->first ? '' : 'mt-4' }}">
                        @if ($group['label'])
                            <p class="mb-1 px-2 font-mono text-[10px] tracking-[0.13em] text-faint uppercase">{{ $group['label'] }}</p>
                        @endif
                        <ul class="space-y-0.5">
                            @foreach ($group['items'] as $item)
                                <li>
                                    <a
                                        href="{{ $item['href'] }}"
                                        wire:navigate
                                        x-on:click="closeDrawer()"
                                        @if ($item['active']) aria-current="page" @endif
                                        class="relative flex min-h-11 items-center gap-3 rounded-lg px-2 text-sm transition-colors
                                            {{ $item['active'] ? 'bg-subtle font-medium text-ink' : 'text-muted' }}"
                                    >
                                        @if ($item['active'])
                                            <span class="absolute inset-y-2 left-0 w-0.5 rounded-r-full bg-accent-solid" aria-hidden="true"></span>
                                        @endif
                                        <x-icon :name="$item['icon']" class="size-4 shrink-0 {{ $item['active'] ? 'text-accent' : 'text-faint' }}" />
                                        <span class="flex-1 truncate">{{ $item['label'] }}</span>
                                        @if (($item['badge'] ?? 0) > 0)
                                            <span class="rounded-md bg-accent-soft px-1.5 font-mono text-[10px] font-medium text-accent tabular-nums">{{ $item['badge'] }}</span>
                                        @endif
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </nav>

            <div class="border-t border-line px-3 py-3">
                <div class="flex items-center gap-2.5">
                    <x-avatar :name="auth()->user()->name" size="md" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium">{{ auth()->user()->name }}</p>
                        <p class="truncate font-mono text-[10px] text-faint">{{ auth()->user()->email }}</p>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="rounded-lg px-2 py-2 text-xs font-medium text-muted hover:bg-subtle hover:text-ink">Log out</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Thumb bar: the four screens staff actually live in. --}}
    @if ($bottomBar !== [])
        <nav
            class="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-surface/95 pb-[env(safe-area-inset-bottom)] backdrop-blur-md"
            aria-label="Quick navigation"
        >
            <ul class="flex items-stretch">
                @foreach ($bottomBar as $item)
                    <li class="flex-1">
                        <a
                            href="{{ $item['href'] }}"
                            wire:navigate
                            @if ($item['active']) aria-current="page" @endif
                            class="relative flex min-h-14 flex-col items-center justify-center gap-1 px-1 text-[10px] font-medium transition-colors
                                {{ $item['active'] ? 'text-accent' : 'text-muted' }}"
                        >
                            @if ($item['active'])
                                <span class="absolute inset-x-5 top-0 h-0.5 rounded-b-full bg-accent-solid" aria-hidden="true"></span>
                            @endif
                            <span class="relative">
                                <x-icon :name="$item['icon']" class="size-5" />
                                @if (($item['badge'] ?? 0) > 0)
                                    <span class="absolute -top-1 -right-1.5 min-w-3.5 rounded-full bg-danger px-1 font-mono text-[8px] leading-3.5 text-white tabular-nums">{{ $item['badge'] > 9 ? '9+' : $item['badge'] }}</span>
                                @endif
                            </span>
                            <span class="truncate">{{ $item['label'] }}</span>
                        </a>
                    </li>
                @endforeach
                <li class="flex-1">
                    <button
                        type="button"
                        x-on:click="openDrawer()"
                        class="flex min-h-14 w-full flex-col items-center justify-center gap-1 px-1 text-[10px] font-medium text-muted"
                    >
                        <x-icon name="menu" class="size-5" />
                        More
                    </button>
                </li>
            </ul>
        </nav>
    @endif
</div>
