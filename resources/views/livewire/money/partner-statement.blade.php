<div class="space-y-6">
    <x-page-header
        title="{{ $partner->name }}"
        subtitle="Every capital contribution, withdrawal and distribution credit, newest first, with the balance after each entry."
        :breadcrumbs="[
            ['label' => 'Money'],
            ['label' => 'Partners', 'href' => route('money.partners')],
            ['label' => $partner->name],
        ]"
        back="{{ route('money.partners') }}"
    >
        <x-slot:meta>
            <span class="font-mono text-xs text-muted">{{ $partner->email }}</span>
        </x-slot:meta>

        <x-slot:actions>
            <x-button variant="secondary" icon="download" wire:click="exportCsv">Export CSV</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-3 sm:grid-cols-2">
        <x-stat
            label="Running balance"
            :tone="$balance < 0 ? 'danger' : 'success'"
            icon="partners"
            hint="owed to the partner after the latest entry"
        >
            <x-money :paisa="$balance" size="lg" :currency="\App\Support\Currency::code()" signed />
        </x-stat>

        <x-stat
            label="Ledger entries"
            :value="number_format($entries->total())"
            tone="neutral"
            icon="history"
            hint="capital, withdrawals and distribution credits"
        />
    </div>

    <div wire:loading.delay.long.flex wire:target="previousPage,nextPage,gotoPage" class="hidden">
        <x-skeleton variant="table" class="w-full" :rows="6" :cols="5" />
    </div>

    @if ($entries->isEmpty())
        <x-empty-state
            icon="partners"
            title="No ledger entries yet"
            description="This partner's ledger starts with their first capital contribution or an approved distribution run. Record capital from the partners screen."
        >
            <x-button variant="secondary" iconRight="arrow-right" href="{{ route('money.partners') }}" wire:navigate>
                Back to partners
            </x-button>
        </x-empty-state>
    @else
        <div wire:loading.class="opacity-60" wire:target="previousPage,nextPage,gotoPage" class="transition-opacity">
            <x-table
                :headers="[
                    ['label' => 'Date', 'width' => 'w-28'],
                    'Type',
                    'Description',
                    ['label' => 'Amount', 'align' => 'right'],
                    ['label' => 'Balance', 'align' => 'right'],
                ]"
            >
                @foreach ($entries as $e)
                    <x-table.row>
                        <x-table.cell mono nowrap>{{ $e->entry_date->format('Y-m-d') }}</x-table.cell>
                        <x-table.cell>
                            <x-badge size="sm" :tone="$e->amount_paisa < 0 ? 'warn' : 'accent'">
                                {{ $e->type->label() }}
                            </x-badge>
                        </x-table.cell>
                        <x-table.cell>{{ $e->description }}</x-table.cell>
                        <x-table.cell numeric>
                            <x-money :paisa="$e->amount_paisa" signed />
                        </x-table.cell>
                        <x-table.cell numeric>
                            <x-money :paisa="$e->balance_after_paisa" :tone="false" />
                        </x-table.cell>
                    </x-table.row>
                @endforeach
            </x-table>
        </div>

        {{ $entries->links() }}
    @endif
</div>
