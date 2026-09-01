<div class="space-y-6">
    <x-page-header
        title="Revenue"
        :subtitle="'Per site, per month. '.\App\Support\Currency::sourceCode().' is frozen at the row\'s FX rate, so stored '.\App\Support\Currency::code().' never re-converts.'"
        :breadcrumbs="[['label' => 'Money'], ['label' => 'Revenue']]"
    >
        <x-slot:actions>
            <x-button variant="secondary" icon="download" wire:click="exportCsv">Export CSV</x-button>
            @if ($canManage)
                <x-button variant="secondary" icon="upload" wire:click="$set('showImport', true)">Import CSV</x-button>
                <x-button icon="plus" wire:click="create">Add revenue</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <x-filter-bar target="search,projectFilter,monthFilter">
        <x-input
            class="min-w-[12rem] flex-1 sm:max-w-xs"
            size="sm"
            icon="search"
            type="search"
            data-page-search
            wire:model.live.debounce.300ms="search"
            placeholder="Search domain or notes…"
        />
        <x-select size="sm" class="w-auto" wire:model.live="projectFilter" placeholder="All projects">
            @foreach ($projects as $p)
                <option value="{{ $p->id }}">{{ $p->domain }}</option>
            @endforeach
        </x-select>
        <x-input size="sm" class="w-auto" type="month" wire:model.live="monthFilter" aria-label="Month" />
        <x-slot:trailing>{{ $revenues->total() }} rows</x-slot:trailing>
    </x-filter-bar>

    @if ($showForm && $canManage)
        <x-card
            :title="$editingId ? 'Edit revenue' : 'New revenue'"
            subtitle="The FX rate is stored on the row at save time and is never recalculated later."
        >
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <x-select
                    label="Project"
                    wire:model="project_id"
                    placeholder="Select…"
                    :error="$errors->first('project_id')"
                    required
                >
                    @foreach ($projects as $p)
                        <option value="{{ $p->id }}">{{ $p->domain }}</option>
                    @endforeach
                </x-select>

                <x-input
                    type="month"
                    label="Month"
                    wire:model="period_month"
                    :error="$errors->first('period_month')"
                    required
                />

                <x-select label="Source" wire:model="source" :error="$errors->first('source')" required>
                    @foreach ($sources as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </x-select>

                <x-input
                    :label="'Amount '.\App\Support\Currency::sourceCode()"
                    wire:model="amount_usd"
                    :error="$errors->first('amount_usd')"
                    :suffix="\App\Support\Currency::sourceCode()"
                    required
                />

                <x-input
                    :label="'FX rate ('.\App\Support\Currency::fxLabel().')'"
                    wire:model="fx_rate"
                    :error="$errors->first('fx_rate')"
                    hint="Stored on the row; never recalculated later at a new rate."
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
                <x-button wire:click="save">Save</x-button>
                <x-button variant="ghost" wire:click="$set('showForm', false)">Cancel</x-button>
            </div>
        </x-card>
    @endif

    @if ($showImport && $canManage)
        <x-card title="Import AdSense CSV" subtitle="Columns: domain, month (YYYY-MM), amount_usd [, fx_rate]">
            <x-file-input
                wire:model="importFile"
                accept=".csv,text/csv"
                :filename="$importFile?->getClientOriginalName()"
                :error="$errors->first('importFile')"
                hint="CSV only"
            />

            <div class="mt-5 flex flex-wrap gap-2">
                <x-button icon="upload" wire:click="import">Import</x-button>
                <x-button variant="ghost" wire:click="$set('showImport', false)">Cancel</x-button>
            </div>
        </x-card>
    @endif

    <div wire:loading.delay.long.flex wire:target="search,projectFilter,monthFilter" class="hidden">
        <x-skeleton variant="table" class="w-full" :rows="6" :cols="5" />
    </div>

    @if ($revenues->isEmpty())
        <x-empty-state
            icon="revenue"
            title="No revenue for this period"
            :description="'Revenue is logged per site per month with the '.\App\Support\Currency::sourceCode().'→'.\App\Support\Currency::code().' rate frozen on the row. Widen the month or project filter, or record the first row.'"
        >
            @if ($canManage)
                <x-button icon="plus" wire:click="create">Add revenue</x-button>
                <x-button variant="secondary" icon="upload" wire:click="$set('showImport', true)">Import CSV</x-button>
            @endif
        </x-empty-state>
    @else
        <div wire:loading.class="opacity-60" wire:target="search,projectFilter,monthFilter" class="transition-opacity">
            <x-table
                :headers="array_merge([
                    ['label' => 'Month', 'width' => 'w-24'],
                    'Project',
                    'Source',
                    ['label' => \App\Support\Currency::sourceCode(), 'align' => 'right'],
                    ['label' => 'FX', 'align' => 'right'],
                    ['label' => \App\Support\Currency::code(), 'align' => 'right'],
                ], $canManage ? [['label' => 'Actions', 'align' => 'right', 'width' => 'w-24']] : [])"
            >
                @foreach ($revenues as $row)
                    <x-table.row>
                        <x-table.cell mono nowrap>{{ $row->period_month->format('Y-m') }}</x-table.cell>
                        <x-table.cell class="font-medium text-ink">{{ $row->project?->domain }}</x-table.cell>
                        <x-table.cell>
                            <x-badge size="sm">{{ $row->source->label() }}</x-badge>
                        </x-table.cell>
                        <x-table.cell numeric>
                            <x-money :paisa="$row->amount_usd_cents" />
                        </x-table.cell>
                        <x-table.cell numeric muted>{{ \App\Support\Money::fxRateFromE6($row->fx_rate_e6) }}</x-table.cell>
                        <x-table.cell numeric>
                            <x-money :paisa="$row->amount_pkr_paisa" />
                        </x-table.cell>

                        @if ($canManage)
                            <x-table.cell align="right" tight nowrap>
                                <div class="inline-flex items-center justify-end gap-0.5">
                                    <x-tooltip text="Edit revenue">
                                        <x-button
                                            size="sm"
                                            variant="ghost"
                                            square
                                            icon="pencil"
                                            aria-label="Edit revenue"
                                            wire:click="edit({{ $row->id }})"
                                        />
                                    </x-tooltip>
                                    <x-tooltip text="Delete revenue">
                                        <x-button
                                            size="sm"
                                            variant="danger-ghost"
                                            square
                                            icon="trash"
                                            aria-label="Delete revenue"
                                            wire:click="delete({{ $row->id }})"
                                            wire:confirm="Soft-delete this revenue?"
                                        />
                                    </x-tooltip>
                                </div>
                            </x-table.cell>
                        @endif
                    </x-table.row>
                @endforeach
            </x-table>
        </div>

        {{ $revenues->links() }}
    @endif
</div>
