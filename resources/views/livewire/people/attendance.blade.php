<div class="space-y-6">
    <x-page-header
        title="Attendance"
        subtitle="First login of each {{ $tz }} day is check-in. Late after {{ sprintf('%02d:00', $lateHour) }} local."
        :breadcrumbs="[['label' => 'People']]"
    >
        <x-slot:meta>
            <x-badge tone="neutral">{{ $subject->name }}</x-badge>
            <x-badge tone="neutral" size="sm">{{ $sheet['year_month'] }}</x-badge>
        </x-slot:meta>
    </x-page-header>

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
        <x-stat label="Present" :value="$sheet['days_present']" tone="success" hint="days" />
        <x-stat label="Late" :value="$sheet['days_late']" tone="warn" hint="after {{ sprintf('%02d:00', $lateHour) }}" />
        <x-stat label="Leave" :value="$sheet['days_leave']" tone="warn" hint="days" />
        <x-stat label="Holiday" :value="$sheet['days_holiday']" tone="info" hint="days" />
        <x-stat label="Absent" :value="$sheet['days_absent']" tone="danger" hint="no login" />
    </div>

    <x-filter-bar target="month,userId">
        <x-input
            type="month"
            size="sm"
            data-page-search
            class="w-auto"
            wire:model.live="month"
            aria-label="Attendance month"
        />

        @if ($canFilterUsers)
            <x-select size="sm" class="w-auto" wire:model.live="userId" aria-label="Filter by person">
                @foreach ($viewableUsers as $u)
                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                @endforeach
            </x-select>
        @endif

        <x-slot:trailing>
            {{ count($sheet['rows']) }} days
        </x-slot:trailing>
    </x-filter-bar>

    @if ($canManage)
        <x-card title="Mark leave / holiday" subtitle="Applies to {{ $subject->name }} (selected person above)." icon="calendar">
            <form wire:submit="markLeave" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <x-input type="date" label="Date" wire:model="markDate" :error="$errors->first('markDate')" />

                <x-select label="Type" wire:model="markStatus" :error="$errors->first('markStatus')">
                    <option value="leave">Leave</option>
                    <option value="holiday">Holiday</option>
                </x-select>

                <x-input label="Notes" wire:model="markNotes" :error="$errors->first('markNotes')" placeholder="Optional reason" />

                <div class="flex items-end">
                    <x-button type="submit" target="markLeave" class="w-full sm:w-auto">Save mark</x-button>
                </div>
            </form>
        </x-card>
    @endif

    <div wire:loading.delay.long.flex wire:target="month,userId" class="hidden">
        <x-skeleton variant="table" :rows="8" :cols="4" class="w-full" />
    </div>

    @if (empty($sheet['rows']))
        <x-empty-state
            icon="attendance"
            title="Nothing to show for this month"
            description="Pick another month, or wait for the first sign-in of the month to create the day sheet."
        />
    @else
        <div wire:loading.class="opacity-60" wire:target="month,userId" class="transition-opacity duration-150">
            <x-table
                :headers="array_values(array_filter([
                    'Date',
                    'Status',
                    ['label' => 'First login', 'align' => 'right'],
                    'Notes',
                    $canManage ? ['label' => 'Actions', 'align' => 'right'] : null,
                ]))"
            >
                @foreach ($sheet['rows'] as $row)
                    <x-table.row wire:key="day-{{ $row['date'] }}" class="{{ $row['is_weekend'] ? '[&>td]:bg-subtle/40' : '' }}">
                        <x-table.cell mono nowrap>
                            {{ $row['date'] }}
                            <span class="text-muted">{{ $row['weekday'] }}</span>
                        </x-table.cell>

                        <x-table.cell nowrap>
                            @if ($row['status'] === 'present')
                                @if ($row['is_late'])
                                    <x-badge size="sm" tone="warn">Present · late</x-badge>
                                @else
                                    <x-badge size="sm" tone="success">Present</x-badge>
                                @endif
                            @elseif ($row['status'] === 'leave')
                                <x-badge size="sm" tone="warn">Leave</x-badge>
                            @elseif ($row['status'] === 'holiday')
                                <x-badge size="sm" tone="info">Holiday</x-badge>
                            @elseif ($row['status'] === 'absent')
                                <x-badge size="sm" tone="danger">Absent</x-badge>
                            @else
                                <span class="text-xs text-faint">—</span>
                            @endif
                        </x-table.cell>

                        <x-table.cell numeric muted>
                            {{ $row['first_login_at'] ? $row['first_login_at']->timezone($tz)->format('H:i') : '—' }}
                        </x-table.cell>

                        <x-table.cell muted class="text-xs">{{ $row['notes'] ?? '' }}</x-table.cell>

                        @if ($canManage)
                            <x-table.cell align="right" nowrap>
                                @if (in_array($row['status'], ['leave', 'holiday'], true))
                                    <x-button size="sm" variant="ghost" wire:click="clearMark('{{ $row['date'] }}')" aria-label="Clear mark for {{ $row['date'] }}">
                                        Clear
                                    </x-button>
                                @endif
                            </x-table.cell>
                        @endif
                    </x-table.row>
                @endforeach
            </x-table>
        </div>
    @endif
</div>
