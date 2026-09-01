<div class="space-y-6">
    <x-page-header
        title="Distributions"
        subtitle="Manual approval only. Ownership shares are frozen onto the run the moment it is approved."
        :breadcrumbs="[['label' => 'Money'], ['label' => 'Distributions']]"
    >
        <x-slot:actions>
            <x-button variant="secondary" icon="download" wire:click="exportCsv">Export CSV</x-button>
            @if ($canManage)
                <x-button icon="plus" wire:click="$set('showCreate', true)">New draft</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($showCreate && $canManage)
        <x-card
            title="Create distribution draft"
            subtitle="Builds one line per partner per project from that month's net profit. Nothing is credited until you approve it."
        >
            <div class="grid gap-4 sm:grid-cols-3">
                <x-input
                    type="month"
                    label="Month"
                    wire:model="period_month"
                    :error="$errors->first('period_month')"
                    required
                />

                <x-input
                    label="Reinvestment holdback"
                    wire:model="holdback_percent"
                    :error="$errors->first('holdback_percent')"
                    hint="0–100; applied after ownership share"
                    suffix="%"
                    required
                />

                <x-textarea
                    label="Notes"
                    wire:model="notes"
                    rows="2"
                    :error="$errors->first('notes')"
                />
            </div>

            <div class="mt-5 flex flex-wrap gap-2">
                <x-button wire:click="createDraft">Build draft</x-button>
                <x-button variant="ghost" wire:click="$set('showCreate', false)">Cancel</x-button>
            </div>
        </x-card>
    @endif

    <div wire:loading.delay.long.flex wire:target="previousPage,nextPage,gotoPage" class="hidden">
        <x-skeleton variant="table" class="w-full" :rows="5" :cols="5" />
    </div>

    @if ($runs->isEmpty())
        <x-empty-state
            icon="distributions"
            title="No distribution runs yet"
            description="A run turns one month of net profit into per-partner credits using each project's ownership shares. Build a draft, review the lines, then approve to lock it."
        >
            @if ($canManage)
                <x-button icon="plus" wire:click="$set('showCreate', true)">New draft</x-button>
            @endif
        </x-empty-state>
    @else
        <div wire:loading.class="opacity-60" wire:target="previousPage,nextPage,gotoPage" class="transition-opacity">
            <x-table
                :headers="[
                    ['label' => 'Run', 'width' => 'w-16'],
                    ['label' => 'Period', 'width' => 'w-28'],
                    'Status',
                    ['label' => 'Net profit', 'align' => 'right'],
                    ['label' => 'Credited', 'align' => 'right'],
                    ['label' => 'Holdback', 'align' => 'right'],
                    ['label' => '', 'align' => 'right', 'width' => 'w-24'],
                ]"
            >
                @foreach ($runs as $run)
                    <x-table.row>
                        <x-table.cell mono muted>#{{ $run->id }}</x-table.cell>
                        <x-table.cell mono nowrap>{{ $run->period_month->format('Y-m') }}</x-table.cell>
                        <x-table.cell>
                            <x-badge
                                dot
                                :tone="match ($run->status->value) {
                                    'approved' => 'success',
                                    'voided' => 'danger',
                                    default => 'neutral',
                                }"
                            >{{ $run->status->label() }}</x-badge>
                        </x-table.cell>
                        <x-table.cell numeric>
                            <x-money :paisa="$run->total_net_profit_paisa" signed />
                        </x-table.cell>
                        <x-table.cell numeric>
                            <x-money :paisa="$run->total_credited_paisa" :tone="false" />
                        </x-table.cell>
                        <x-table.cell numeric muted>
                            <x-money :paisa="$run->total_holdback_paisa" :tone="false" />
                        </x-table.cell>
                        <x-table.cell align="right" tight nowrap>
                            <x-button
                                size="sm"
                                variant="ghost"
                                iconRight="arrow-right"
                                href="{{ route('money.distributions.show', $run) }}"
                                wire:navigate
                            >Open</x-button>
                        </x-table.cell>
                    </x-table.row>
                @endforeach
            </x-table>
        </div>

        {{ $runs->links() }}
    @endif
</div>
