<div class="space-y-6">
    <x-page-header
        title="Links"
        subtitle="Monthly targets, individual approval, domain duplicate warnings."
        :breadcrumbs="[['label' => 'Work'], ['label' => 'Links']]"
    >
        @if ($canCreate)
            <x-slot:actions>
                <x-button icon="plus" wire:click="create">Log link</x-button>
            </x-slot:actions>
        @endif
    </x-page-header>

    <x-filter-bar target="search,projectFilter,statusFilter">
        <x-input
            class="min-w-[12rem] flex-1 sm:max-w-xs"
            size="sm"
            icon="search"
            type="search"
            data-page-search
            wire:model.live.debounce.300ms="search"
            placeholder="Search URL, domain, anchor…"
            aria-label="Search links"
        />
        <x-select size="sm" class="w-auto" wire:model.live="projectFilter" placeholder="All projects" aria-label="Filter by project">
            @foreach ($projects as $project)
                <option value="{{ $project->id }}">{{ $project->domain }}</option>
            @endforeach
        </x-select>
        <x-select size="sm" class="w-auto" wire:model.live="statusFilter" placeholder="All workflows" aria-label="Filter by workflow status">
            @foreach ($statusOptions as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </x-select>

        <x-slot:trailing>{{ $links->total() }} links</x-slot:trailing>
    </x-filter-bar>

    @if ($budgetProject)
        <x-card
            title="Monthly link plan · {{ $budgetProject->domain }}"
            subtitle="Approved links and spend inside the current calendar month."
            icon="links"
        >
            @if ($canEditBudget)
                <x-slot:actions>
                    <x-button size="sm" variant="ghost" icon="pencil" wire:click="$toggle('editingBudget')">
                        {{ $editingBudget ? 'Close' : 'Edit target' }}
                    </x-button>
                </x-slot:actions>
            @endif

            <div class="grid gap-5 sm:grid-cols-2">
                <x-progress
                    :value="$monthApprovedCount"
                    :max="max(1, (int) $budgetProject->monthly_link_target)"
                    label="Links approved"
                    caption="{{ $monthApprovedCount }} / {{ $budgetProject->monthly_link_target }}"
                />
                <x-progress
                    :value="$monthSpend"
                    :max="max(1, (int) $budgetProject->monthly_link_budget_paisa)"
                    :label="'Budget used ('.\App\Support\Currency::code().')'"
                    caption="{{ \App\Support\Money::rounded((int) $monthSpend) }} / {{ \App\Support\Money::rounded((int) $budgetProject->monthly_link_budget_paisa) }}"
                    :tone="$budgetProject->monthly_link_budget_paisa > 0 && $monthSpend > $budgetProject->monthly_link_budget_paisa ? 'danger' : 'accent'"
                />
            </div>

            @if ($editingBudget && $canEditBudget)
                <form wire:submit="saveBudget" class="mt-5 grid gap-3 border-t border-line pt-5 sm:grid-cols-3">
                    <x-input
                        label="Monthly target"
                        type="number"
                        min="0"
                        wire:model="monthly_link_target"
                        :error="$errors->first('monthly_link_target')"
                        suffix="links"
                    />
                    <x-input
                        label="Monthly budget"
                        type="number"
                        step="0.01"
                        min="0"
                        wire:model="monthly_link_budget"
                        :error="$errors->first('monthly_link_budget')"
                        :suffix="\App\Support\Currency::code()"
                    />
                    <div class="flex items-end">
                        <x-button type="submit" size="sm" variant="secondary" target="saveBudget">Save plan</x-button>
                    </div>
                </form>
            @endif
        </x-card>
    @endif

    <div wire:loading.delay.long.flex wire:target="search,projectFilter,statusFilter" class="hidden">
        <x-skeleton variant="table" class="w-full" :rows="6" :cols="5" />
    </div>

    @if ($links->isEmpty())
        <x-empty-state
            icon="links"
            title="No links logged for this view"
            description="Log a placement with its source URL. Duplicate source domains warn but never block."
        >
            @if ($canCreate)
                <x-button icon="plus" wire:click="create">Log link</x-button>
            @endif
        </x-empty-state>
    @else
        <div wire:loading.class="opacity-60" wire:target="search,projectFilter,statusFilter">
            <x-table :headers="[
                'Source',
                'Target',
                ['label' => 'DR / DA', 'align' => 'right'],
                ['label' => 'Date', 'align' => 'right'],
                ['label' => \App\Support\Currency::code(), 'align' => 'right'],
                'Status',
                ['label' => 'Actions', 'sr' => true, 'align' => 'right', 'width' => 'relative'],
            ]">
                @foreach ($links as $link)
                    <x-table.row wire:key="link-{{ $link->id }}">
                        <x-table.cell>
                            <p class="max-w-[14rem] truncate font-mono text-xs font-medium text-ink">{{ $link->source_domain }}</p>
                            <p class="mt-0.5 max-w-[14rem] truncate text-xs text-muted">{{ $link->project?->domain }} · {{ $link->type->label() }}</p>
                        </x-table.cell>
                        <x-table.cell>
                            <p class="max-w-[12rem] truncate">{{ $link->target_page }}</p>
                            <p class="mt-0.5 max-w-[12rem] truncate text-xs text-muted">“{{ $link->anchor_text }}”</p>
                        </x-table.cell>
                        <x-table.cell numeric muted nowrap>
                            {{ $link->dr ?? '—' }} / {{ $link->da ?? '—' }}
                        </x-table.cell>
                        <x-table.cell numeric muted nowrap>
                            {{ $link->link_date?->format('Y-m-d') ?? '—' }}
                        </x-table.cell>
                        <x-table.cell numeric>
                            <x-money :paisa="$link->cost_paisa" />
                            @if ($link->expense_id)
                                <span class="mt-0.5 block text-[10px] text-success">exp #{{ $link->expense_id }}</span>
                            @endif
                        </x-table.cell>
                        <x-table.cell>
                            <div class="flex flex-col items-start gap-1">
                                <x-badge :tone="match($link->workflow_status->value) {
                                    'approved' => 'success',
                                    'rejected' => 'danger',
                                    'submitted' => 'warn',
                                    default => 'accent',
                                }">{{ $link->workflow_status->label() }}</x-badge>
                                <x-badge size="sm" :tone="$link->live_status->value === 'removed' ? 'warn' : 'neutral'">{{ $link->live_status->label() }}</x-badge>
                            </div>
                        </x-table.cell>
                        <x-table.cell align="right" nowrap>
                            <div class="flex items-center justify-end gap-1">
                                @can('update', $link)
                                    <x-tooltip text="Edit link">
                                        <x-button size="sm" variant="ghost" square icon="pencil" wire:click="edit({{ $link->id }})" aria-label="Edit link {{ $link->source_domain }}" />
                                    </x-tooltip>
                                @endcan
                                @can('submit', $link)
                                    @if (in_array($link->workflow_status->value, ['pending', 'rejected'], true))
                                        <x-button size="sm" variant="secondary" wire:click="submit({{ $link->id }})">Submit</x-button>
                                    @endif
                                @endcan
                                @can('approve', $link)
                                    @if (in_array($link->workflow_status->value, ['submitted', 'pending'], true))
                                        <x-button size="sm" icon="check" wire:click="approve({{ $link->id }})">Approve</x-button>
                                        <x-tooltip text="Reject link">
                                            <x-button size="sm" variant="danger-ghost" square icon="x" wire:click="openReject({{ $link->id }})" aria-label="Reject link {{ $link->source_domain }}" />
                                        </x-tooltip>
                                    @endif
                                @endcan
                            </div>
                        </x-table.cell>
                    </x-table.row>
                @endforeach
            </x-table>
        </div>

        <div>{{ $links->links() }}</div>
    @endif

    <x-modal
        :show="$showForm"
        :title="$editingId ? 'Edit link' : 'Log link'"
        subtitle="Source domain is derived from the URL and checked for duplicates."
        close="cancel"
        size="lg"
    >
        <form id="link-form" wire:submit="save" class="grid gap-4 sm:grid-cols-2">
            <x-select
                label="Project"
                wire:model="project_id"
                placeholder="Select…"
                :error="$errors->first('project_id')"
                class="sm:col-span-2"
                required
            >
                @foreach ($projects as $project)
                    <option value="{{ $project->id }}">{{ $project->domain }}</option>
                @endforeach
            </x-select>

            <x-input label="Source URL" wire:model="source_url" :error="$errors->first('source_url')" class="sm:col-span-2" required />
            <x-input label="Target page" wire:model="target_page" :error="$errors->first('target_page')" required />
            <x-input label="Anchor text" wire:model="anchor_text" :error="$errors->first('anchor_text')" required />

            <x-select label="Type" wire:model="type" :error="$errors->first('type')">
                @foreach ($typeOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </x-select>

            <x-input label="Cost" type="number" step="0.01" min="0" wire:model="cost" :error="$errors->first('cost')" :suffix="\App\Support\Currency::code()" />
            <x-input label="Date" type="date" wire:model="link_date" :error="$errors->first('link_date')" />

            <x-select label="Live status" wire:model="live_status" :error="$errors->first('live_status')">
                @foreach ($liveStatusOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </x-select>

            <div class="sm:col-span-2">
                <x-button type="button" variant="link" size="sm" wire:click="$toggle('showMore')">
                    {{ $showMore ? 'Hide' : 'More' }} details
                </x-button>
            </div>

            @if ($showMore)
                <x-input label="DR" type="number" min="0" max="100" wire:model="dr" :error="$errors->first('dr')" />
                <x-input label="DA" type="number" min="0" max="100" wire:model="da" :error="$errors->first('da')" />
                <x-select label="Assignee" wire:model="assigned_to" placeholder="Unassigned" :error="$errors->first('assigned_to')" class="sm:col-span-2">
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                    @endforeach
                </x-select>
            @endif
        </form>

        <x-slot:footer>
            <x-button variant="ghost" wire:click="cancel">Cancel</x-button>
            <x-button type="submit" form="link-form" target="save">Save link</x-button>
        </x-slot:footer>
    </x-modal>

    <x-modal :show="$showReject" title="Reject link" subtitle="The reason is stored on the link." close="cancel" size="sm">
        <x-textarea
            label="Rejection reason"
            wire:model="rejection_reason"
            rows="4"
            placeholder="Reason required…"
            :error="$errors->first('rejection_reason')"
            required
        />

        <x-slot:footer>
            <x-button variant="ghost" wire:click="cancel">Cancel</x-button>
            <x-button variant="danger" wire:click="reject">Confirm</x-button>
        </x-slot:footer>
    </x-modal>
</div>
