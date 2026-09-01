<div class="space-y-6">
    <x-page-header
        title="Partners"
        subtitle="Capital in, withdrawals, and distribution credits — every partner's running balance in one ledger."
        :breadcrumbs="[['label' => 'Money'], ['label' => 'Partners']]"
    >
        <x-slot:actions>
            <x-button variant="secondary" icon="download" wire:click="exportCsv">Export ledger CSV</x-button>
            @if ($canManage)
                <x-button icon="plus" wire:click="$set('showCapital', true)">Record capital / withdrawal</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($showCapital && $canManage)
        <x-card
            title="Record capital or withdrawal"
            subtitle="Posts a single ledger entry and moves the partner's running balance immediately."
        >
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-select
                    label="Partner"
                    wire:model="user_id"
                    placeholder="Select…"
                    :error="$errors->first('user_id')"
                    required
                >
                    @foreach ($partners as $p)
                        <option value="{{ $p->id }}">{{ $p->name }}</option>
                    @endforeach
                </x-select>

                <x-select label="Type" wire:model="entry_type" :error="$errors->first('entry_type')" required>
                    <option value="capital_in">Capital in</option>
                    <option value="withdrawal">Withdrawal</option>
                </x-select>

                <x-input
                    label="Amount"
                    wire:model="amount"
                    :error="$errors->first('amount')"
                    :suffix="\App\Support\Currency::code()"
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
                <x-button wire:click="postEntry">Post</x-button>
                <x-button variant="ghost" wire:click="$set('showCapital', false)">Cancel</x-button>
            </div>
        </x-card>
    @endif

    @if ($partners->isEmpty())
        <x-empty-state
            icon="partners"
            title="No partners yet"
            description="Partners appear here once a user holds the partner role or owns a share of a project. Assign ownership on a project to start their ledger."
        >
            <x-button variant="secondary" icon="projects" href="{{ route('projects.index') }}" wire:navigate>
                Go to projects
            </x-button>
        </x-empty-state>
    @else
        <x-table
            :headers="[
                'Partner',
                'Email',
                'Payout',
                ['label' => 'Balance '.\App\Support\Currency::code(), 'align' => 'right'],
                ['label' => '', 'align' => 'right', 'width' => 'w-32'],
            ]"
        >
            @foreach ($partners as $p)
                <x-table.row>
                    <x-table.cell class="font-medium text-ink">{{ $p->name }}</x-table.cell>
                    <x-table.cell mono muted>{{ $p->email }}</x-table.cell>
                    <x-table.cell muted>{{ $profiles[$p->id]->payout_method ?? '—' }}</x-table.cell>
                    <x-table.cell numeric>
                        <x-money :paisa="$balances[$p->id] ?? 0" />
                    </x-table.cell>
                    <x-table.cell align="right" tight nowrap>
                        <x-button
                            size="sm"
                            variant="ghost"
                            iconRight="arrow-right"
                            href="{{ route('money.partners.statement', ['user' => $p->id]) }}"
                            wire:navigate
                        >Statement</x-button>
                    </x-table.cell>
                </x-table.row>
            @endforeach
        </x-table>
    @endif
</div>
