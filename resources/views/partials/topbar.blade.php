<header class="sticky top-0 z-20 flex h-14 shrink-0 items-center gap-2 border-b border-line bg-canvas/85 px-3 backdrop-blur-md sm:px-4">
    {{-- Mobile: drawer trigger. Desktop: rail collapse. --}}
    <button
        type="button"
        x-on:click="openDrawer()"
        class="flex size-9 items-center justify-center rounded-lg text-muted transition-colors hover:bg-subtle hover:text-ink md:hidden"
        aria-label="Open navigation"
    >
        <x-icon name="menu" class="size-5" />
    </button>

    <button
        type="button"
        x-on:click="toggleSidebar()"
        class="hidden size-9 items-center justify-center rounded-lg text-muted transition-colors hover:bg-subtle hover:text-ink md:flex"
        :aria-label="collapsed ? 'Expand sidebar' : 'Collapse sidebar'"
    >
        <x-icon name="panel-left" class="size-4" />
    </button>

    <span class="font-mono text-[11px] tracking-[0.13em] text-faint uppercase md:hidden">{{ $orgName }}</span>

    {{-- Search is the front door: one keystroke from anywhere. --}}
    <button
        type="button"
        x-on:click="$dispatch('palette:open')"
        class="group ml-auto hidden h-9 w-64 items-center gap-2 rounded-lg border border-line bg-surface px-2.5 text-left text-sm text-faint shadow-xs transition-colors hover:border-line-strong hover:text-muted sm:flex lg:w-80"
    >
        <x-icon name="search" class="size-4" />
        <span class="flex-1 truncate">Search or jump to…</span>
        <span class="flex items-center gap-0.5">
            <x-kbd>⌘</x-kbd>
            <x-kbd>K</x-kbd>
        </span>
    </button>

    <div class="ml-auto flex items-center gap-1 sm:ml-2">
        <button
            type="button"
            x-on:click="$dispatch('palette:open')"
            class="flex size-9 items-center justify-center rounded-lg text-muted transition-colors hover:bg-subtle hover:text-ink sm:hidden"
            aria-label="Search"
        >
            <x-icon name="search" class="size-5" />
        </button>

        @if ($quickCreate !== [])
            <x-dropdown align="right" width="w-52" label="Create new">
                <x-slot:trigger>
                    <span class="inline-flex h-9 cursor-pointer items-center gap-1.5 rounded-lg bg-accent-solid px-2.5 text-sm font-medium text-accent-fg shadow-xs transition-colors hover:bg-accent-hover">
                        <x-icon name="plus" class="size-4" />
                        <span class="hidden sm:inline">New</span>
                        <x-icon name="chevron-down" class="size-3.5 opacity-70" />
                    </span>
                </x-slot:trigger>

                @foreach ($quickCreate as $action)
                    <x-dropdown.item :icon="$action['icon']" :href="$action['href']">{{ $action['label'] }}</x-dropdown.item>
                @endforeach
            </x-dropdown>
        @endif

        @if ($approvalCount > 0)
            <a
                href="{{ route('approvals.queue') }}"
                wire:navigate
                class="relative flex size-9 items-center justify-center rounded-lg text-muted transition-colors hover:bg-subtle hover:text-ink"
                aria-label="{{ $approvalCount }} items awaiting your approval"
            >
                <x-icon name="bell" class="size-4.5" />
                <span class="absolute top-1 right-1 flex min-w-4 items-center justify-center rounded-full bg-danger px-1 font-mono text-[9px] leading-4 font-medium text-white tabular-nums">
                    {{ $approvalCount > 9 ? '9+' : $approvalCount }}
                </span>
            </a>
        @endif

        <button
            type="button"
            x-on:click="toggleTheme()"
            class="flex size-9 items-center justify-center rounded-lg text-muted transition-colors hover:bg-subtle hover:text-ink"
            :aria-label="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
        >
            <x-icon name="sun" class="size-4 dark:hidden" />
            <x-icon name="moon" class="hidden size-4 dark:block" />
        </button>

        <x-dropdown align="right" width="w-52" label="Account menu" class="md:hidden">
            <x-slot:trigger>
                <span class="flex size-9 cursor-pointer items-center justify-center rounded-lg transition-colors hover:bg-subtle">
                    <x-avatar :name="auth()->user()->name" size="sm" />
                </span>
            </x-slot:trigger>

            <div class="border-b border-line px-2.5 py-2">
                <p class="truncate text-xs font-medium text-ink">{{ auth()->user()->name }}</p>
                <p class="truncate font-mono text-[10px] text-muted">{{ auth()->user()->email }}</p>
            </div>

            <x-dropdown.item icon="panel-left" x-on:click="toggleDensity()">
                <span x-text="density === 'compact' ? 'Comfortable rows' : 'Compact rows'">Row density</span>
            </x-dropdown.item>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <x-dropdown.item icon="external" type="submit" tone="danger">Log out</x-dropdown.item>
            </form>
        </x-dropdown>
    </div>
</header>
