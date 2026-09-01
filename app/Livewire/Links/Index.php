<?php

namespace App\Livewire\Links;

use App\Enums\LinkLiveStatus;
use App\Enums\LinkType;
use App\Enums\LinkWorkflowStatus;
use App\Models\Link;
use App\Models\Project;
use App\Models\User;
use App\Services\LinkWorkflowService;
use App\Support\Money;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Links')]
class Index extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $statusFilter = '';

    #[Url]
    public string $projectFilter = '';

    public bool $showForm = false;

    public bool $showMore = false;

    public ?int $editingId = null;

    public string $project_id = '';

    public string $source_url = '';

    public string $dr = '';

    public string $da = '';

    public string $target_page = '';

    public string $anchor_text = '';

    public string $type = 'guest_post';

    public string $cost = '0';

    public string $link_date = '';

    public string $live_status = 'live';

    public string $assigned_to = '';

    public ?string $domainWarning = null;

    public string $rejection_reason = '';

    public bool $showReject = false;

    public string $monthly_link_target = '0';

    public string $monthly_link_budget = '0';

    public bool $editingBudget = false;

    public function mount(): void
    {
        $this->authorize('viewAny', Link::class);
        if ($this->projectFilter === '' && session()->has('links.last_project_id')) {
            $this->projectFilter = (string) session('links.last_project_id');
        }
        $this->syncBudgetFields();

        // Quick-create deep link (?new=1) opens the form straight away
        if (request()->boolean('new') && Auth::user()?->hasPermission('links.create')) {
            $this->create();
        }
    }

    public function updatedProjectFilter(): void
    {
        $this->resetPage();
        $this->syncBudgetFields();
        if ($this->projectFilter !== '') {
            session(['links.last_project_id' => $this->projectFilter]);
        }
    }

    protected function syncBudgetFields(): void
    {
        if ($this->projectFilter === '') {
            $this->monthly_link_target = '0';
            $this->monthly_link_budget = '0';
            $this->editingBudget = false;

            return;
        }

        $project = Project::query()->find($this->projectFilter);
        if ($project) {
            $this->monthly_link_target = (string) $project->monthly_link_target;
            $this->monthly_link_budget = Money::fromMinor((int) $project->monthly_link_budget_paisa);
        }
    }

    public function saveBudget(): void
    {
        abort_unless(Auth::user()?->hasPermission('links.approve') || Auth::user()?->hasPermission('projects.update'), 403);
        abort_if($this->projectFilter === '', 422);

        $project = Project::query()->findOrFail($this->projectFilter);
        abort_unless(Auth::user()->canAccessProject($project), 403);

        $validated = $this->validate([
            'monthly_link_target' => ['required', 'integer', 'min:0', 'max:100000'],
            'monthly_link_budget' => ['required', 'numeric', 'min:0'],
        ]);

        $project->update([
            'monthly_link_target' => (int) $validated['monthly_link_target'],
            'monthly_link_budget_paisa' => Money::toMinor($validated['monthly_link_budget']),
        ]);

        $this->editingBudget = false;
        $this->dispatch('toast', message: 'Monthly link target & budget saved.', tone: 'success');
    }

    public function create(): void
    {
        $this->authorize('create', Link::class);
        $this->resetForm();
        $this->project_id = $this->projectFilter !== '' ? $this->projectFilter : (string) (session('links.last_project_id') ?? '');
        $this->link_date = now()->toDateString();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $link = Link::query()->findOrFail($id);
        $this->authorize('update', $link);

        $this->editingId = $link->id;
        $this->project_id = (string) $link->project_id;
        $this->source_url = $link->source_url;
        $this->dr = (string) ($link->dr ?? '');
        $this->da = (string) ($link->da ?? '');
        $this->target_page = $link->target_page;
        $this->anchor_text = $link->anchor_text;
        $this->type = $link->type->value;
        $this->cost = Money::fromMinor((int) $link->cost_paisa);
        $this->link_date = $link->link_date?->format('Y-m-d') ?? '';
        $this->live_status = $link->live_status->value;
        $this->assigned_to = (string) ($link->assigned_to ?? '');
        $this->domainWarning = null;
        $this->showForm = true;
        $this->showMore = filled($link->dr) || filled($link->da);
    }

    public function save(LinkWorkflowService $workflow): void
    {
        if ($this->editingId) {
            $link = Link::query()->findOrFail($this->editingId);
            $this->authorize('update', $link);
        } else {
            $this->authorize('create', Link::class);
        }

        $validated = $this->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'source_url' => ['required', 'string', 'max:2048'],
            'dr' => ['nullable', 'integer', 'min:0', 'max:100'],
            'da' => ['nullable', 'integer', 'min:0', 'max:100'],
            'target_page' => ['required', 'string', 'max:2048'],
            'anchor_text' => ['required', 'string', 'max:500'],
            'type' => ['required', Rule::enum(LinkType::class)],
            'cost' => ['required', 'numeric', 'min:0'],
            'link_date' => ['nullable', 'date'],
            'live_status' => ['required', Rule::enum(LinkLiveStatus::class)],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $project = Project::query()->findOrFail($validated['project_id']);
        abort_unless(Auth::user()->canAccessProject($project), 403);

        $data = [
            'project_id' => (int) $validated['project_id'],
            'source_url' => $validated['source_url'],
            'dr' => $validated['dr'] !== null && $validated['dr'] !== '' ? (int) $validated['dr'] : null,
            'da' => $validated['da'] !== null && $validated['da'] !== '' ? (int) $validated['da'] : null,
            'target_page' => $validated['target_page'],
            'anchor_text' => $validated['anchor_text'],
            'type' => $validated['type'],
            'cost_paisa' => Money::toMinor($validated['cost']),
            'link_date' => $validated['link_date'] ?: null,
            'live_status' => $validated['live_status'],
            'assigned_to' => $validated['assigned_to'] ?: null,
        ];

        if ($this->editingId) {
            $result = $workflow->update(Link::query()->findOrFail($this->editingId), $data);
        } else {
            $result = $workflow->create($data, Auth::user());
        }

        $this->domainWarning = $result['domain_warning'];
        session(['links.last_project_id' => $validated['project_id']]);

        if ($this->domainWarning) {
            $this->dispatch('toast', message: 'Link saved. '.$this->domainWarning, tone: 'success');
        } else {
            $this->dispatch('toast', message: 'Link saved.', tone: 'success');
        }

        $this->showForm = false;
        $this->resetForm();
    }

    public function submit(int $id, LinkWorkflowService $workflow): void
    {
        $link = Link::query()->findOrFail($id);
        $this->authorize('submit', $link);

        try {
            $workflow->submit($link);
            $this->dispatch('toast', message: 'Link submitted for approval.', tone: 'success');
        } catch (ValidationException $e) {
            $this->dispatch('toast', message: collect($e->errors())->flatten()->first(), tone: 'danger');
        }
    }

    public function approve(int $id, LinkWorkflowService $workflow): void
    {
        $link = Link::query()->findOrFail($id);
        $this->authorize('approve', $link);

        try {
            $workflow->approve($link, Auth::user());
            $this->dispatch('toast', message: 'Link approved. Cost posted as expense.', tone: 'success');
        } catch (ValidationException $e) {
            $this->dispatch('toast', message: collect($e->errors())->flatten()->first(), tone: 'danger');
        }
    }

    public function openReject(int $id): void
    {
        $link = Link::query()->findOrFail($id);
        $this->authorize('approve', $link);
        $this->editingId = $id;
        $this->rejection_reason = '';
        $this->showReject = true;
    }

    public function reject(LinkWorkflowService $workflow): void
    {
        $link = Link::query()->findOrFail($this->editingId);
        $this->authorize('approve', $link);

        try {
            $workflow->reject($link, $this->rejection_reason);
            $this->showReject = false;
            $this->editingId = null;
            $this->dispatch('toast', message: 'Link rejected.', tone: 'success');
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                foreach ($messages as $message) {
                    $this->addError($key, $message);
                }
            }
        }
    }

    public function cancel(): void
    {
        $this->showForm = false;
        $this->showReject = false;
        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->editingId = null;
        $this->project_id = '';
        $this->source_url = '';
        $this->dr = '';
        $this->da = '';
        $this->target_page = '';
        $this->anchor_text = '';
        $this->type = LinkType::GuestPost->value;
        $this->cost = '0';
        $this->link_date = '';
        $this->live_status = LinkLiveStatus::Live->value;
        $this->assigned_to = '';
        $this->domainWarning = null;
        $this->showMore = false;
        $this->resetValidation();
    }

    public function render()
    {
        $user = Auth::user();

        $links = Link::query()
            ->with(['project', 'assignee', 'expense'])
            ->accessibleBy($user)
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('source_url', 'like', $term)
                        ->orWhere('source_domain', 'like', $term)
                        ->orWhere('target_page', 'like', $term)
                        ->orWhere('anchor_text', 'like', $term);
                });
            })
            ->when($this->statusFilter !== '', fn ($q) => $q->where('workflow_status', $this->statusFilter))
            ->when($this->projectFilter !== '', fn ($q) => $q->where('project_id', $this->projectFilter))
            ->orderByRaw("CASE workflow_status WHEN 'submitted' THEN 0 WHEN 'rejected' THEN 1 WHEN 'pending' THEN 2 ELSE 3 END")
            ->orderByDesc('id')
            ->paginate(20);

        $monthApprovedCount = 0;
        $monthSpend = 0;
        $budgetProject = null;
        // Scope the budget lookup: `$projectFilter` comes from the query string, so an
        // unscoped find would report another project's link count and spend.
        if ($this->projectFilter !== '') {
            $budgetProject = Project::query()->accessibleBy($user)->find($this->projectFilter);
        }

        if ($budgetProject) {
            $totals = Link::query()
                ->where('project_id', $budgetProject->id)
                ->where('workflow_status', LinkWorkflowStatus::Approved->value)
                ->whereBetween('link_date', [
                    now('UTC')->startOfMonth()->toDateString(),
                    now('UTC')->endOfMonth()->toDateString(),
                ])
                ->selectRaw('COUNT(*) as approved_count, COALESCE(SUM(cost_paisa), 0) as spend_paisa')
                ->first();

            $monthApprovedCount = (int) $totals->approved_count;
            $monthSpend = (int) $totals->spend_paisa;
        }

        return view('livewire.links.index', [
            'links' => $links,
            'statusOptions' => LinkWorkflowStatus::options(),
            'liveStatusOptions' => LinkLiveStatus::options(),
            'typeOptions' => LinkType::options(),
            'projects' => Project::query()->accessibleBy($user)->orderBy('domain')->get(),
            'users' => User::query()->where('is_active', true)->orderBy('name')->get(),
            'canCreate' => $user->hasPermission('links.create'),
            'canApprove' => $user->hasPermission('links.approve'),
            'canEditBudget' => $user->hasPermission('links.approve') || $user->hasPermission('projects.update'),
            'monthApprovedCount' => $monthApprovedCount,
            'monthSpend' => $monthSpend,
            'budgetProject' => $budgetProject,
        ]);
    }
}
