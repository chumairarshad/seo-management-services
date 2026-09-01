<?php

namespace App\Livewire\Approvals;

use App\Models\Article;
use App\Models\Link;
use App\Models\Task;
use App\Services\ArticleWorkflowService;
use App\Services\LinkWorkflowService;
use App\Services\TaskWorkflowService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Approval queue')]
class Queue extends Component
{
    use AuthorizesRequests;

    #[Locked]
    public int $cursor = 0;

    public string $rejection_reason = '';

    public bool $showReject = false;

    /**
     * The queue, built server-side from what this user may approve. Locked so a
     * client cannot inject ids for work on projects they are not assigned to.
     *
     * @var array<int, array{type: string, id: int}>
     */
    #[Locked]
    public array $queueKeys = [];

    public function mount(): void
    {
        abort_unless(
            Auth::user()?->hasAnyPermission('tasks.approve', 'articles.approve', 'links.approve'),
            403,
        );

        $this->refreshQueue();
    }

    public function refreshQueue(): void
    {
        $user = Auth::user();
        $items = collect();

        if ($user->hasPermission('tasks.approve')) {
            Task::query()
                ->with(['project', 'assignee', 'media'])
                ->accessibleBy($user)
                ->awaitingApproval()
                ->orderBy('submitted_at')
                ->orderBy('id')
                ->get()
                ->each(function (Task $task) use ($items) {
                    $items->push([
                        'type' => 'task',
                        'id' => $task->id,
                        'sort' => $task->submitted_at?->timestamp ?? 0,
                    ]);
                });
        }

        if ($user->hasPermission('articles.approve')) {
            Article::query()
                ->with(['project', 'writer'])
                ->accessibleBy($user)
                ->awaitingApproval()
                ->orderBy('submitted_at')
                ->orderBy('id')
                ->get()
                ->each(function (Article $article) use ($items) {
                    $items->push([
                        'type' => 'article',
                        'id' => $article->id,
                        'sort' => $article->submitted_at?->timestamp ?? 0,
                    ]);
                });
        }

        if ($user->hasPermission('links.approve')) {
            Link::query()
                ->with(['project', 'assignee'])
                ->accessibleBy($user)
                ->awaitingApproval()
                ->orderBy('submitted_at')
                ->orderBy('id')
                ->get()
                ->each(function (Link $link) use ($items) {
                    $items->push([
                        'type' => 'link',
                        'id' => $link->id,
                        'sort' => $link->submitted_at?->timestamp ?? 0,
                    ]);
                });
        }

        $this->queueKeys = $items->sortBy('sort')->values()->map(fn ($i) => [
            'type' => $i['type'],
            'id' => $i['id'],
        ])->all();

        if ($this->cursor >= count($this->queueKeys)) {
            $this->cursor = max(0, count($this->queueKeys) - 1);
        }

        $this->showReject = false;
        $this->rejection_reason = '';
    }

    public function next(): void
    {
        if ($this->cursor < count($this->queueKeys) - 1) {
            $this->cursor++;
        }
        $this->showReject = false;
        $this->rejection_reason = '';
    }

    public function prev(): void
    {
        if ($this->cursor > 0) {
            $this->cursor--;
        }
        $this->showReject = false;
        $this->rejection_reason = '';
    }

    public function approve(
        TaskWorkflowService $tasks,
        ArticleWorkflowService $articles,
        LinkWorkflowService $links,
    ): void {
        $item = $this->currentItem();
        if (! $item) {
            return;
        }

        try {
            match ($item['type']) {
                'task' => $this->approveTask($tasks, $item['model']),
                'article' => $this->approveArticle($articles, $item['model']),
                'link' => $this->approveLink($links, $item['model']),
            };
        } catch (ValidationException $e) {
            $this->addError('status', collect($e->errors())->flatten()->first() ?? 'Cannot approve.');

            return;
        }

        $this->refreshQueue();
        $this->dispatch('toast', message: 'Approved. Next item loaded.', tone: 'success');
    }

    public function openReject(): void
    {
        if (! $this->currentItem()) {
            return;
        }
        $this->showReject = true;
        $this->rejection_reason = '';
    }

    public function reject(
        TaskWorkflowService $tasks,
        ArticleWorkflowService $articles,
        LinkWorkflowService $links,
    ): void {
        $item = $this->currentItem();
        if (! $item) {
            return;
        }

        try {
            match ($item['type']) {
                'task' => $this->rejectTask($tasks, $item['model']),
                'article' => $this->rejectArticle($articles, $item['model']),
                'link' => $this->rejectLink($links, $item['model']),
            };
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                foreach ($messages as $message) {
                    $this->addError($key === 'revision_notes' ? 'rejection_reason' : $key, $message);
                }
            }

            return;
        }

        $this->refreshQueue();
        $this->dispatch('toast', message: 'Rejected. Next item loaded.', tone: 'success');
    }

    protected function approveTask(TaskWorkflowService $workflow, Task $task): void
    {
        $this->authorize('approve', $task);
        $workflow->approve($task, Auth::user());
    }

    protected function rejectTask(TaskWorkflowService $workflow, Task $task): void
    {
        $this->authorize('approve', $task);
        $workflow->reject($task, Auth::user(), $this->rejection_reason);
    }

    protected function approveArticle(ArticleWorkflowService $workflow, Article $article): void
    {
        $this->authorize('approve', $article);
        $workflow->approve($article, Auth::user());
    }

    protected function rejectArticle(ArticleWorkflowService $workflow, Article $article): void
    {
        $this->authorize('approve', $article);
        $workflow->requestRevision($article, $this->rejection_reason);
    }

    protected function approveLink(LinkWorkflowService $workflow, Link $link): void
    {
        $this->authorize('approve', $link);
        $workflow->approve($link, Auth::user());
    }

    protected function rejectLink(LinkWorkflowService $workflow, Link $link): void
    {
        $this->authorize('approve', $link);
        $workflow->reject($link, $this->rejection_reason);
    }

    /**
     * @return array{type: string, id: int, model: Task|Article|Link}|null
     */
    protected function currentItem(): ?array
    {
        if ($this->queueKeys === [] || ! isset($this->queueKeys[$this->cursor])) {
            return null;
        }

        $key = $this->queueKeys[$this->cursor];
        $user = Auth::user();

        // Scope the fetch as well as the queue build: the item is rendered in full,
        // so an id that has drifted out of scope must not resolve.
        $model = match ($key['type']) {
            'task' => Task::query()->with(['project', 'assignee', 'media', 'comments.user'])
                ->accessibleBy($user)->find($key['id']),
            'article' => Article::query()->with(['project', 'writer'])
                ->accessibleBy($user)->find($key['id']),
            'link' => Link::query()->with(['project', 'assignee'])
                ->accessibleBy($user)->find($key['id']),
            default => null,
        };

        if (! $model) {
            return null;
        }

        return [
            'type' => $key['type'],
            'id' => $key['id'],
            'model' => $model,
        ];
    }

    public function render()
    {
        $current = $this->currentItem();

        return view('livewire.approvals.queue', [
            'current' => $current,
            'queueCount' => count($this->queueKeys),
            'position' => count($this->queueKeys) === 0 ? 0 : $this->cursor + 1,
        ]);
    }
}
