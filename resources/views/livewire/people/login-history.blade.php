<div class="space-y-6">
    <x-page-header
        title="Login history"
        subtitle="Successful sign-ins, stored in UTC and shown in {{ $tz }}. Each first sign-in of the day also sets attendance."
        :breadcrumbs="[['label' => 'People']]"
    >
        <x-slot:actions>
            @if (auth()->user()?->hasPermission('attendance.view'))
                <x-button variant="secondary" icon="attendance" href="{{ route('people.attendance') }}" wire:navigate>Attendance sheet</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <x-filter-bar target="userId">
        @if ($canFilterUsers)
            <x-select
                size="sm"
                class="w-auto"
                data-page-search
                wire:model.live="userId"
                placeholder="Everyone I can see"
                aria-label="Filter by person"
            >
                @foreach ($viewableUsers as $u)
                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                @endforeach
            </x-select>
        @else
            <p class="px-1 text-xs text-muted">Your own sign-ins.</p>
        @endif

        <x-slot:trailing>
            {{ $histories->total() }} {{ \Illuminate\Support\Str::plural('sign-in', $histories->total()) }}
        </x-slot:trailing>
    </x-filter-bar>

    <div wire:loading.delay.long.flex wire:target="userId" class="hidden">
        <x-skeleton variant="table" :rows="6" :cols="4" class="w-full" />
    </div>

    @if ($histories->isEmpty())
        <x-empty-state
            icon="history"
            title="No sign-ins recorded yet"
            description="Every successful sign-in lands here with its time, IP and device, so you can audit access and check attendance."
        />
    @else
        <div wire:loading.class="opacity-60" wire:target="userId" class="transition-opacity duration-150">
            <x-table :headers="['When', 'Person', 'Device', 'IP']">
                @foreach ($histories as $row)
                    <x-table.row wire:key="login-{{ $row->id }}">
                        <x-table.cell mono nowrap>
                            {{ $row->logged_in_at->timezone($tz)->format('Y-m-d H:i') }}
                            <span class="block text-[10px] text-faint">{{ $tz }}</span>
                        </x-table.cell>

                        <x-table.cell nowrap>
                            <span class="flex items-center gap-2">
                                <x-avatar :name="$row->user?->name ?? '—'" size="sm" />
                                <span class="text-sm font-medium text-ink">{{ $row->user?->name ?? 'Deleted user' }}</span>
                            </span>
                        </x-table.cell>

                        <x-table.cell mono muted>
                            {{ $row->device ?? '—' }}
                            @if ($row->user_agent)
                                <span class="block max-w-[22rem] truncate text-[10px] text-faint" title="{{ $row->user_agent }}">{{ $row->user_agent }}</span>
                            @endif
                        </x-table.cell>

                        <x-table.cell mono muted nowrap>{{ $row->ip_address ?? '—' }}</x-table.cell>
                    </x-table.row>
                @endforeach
            </x-table>
        </div>

        {{ $histories->links() }}
    @endif
</div>
