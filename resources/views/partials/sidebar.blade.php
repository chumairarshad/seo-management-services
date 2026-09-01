<aside
    class="sticky top-0 hidden h-svh shrink-0 flex-col border-r border-line bg-surface transition-[width] duration-200 ease-out md:flex"
    :class="collapsed ? 'w-[68px]' : 'w-[236px]'"
>
    <div class="flex h-14 items-center gap-2.5 border-b border-line px-3">
        <a href="{{ route('dashboard') }}" wire:navigate class="flex min-w-0 items-center gap-2.5 rounded-lg px-1 py-1">
            <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-ink font-mono text-[13px] font-medium text-canvas">
                {{ mb_strtoupper(mb_substr($orgName, 0, 1)) }}
            </span>
            <span class="min-w-0" x-show="! collapsed" x-cloak>
                <span class="block truncate text-sm font-semibold tracking-tight text-ink">{{ $orgName }}</span>
                <span class="block font-mono text-[10px] tracking-[0.13em] text-faint uppercase">Portfolio OS</span>
            </span>
        </a>
    </div>

    <nav class="flex-1 overflow-y-auto px-2.5 py-3" aria-label="Main">
        @foreach ($navGroups as $group)
            <div class="{{ $loop->first ? '' : 'mt-4' }}">
                @if ($group['label'])
                    <p class="mb-1 px-2 font-mono text-[10px] tracking-[0.13em] text-faint uppercase" x-show="! collapsed" x-cloak>
                        {{ $group['label'] }}
                    </p>
                    <div class="mx-2 mb-2 h-px bg-line" x-show="collapsed" x-cloak></div>
                @endif

                <ul class="space-y-0.5">
                    @foreach ($group['items'] as $item)
                        <li>
                            <a
                                href="{{ $item['href'] }}"
                                wire:navigate
                                @if ($item['active']) aria-current="page" @endif
                                title="{{ $item['label'] }}"
                                class="group relative flex items-center gap-2.5 rounded-lg px-2 nav-y text-sm transition-colors duration-150
                                    {{ $item['active']
                                        ? 'bg-subtle font-medium text-ink'
                                        : 'text-muted hover:bg-subtle/70 hover:text-ink' }}"
                                :class="collapsed ? 'justify-center' : ''"
                            >
                                @if ($item['active'])
                                    <span class="absolute inset-y-1.5 left-0 w-0.5 rounded-r-full bg-accent-solid" aria-hidden="true"></span>
                                @endif

                                <x-icon :name="$item['icon']" class="size-4 shrink-0 {{ $item['active'] ? 'text-accent' : 'text-faint group-hover:text-muted' }}" />

                                <span class="min-w-0 flex-1 truncate" x-show="! collapsed" x-cloak>{{ $item['label'] }}</span>
                                <span class="sr-only" x-show="collapsed">{{ $item['label'] }}</span>

                                @if (($item['badge'] ?? 0) > 0)
                                    <span
                                        class="shrink-0 rounded-md bg-accent-soft px-1.5 font-mono text-[10px] font-medium text-accent tabular-nums"
                                        x-show="! collapsed"
                                        x-cloak
                                    >{{ $item['badge'] }}</span>
                                    <span class="absolute top-1 right-1 size-1.5 rounded-full bg-accent-solid" x-show="collapsed" x-cloak></span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </nav>

    <div class="border-t border-line p-2.5">
        <x-dropdown align="left" placement="top" width="w-56" class="w-full" label="Account menu">
            <x-slot:trigger>
                <span
                    class="flex w-full cursor-pointer items-center gap-2.5 rounded-lg px-1.5 py-1.5 text-left transition-colors hover:bg-subtle"
                    :class="collapsed ? 'justify-center' : ''"
                >
                    <x-avatar :name="auth()->user()->name" size="md" />
                    <span class="min-w-0 flex-1" x-show="! collapsed" x-cloak>
                        <span class="block truncate text-sm font-medium text-ink">{{ auth()->user()->name }}</span>
                        <span class="block truncate font-mono text-[10px] text-faint">{{ auth()->user()->email }}</span>
                    </span>
                    <x-icon name="chevron-up-down" class="size-3.5 shrink-0 text-faint" x-show="! collapsed" x-cloak />
                </span>
            </x-slot:trigger>

            <div class="border-b border-line px-2.5 py-2">
                <p class="truncate text-xs font-medium text-ink">{{ auth()->user()->name }}</p>
                <p class="truncate font-mono text-[10px] text-muted">{{ auth()->user()->email }}</p>
            </div>

            <x-dropdown.item icon="sun" x-on:click="toggleTheme()">
                <span x-text="isDark ? 'Light mode' : 'Dark mode'">Toggle theme</span>
            </x-dropdown.item>

            <x-dropdown.item icon="panel-left" x-on:click="toggleDensity()">
                <span x-text="density === 'compact' ? 'Comfortable rows' : 'Compact rows'">Row density</span>
            </x-dropdown.item>

            <x-dropdown.item icon="command" x-on:click="$dispatch('shortcuts:open')" shortcut="?">
                Keyboard shortcuts
            </x-dropdown.item>

            <div class="my-1 h-px bg-line"></div>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <x-dropdown.item icon="external" type="submit" tone="danger">Log out</x-dropdown.item>
            </form>
        </x-dropdown>
    </div>
</aside>
