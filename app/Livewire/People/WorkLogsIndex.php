<?php

namespace App\Livewire\People;

use App\Models\WorkLog;
use App\Services\PeopleVisibilityService;
use App\Support\DisplayTimezone;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Work logs')]
class WorkLogsIndex extends Component
{
    use WithPagination;

    #[Url]
    public ?int $userId = null;

    public string $body = '';

    public string $taskIdsInput = '';

    public string $articleIdsInput = '';

    public string $linkIdsInput = '';

    public string $logDate = '';

    public function mount(): void
    {
        abort_unless(Auth::user()?->hasPermission('work_logs.view'), 403);

        $this->userId ??= Auth::id();
        $this->logDate = DisplayTimezone::today();
        $this->loadExistingLog();
    }

    public function updatedUserId(): void
    {
        $this->resetPage();
        $this->loadExistingLog();
    }

    public function updatedLogDate(): void
    {
        $this->loadExistingLog();
    }

    protected function loadExistingLog(): void
    {
        $viewer = Auth::user();
        if (! $viewer || ! $this->userId) {
            return;
        }

        // Staff may only edit own logs
        if ((int) $this->userId !== (int) $viewer->id && ! $viewer->hasPermission('work_logs.manage')) {
            return;
        }

        if ((int) $this->userId !== (int) $viewer->id) {
            // Viewing someone else: don't load into editor (viewer only writes own unless admin)
            if (! $viewer->isAdmin()) {
                $this->body = '';
                $this->taskIdsInput = '';
                $this->articleIdsInput = '';
                $this->linkIdsInput = '';

                return;
            }
        }

        $log = WorkLog::query()
            ->where('user_id', $this->userId)
            ->whereDate('local_date', $this->logDate ?: DisplayTimezone::today())
            ->first();

        if ($log) {
            $this->body = $log->body;
            $this->taskIdsInput = implode(', ', $log->task_ids ?? []);
            $this->articleIdsInput = implode(', ', $log->article_ids ?? []);
            $this->linkIdsInput = implode(', ', $log->link_ids ?? []);
        } else {
            $this->body = '';
            $this->taskIdsInput = '';
            $this->articleIdsInput = '';
            $this->linkIdsInput = '';
        }
    }

    public function save(): void
    {
        $user = Auth::user();
        abort_unless($user?->hasPermission('work_logs.manage'), 403);

        // Everyone with manage writes their own log only (simpler staff UX)
        $ownerId = $user->id;

        $validated = $this->validate([
            'logDate' => ['required', 'date'],
            'body' => ['required', 'string', 'max:5000'],
            'taskIdsInput' => ['nullable', 'string', 'max:255'],
            'articleIdsInput' => ['nullable', 'string', 'max:255'],
            'linkIdsInput' => ['nullable', 'string', 'max:255'],
        ]);

        WorkLog::query()->updateOrCreate(
            [
                'user_id' => $ownerId,
                'local_date' => $validated['logDate'],
            ],
            [
                'body' => $validated['body'],
                'task_ids' => $this->parseIds($validated['taskIdsInput'] ?? ''),
                'article_ids' => $this->parseIds($validated['articleIdsInput'] ?? ''),
                'link_ids' => $this->parseIds($validated['linkIdsInput'] ?? ''),
            ],
        );

        $this->userId = $ownerId;
        $this->dispatch('toast', message: 'Work log saved.', tone: 'success');
    }

    /**
     * @return array<int, int>
     */
    protected function parseIds(string $raw): array
    {
        if (trim($raw) === '') {
            return [];
        }

        return collect(explode(',', $raw))
            ->map(fn ($v) => (int) trim($v))
            ->filter(fn ($v) => $v > 0)
            ->unique()
            ->values()
            ->all();
    }

    public function render(PeopleVisibilityService $visibility)
    {
        $viewer = Auth::user();
        $viewable = $visibility->viewableUsers($viewer);

        $filterId = $this->userId;
        if ($filterId && ! $viewable->pluck('id')->contains($filterId)) {
            $filterId = $viewer->id;
            $this->userId = $viewer->id;
        }

        $logs = WorkLog::query()
            ->with('user')
            ->when(
                $filterId,
                fn ($q) => $q->where('user_id', $filterId),
                fn ($q) => $q->whereIn('user_id', $viewable->pluck('id')->all() ?: [0]),
            )
            ->orderByDesc('local_date')
            ->paginate(20);

        return view('livewire.people.work-logs', [
            'logs' => $logs,
            'viewableUsers' => $viewable,
            'canWrite' => (bool) $viewer?->hasPermission('work_logs.manage'),
            'canFilterUsers' => $viewable->count() > 1,
            'tz' => DisplayTimezone::name(),
        ]);
    }
}
