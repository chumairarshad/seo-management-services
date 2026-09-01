<?php

namespace App\Services;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ArticleWorkflowService
{
    public function __construct(
        protected ExpenseService $expenses,
    ) {}

    public function create(array $data, User $actor): Article
    {
        $this->assertUniqueKeyword((int) $data['project_id'], (string) $data['target_keyword']);

        return Article::query()->create([
            ...$data,
            'status' => $data['writer_id'] ? ArticleStatus::Assigned : ArticleStatus::Brief,
            'created_by' => $actor->id,
        ]);
    }

    public function update(Article $article, array $data): Article
    {
        if (isset($data['target_keyword']) || isset($data['project_id'])) {
            $this->assertUniqueKeyword(
                (int) ($data['project_id'] ?? $article->project_id),
                (string) ($data['target_keyword'] ?? $article->target_keyword),
                $article->id,
            );
        }

        // Re-assign elevates brief → assigned
        if (array_key_exists('writer_id', $data) && $data['writer_id'] && $article->status === ArticleStatus::Brief) {
            $data['status'] = ArticleStatus::Assigned;
        }

        $article->update($data);

        return $article->fresh();
    }

    public function submitDraft(Article $article): Article
    {
        $this->assertTransition($article, [ArticleStatus::Assigned, ArticleStatus::RevisionRequested, ArticleStatus::Brief], ArticleStatus::DraftSubmitted);

        $article->update([
            'status' => ArticleStatus::DraftSubmitted,
            'submitted_at' => now(),
            'revision_notes' => null,
        ]);

        return $article->fresh();
    }

    public function approve(Article $article, User $actor): Article
    {
        return DB::transaction(function () use ($article, $actor) {
            $this->assertTransition($article, [ArticleStatus::DraftSubmitted], ArticleStatus::Approved);

            $article->update([
                'status' => ArticleStatus::Approved,
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'revision_notes' => null,
            ]);

            $article = $article->fresh();
            $this->expenses->createFromArticleApproval($article, $actor);

            return $article->fresh();
        });
    }

    public function requestRevision(Article $article, string $notes): Article
    {
        $notes = trim($notes);
        if ($notes === '') {
            throw ValidationException::withMessages([
                'revision_notes' => 'A revision reason is required.',
            ]);
        }

        $this->assertTransition($article, [ArticleStatus::DraftSubmitted], ArticleStatus::RevisionRequested);

        $article->update([
            'status' => ArticleStatus::RevisionRequested,
            'revision_notes' => $notes,
            'approved_by' => null,
            'approved_at' => null,
        ]);

        return $article->fresh();
    }

    public function assertUniqueKeyword(int $projectId, string $keyword, ?int $ignoreId = null): void
    {
        $normalized = mb_strtolower(trim($keyword));

        $query = Article::query()
            ->where('project_id', $projectId)
            ->whereRaw('LOWER(target_keyword) = ?', [$normalized]);

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'target_keyword' => 'This keyword is already assigned on this project.',
            ]);
        }
    }

    /**
     * @param  array<int, ArticleStatus>  $from
     */
    protected function assertTransition(Article $article, array $from, ArticleStatus $to): void
    {
        if (! in_array($article->status, $from, true)) {
            throw ValidationException::withMessages([
                'status' => "Cannot move article from {$article->status->label()} to {$to->label()}.",
            ]);
        }
    }
}
