<div class="space-y-6">
    <x-page-header
        title="Scorecard"
        subtitle="Derived from tasks, articles and links for {{ $subject->name }} · {{ $tz }}."
        :breadcrumbs="[['label' => 'People']]"
    >
        <x-slot:meta>
            <x-badge tone="neutral">{{ $subject->name }}</x-badge>
            <x-badge tone="neutral" size="sm">{{ $card['year_month'] }}</x-badge>
        </x-slot:meta>
        <x-slot:actions>
            @if (auth()->user()?->hasPermission('work_logs.view'))
                <x-button variant="secondary" icon="worklogs" href="{{ route('people.work-logs') }}" wire:navigate>Work logs</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <x-filter-bar target="month,userId">
        <x-input
            type="month"
            size="sm"
            data-page-search
            class="w-auto"
            wire:model.live="month"
            aria-label="Scorecard month"
        />

        @if ($canFilterUsers)
            <x-select size="sm" class="w-auto" wire:model.live="userId" aria-label="Filter by person">
                @foreach ($viewableUsers as $u)
                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                @endforeach
            </x-select>
        @endif

        <x-slot:trailing>
            {{ $card['period']['local_from'] }} → {{ $card['period']['local_to'] }}
        </x-slot:trailing>
    </x-filter-bar>

    <div wire:loading.delay.long.flex wire:target="month,userId" class="hidden">
        <x-skeleton variant="cards" :rows="4" class="w-full" />
    </div>

    <div wire:loading.class="opacity-60" wire:target="month,userId" class="space-y-6 transition-opacity duration-150">
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat
                label="Tasks done"
                :value="$card['tasks']['completed']"
                icon="tasks"
                tone="accent"
                hint="of {{ $card['tasks']['assigned'] }} in scope"
            />
            <x-stat
                label="On-time %"
                :value="$card['tasks']['on_time_pct'] !== null ? $card['tasks']['on_time_pct'].'%' : '—'"
                icon="target"
                :tone="$card['tasks']['on_time_pct'] !== null && $card['tasks']['on_time_pct'] >= 80 ? 'success' : 'warn'"
                hint="{{ $card['tasks']['on_time'] }} on-time approvals"
            />
            <x-stat
                label="Rejection rate"
                :value="$card['tasks']['rejection_rate_pct'] !== null ? $card['tasks']['rejection_rate_pct'].'%' : '—'"
                icon="alert"
                :tone="$card['tasks']['rejected'] > 0 ? 'danger' : 'neutral'"
                hint="{{ $card['tasks']['rejected'] }} rejected"
            />
            <x-stat
                label="Avg turnaround"
                :value="$card['tasks']['avg_turnaround_hours'] !== null ? $card['tasks']['avg_turnaround_hours'].'h' : '—'"
                icon="clock"
                tone="info"
                hint="submit → approve"
            />
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <x-card title="Delivery" subtitle="Actual against what was in scope this month." icon="approvals">
                <div class="space-y-4">
                    <x-progress
                        :value="$card['tasks']['completed']"
                        :max="max(1, $card['tasks']['assigned'])"
                        label="Tasks completed"
                        caption="{{ $card['tasks']['completed'] }} / {{ $card['tasks']['assigned'] }}"
                        tone="accent"
                    />
                    <x-progress
                        :value="$card['tasks']['on_time']"
                        :max="max(1, $card['tasks']['completed'])"
                        label="On time"
                        caption="{{ $card['tasks']['on_time'] }} / {{ $card['tasks']['completed'] }}"
                        :tone="$card['tasks']['on_time'] === $card['tasks']['completed'] ? 'success' : 'warn'"
                    />
                    <x-progress
                        :value="$card['articles']['approved']"
                        :max="max(1, $card['articles']['count'])"
                        label="Articles approved"
                        caption="{{ $card['articles']['approved'] }} / {{ $card['articles']['count'] }}"
                        tone="success"
                    />
                </div>
            </x-card>

            <x-card title="Articles" subtitle="Written in this month’s window." icon="articles" flush class="overflow-hidden">
                <x-table flush :headers="['Measure', ['label' => 'Value', 'align' => 'right']]">
                    <x-table.row>
                        <x-table.cell muted>In scope</x-table.cell>
                        <x-table.cell numeric>{{ $card['articles']['count'] }}</x-table.cell>
                    </x-table.row>
                    <x-table.row>
                        <x-table.cell muted>Approved</x-table.cell>
                        <x-table.cell numeric>{{ $card['articles']['approved'] }}</x-table.cell>
                    </x-table.row>
                    <x-table.row>
                        <x-table.cell muted>Words</x-table.cell>
                        <x-table.cell numeric>{{ number_format($card['articles']['words']) }}</x-table.cell>
                    </x-table.row>
                    <x-table.row>
                        <x-table.cell muted>Approved cost</x-table.cell>
                        <x-table.cell numeric><x-money :paisa="$card['articles']['cost_paisa']" :currency="\App\Support\Currency::code()" /></x-table.cell>
                    </x-table.row>
                    <x-table.row>
                        <x-table.cell muted>Links approved</x-table.cell>
                        <x-table.cell numeric>{{ $card['links']['approved'] }}</x-table.cell>
                    </x-table.row>
                    <x-table.row>
                        <x-table.cell muted>Link cost</x-table.cell>
                        <x-table.cell numeric><x-money :paisa="$card['links']['cost_paisa']" :currency="\App\Support\Currency::code()" /></x-table.cell>
                    </x-table.row>
                </x-table>
            </x-card>

            <x-card title="Output cost" subtitle="Articles + links approved in this month." icon="revenue">
                <p class="font-mono text-figure font-medium text-ink tabular-nums">{{ $card['output_cost_formatted'] }}</p>

                @if (! empty($card['pay_rates']))
                    <h3 class="mt-6 font-mono text-eyebrow text-muted uppercase">Your pay rates</h3>
                    <ul class="mt-2 divide-y divide-line">
                        @foreach ($card['pay_rates'] as $rate)
                            <li class="flex items-center justify-between gap-2 py-2 text-sm">
                                <span class="text-muted">{{ $rate['type_label'] }}</span>
                                <span class="font-mono text-xs text-ink tabular-nums">{{ $rate['amount_formatted'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="mt-4 text-xs text-muted">No pay rates recorded for you yet.</p>
                @endif
            </x-card>
        </div>
    </div>
</div>
