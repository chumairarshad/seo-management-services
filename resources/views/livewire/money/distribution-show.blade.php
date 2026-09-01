<div class="space-y-6">
    <x-page-header
        title="Distribution #{{ $run->id }}"
        subtitle="Per-partner credits for {{ $run->period_month->format('F Y') }}, built from the ownership shares frozen onto this run."
        :breadcrumbs="[
            ['label' => 'Money'],
            ['label' => 'Distributions', 'href' => route('money.distributions')],
            ['label' => '#'.$run->id],
        ]"
        back="{{ route('money.distributions') }}"
    >
        <x-slot:meta>
            <x-badge
                dot
                :tone="match ($run->status->value) {
                    'approved' => 'success',
                    'voided' => 'danger',
                    default => 'neutral',
                }"
            >{{ $run->status->label() }}</x-badge>

            <span class="font-mono text-xs text-muted">{{ $run->period_month->format('Y-m') }}</span>

            @if ($run->approved_at)
                <span class="font-mono text-xs text-muted">
                    Approved {{ $run->approved_at->timezone(\App\Support\DisplayTimezone::name())->format('Y-m-d H:i') }}
                </span>
            @endif

            @if ($locked)
                <x-badge tone="warn">Locked — edits not allowed</x-badge>
            @endif
        </x-slot:meta>

        <x-slot:actions>
            <x-button variant="secondary" icon="download" wire:click="exportCsv">Export lines CSV</x-button>
            @if ($canApprove && $run->status->value === 'draft')
                <x-button
                    icon="check-circle"
                    wire:click="approve"
                    wire:confirm="Approve and lock this run? Partner ledger will be credited."
                >Approve &amp; lock</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-3 sm:grid-cols-3">
        <x-stat label="Total net" tone="accent" icon="pnl" hint="net profit feeding this run">
            <x-money :paisa="$run->total_net_profit_paisa" size="lg" signed />
        </x-stat>

        <x-stat label="Credited" tone="success" icon="partners" hint="posted to partner ledgers on approval">
            <x-money :paisa="$run->total_credited_paisa" size="lg" :tone="false" />
        </x-stat>

        <x-stat
            label="Holdback"
            tone="warn"
            icon="distributions"
            hint="{{ number_format($run->holdback_bps / 100, 2) }}% retained for reinvestment"
        >
            <x-money :paisa="$run->total_holdback_paisa" size="lg" :tone="false" />
        </x-stat>
    </div>

    @if ($run->ownership_snapshot)
        <details class="group/snap rounded-xl border border-line bg-surface shadow-xs">
            <summary class="flex cursor-pointer items-center gap-2 px-4 py-3.5 text-sm font-semibold text-ink sm:px-6">
                <x-icon name="chevron-right" class="size-4 text-muted transition-transform group-open/snap:rotate-90" />
                Ownership snapshot (frozen)
            </summary>
            <pre class="overflow-x-auto border-t border-line px-4 py-4 font-mono text-xs text-muted sm:px-6">{{ json_encode($run->ownership_snapshot, JSON_PRETTY_PRINT) }}</pre>
        </details>
    @endif

    @if ($run->lines->isEmpty())
        <x-empty-state
            icon="distributions"
            title="This run has no lines"
            description="Lines are generated from projects that had net profit in the period and an ownership split. Nothing qualified for this month."
        />
    @else
        <x-table
            :headers="[
                'Project',
                'Partner',
                ['label' => 'Share %', 'align' => 'right'],
                ['label' => 'Net profit', 'align' => 'right'],
                ['label' => 'Gross share', 'align' => 'right'],
                ['label' => 'Holdback', 'align' => 'right'],
                ['label' => 'Credited', 'align' => 'right'],
            ]"
        >
            @foreach ($run->lines as $line)
                <x-table.row>
                    <x-table.cell class="font-medium text-ink">{{ $line->project?->domain }}</x-table.cell>
                    <x-table.cell>{{ $line->user?->name }}</x-table.cell>
                    <x-table.cell numeric muted>{{ number_format($line->share_bps / 100, 2) }}</x-table.cell>
                    <x-table.cell numeric>
                        <x-money :paisa="$line->net_profit_paisa" signed />
                    </x-table.cell>
                    <x-table.cell numeric>
                        <x-money :paisa="$line->gross_share_paisa" :tone="false" />
                    </x-table.cell>
                    <x-table.cell numeric muted>
                        <x-money :paisa="$line->holdback_paisa" :tone="false" />
                    </x-table.cell>
                    <x-table.cell numeric>
                        <x-money :paisa="$line->credited_paisa" :tone="false" />
                    </x-table.cell>
                </x-table.row>
            @endforeach

            <x-slot:footer>
                <div class="flex flex-wrap items-center justify-end gap-x-6 gap-y-1">
                    <span class="mr-auto font-mono text-eyebrow uppercase">Run total</span>
                    <span class="flex items-center gap-1.5">
                        Net
                        <x-money :paisa="$run->total_net_profit_paisa" size="md" signed />
                    </span>
                    <span class="flex items-center gap-1.5">
                        Holdback
                        <x-money :paisa="$run->total_holdback_paisa" size="md" :tone="false" />
                    </span>
                    <span class="flex items-center gap-1.5">
                        Credited
                        <x-money :paisa="$run->total_credited_paisa" size="md" :tone="false" />
                    </span>
                </div>
            </x-slot:footer>
        </x-table>
    @endif

    @if ($canApprove && $run->status->value === 'approved')
        <x-card
            title="Void this run"
            subtitle="An approved run is never edited. Voiding posts reversing entries to every partner ledger it credited."
            icon="alert"
        >
            <div class="flex flex-wrap items-end gap-3">
                <x-input
                    label="Void reason"
                    wire:model="void_reason"
                    :error="$errors->first('void_reason')"
                    class="min-w-[16rem] flex-1 sm:max-w-md"
                    required
                />
                <x-button
                    variant="danger"
                    icon="alert"
                    wire:click="void"
                    wire:confirm="Void and post reversing ledger entries?"
                >Void run</x-button>
            </div>
        </x-card>
    @endif
</div>
