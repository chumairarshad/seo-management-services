@php
    $totals = $report['totals'];
    $marginLabel = $totals['revenue_paisa'] > 0
        ? number_format($totals['net_profit_paisa'] / $totals['revenue_paisa'] * 100, 1).'%'
        : '—';
    $profitTone = $totals['net_profit_paisa'] < 0 ? 'danger' : 'success';
@endphp

<div class="space-y-6">
    <x-page-header
        title="Profit & Loss"
        subtitle="Revenue minus direct expenses minus each site's share of shared costs, for one calendar month."
        :breadcrumbs="[['label' => 'Money'], ['label' => 'Profit & Loss']]"
    >
        <x-slot:actions>
            <x-input size="sm" class="w-auto" type="month" wire:model.live="month" aria-label="Report month" />
            <x-button variant="secondary" icon="download" wire:click="exportCsv">Export CSV</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat label="Revenue" tone="accent" icon="revenue" :hint="\App\Support\Currency::code().', frozen at each row\'s FX rate'">
            <x-money :paisa="$totals['revenue_paisa']" size="lg" :tone="false" />
        </x-stat>

        <x-stat label="Expenses" tone="neutral" icon="expenses" hint="direct plus shared, allocated by revenue">
            <x-money :paisa="$totals['total_expense_paisa']" size="lg" :tone="false" />
        </x-stat>

        <x-stat label="Net profit" :tone="$profitTone" icon="pnl" hint="revenue − all costs">
            <x-money :paisa="$totals['net_profit_paisa']" size="lg" signed />
        </x-stat>

        <x-stat
            label="Margin"
            :value="$marginLabel"
            :tone="$profitTone"
            icon="target"
            hint="net profit as a share of revenue"
        />
    </div>

    <div wire:loading.delay.long.flex wire:target="month" class="hidden">
        <x-skeleton variant="table" class="w-full" :rows="6" :cols="5" />
    </div>

    @if (empty($report['projects']))
        <x-empty-state
            icon="pnl"
            title="Nothing to report for this month"
            description="A site appears here once it has revenue or an expense dated inside the selected month. Pick another month, or record revenue and costs first."
        >
            <x-button variant="secondary" icon="revenue" href="{{ route('money.revenues') }}" wire:navigate>
                Go to revenue
            </x-button>
        </x-empty-state>
    @else
        <div wire:loading.class="opacity-60" wire:target="month" class="transition-opacity">
            <x-table
                :headers="[
                    'Project',
                    ['label' => 'Revenue', 'align' => 'right'],
                    ['label' => 'Direct', 'align' => 'right'],
                    ['label' => 'Shared', 'align' => 'right'],
                    ['label' => 'Expenses', 'align' => 'right'],
                    ['label' => 'Net', 'align' => 'right'],
                ]"
            >
                @foreach ($report['projects'] as $row)
                    <x-table.row>
                        <x-table.cell class="font-medium text-ink">{{ $row['domain'] }}</x-table.cell>
                        <x-table.cell numeric>
                            <x-money :paisa="$row['revenue_paisa']" :tone="false" />
                        </x-table.cell>
                        <x-table.cell numeric>
                            <x-money :paisa="$row['direct_expense_paisa']" :tone="false" />
                        </x-table.cell>
                        <x-table.cell numeric>
                            <x-money :paisa="$row['shared_expense_paisa']" :tone="false" />
                        </x-table.cell>
                        <x-table.cell numeric>
                            <x-money :paisa="$row['total_expense_paisa']" :tone="false" />
                        </x-table.cell>
                        <x-table.cell numeric>
                            <x-money :paisa="$row['net_profit_paisa']" signed />
                        </x-table.cell>
                    </x-table.row>
                @endforeach

                <x-slot:footer>
                    <div class="flex flex-wrap items-center justify-end gap-x-6 gap-y-1">
                        <span class="mr-auto font-mono text-eyebrow uppercase">Portfolio total</span>
                        <span class="flex items-center gap-1.5">
                            Revenue
                            <x-money :paisa="$totals['revenue_paisa']" size="md" :tone="false" />
                        </span>
                        <span class="flex items-center gap-1.5">
                            Expenses
                            <x-money :paisa="$totals['total_expense_paisa']" size="md" :tone="false" />
                        </span>
                        <span class="flex items-center gap-1.5">
                            Net
                            <x-money :paisa="$totals['net_profit_paisa']" size="md" signed />
                        </span>
                    </div>
                </x-slot:footer>
            </x-table>
        </div>
    @endif
</div>
