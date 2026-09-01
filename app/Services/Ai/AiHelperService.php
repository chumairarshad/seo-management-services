<?php

namespace App\Services\Ai;

use App\Models\Article;
use App\Models\Project;
use App\Models\User;
use App\Support\AiAvailability;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Small helpers: meta suggestions, rejection summaries, task briefs.
 */
class AiHelperService
{
    public function __construct(
        protected AiHttpClient $client,
        protected AiBudgetService $budget,
        protected SafePayloadSanitizer $sanitizer,
        protected WhitelistReportService $reports,
        protected MessageBuilder $messages,
    ) {}

    /**
     * @return array{answer: string, source_figures: array<string, mixed>, report_key: string, cached: bool, ai_generated: true}
     */
    public function suggestMeta(User $user, ?int $projectId, ?int $articleId, ?string $title, ?string $notes): array
    {
        $this->guard();
        $this->budget->assertCanSpend();

        $context = $this->buildMetaContext($user, $projectId, $articleId, $title, $notes);
        $cacheKey = $this->cacheKey('meta', $user->id, $context);
        $ttl = AiAvailability::cacheTtl();

        if ($ttl > 0 && Cache::has($cacheKey)) {
            $this->budget->log($user, 'helper_meta', 'meta', 0, 0, true, true, null, ['cache' => true]);
            /** @var array{answer: string, source_figures: array<string, mixed>, report_key: string, cached: bool, ai_generated: true} $hit */
            $hit = Cache::get($cacheKey);
            $hit['cached'] = true;

            return $hit;
        }

        $payload = $this->messages->buildHelperMessages('meta', $context);
        try {
            $result = $this->client->chat($payload['messages'], 0.4, 400);
            $this->budget->log($user, 'helper_meta', 'meta', $result['prompt_tokens'], $result['completion_tokens'], false, true, null, [
                'project_id' => $context['project_id'] ?? null,
            ]);
            $text = $result['text'] !== '' ? $result['text'] : $this->fallbackMeta($context);
        } catch (RuntimeException $e) {
            $this->budget->log($user, 'helper_meta', 'meta', 0, 0, false, false, $e->getMessage());
            $text = $this->fallbackMeta($context);
        }

        $out = [
            'answer' => $text,
            'source_figures' => $this->sanitizer->sanitize($context),
            'report_key' => 'meta',
            'cached' => false,
            'ai_generated' => true,
        ];

        if ($ttl > 0) {
            Cache::put($cacheKey, $out, $ttl);
        }

        return $out;
    }

    /**
     * @return array{answer: string, source_figures: array<string, mixed>, report_key: string, cached: bool, ai_generated: true}
     */
    public function summariseRejections(User $user): array
    {
        $this->guard();
        $this->budget->assertCanSpend();

        $reasons = $this->reports->recentRejectionReasons($user);
        $context = ['rejection_reasons' => $reasons, 'count' => count($reasons)];
        $cacheKey = $this->cacheKey('rejections', $user->id, $context);
        $ttl = AiAvailability::cacheTtl();

        if ($ttl > 0 && Cache::has($cacheKey)) {
            $this->budget->log($user, 'helper_reject', 'rejections', 0, 0, true);
            $hit = Cache::get($cacheKey);
            $hit['cached'] = true;

            return $hit;
        }

        $payload = $this->messages->buildHelperMessages('rejections', $context);
        try {
            $result = $this->client->chat($payload['messages'], 0.2, 500);
            $this->budget->log($user, 'helper_reject', 'rejections', $result['prompt_tokens'], $result['completion_tokens']);
            $text = $result['text'] !== '' ? $result['text'] : $this->fallbackRejections($reasons);
        } catch (RuntimeException $e) {
            $this->budget->log($user, 'helper_reject', 'rejections', 0, 0, false, false, $e->getMessage());
            $text = $this->fallbackRejections($reasons);
        }

        $out = [
            'answer' => $text,
            'source_figures' => $this->sanitizer->sanitize($context),
            'report_key' => 'rejections',
            'cached' => false,
            'ai_generated' => true,
        ];

        if ($ttl > 0) {
            Cache::put($cacheKey, $out, $ttl);
        }

        return $out;
    }

    /**
     * @return array{answer: string, source_figures: array<string, mixed>, report_key: string, cached: bool, ai_generated: true}
     */
    public function draftTaskBrief(User $user, string $title, ?string $notes = null, ?int $projectId = null): array
    {
        $this->guard();
        $this->budget->assertCanSpend();

        $projectDomain = null;
        if ($projectId) {
            $project = Project::query()->accessibleBy($user)->whereKey($projectId)->first();
            $projectDomain = $project?->domain;
        }

        $context = [
            'title' => $title,
            'notes' => $notes,
            'project_domain' => $projectDomain,
        ];
        $cacheKey = $this->cacheKey('task_brief', $user->id, $context);
        $ttl = AiAvailability::cacheTtl();

        if ($ttl > 0 && Cache::has($cacheKey)) {
            $this->budget->log($user, 'helper_brief', 'task_brief', 0, 0, true);
            $hit = Cache::get($cacheKey);
            $hit['cached'] = true;

            return $hit;
        }

        $payload = $this->messages->buildHelperMessages('task_brief', $context);
        try {
            $result = $this->client->chat($payload['messages'], 0.5, 500);
            $this->budget->log($user, 'helper_brief', 'task_brief', $result['prompt_tokens'], $result['completion_tokens']);
            $text = $result['text'] !== '' ? $result['text'] : $this->fallbackBrief($context);
        } catch (RuntimeException $e) {
            $this->budget->log($user, 'helper_brief', 'task_brief', 0, 0, false, false, $e->getMessage());
            $text = $this->fallbackBrief($context);
        }

        $out = [
            'answer' => $text,
            'source_figures' => $this->sanitizer->sanitize($context),
            'report_key' => 'task_brief',
            'cached' => false,
            'ai_generated' => true,
        ];

        if ($ttl > 0) {
            Cache::put($cacheKey, $out, $ttl);
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildMetaContext(User $user, ?int $projectId, ?int $articleId, ?string $title, ?string $notes): array
    {
        $context = [
            'title' => $title,
            'notes' => $notes,
            'project_id' => null,
            'project_domain' => null,
            'niche' => null,
            'article_title' => null,
            'target_keyword' => null,
        ];

        if ($projectId) {
            $project = Project::query()->accessibleBy($user)->whereKey($projectId)->first();
            if ($project) {
                $context['project_id'] = $project->id;
                $context['project_domain'] = $project->domain;
                $context['niche'] = $project->niche;
            }
        }

        if ($articleId && $user->hasPermission('articles.view')) {
            $article = Article::query()->accessibleBy($user)->whereKey($articleId)->first();
            if ($article) {
                $context['article_title'] = $article->title;
                $context['target_keyword'] = $article->target_keyword;
                $context['title'] = $context['title'] ?: $article->title;
                if (! $context['project_domain'] && $article->project_id) {
                    $proj = Project::query()->accessibleBy($user)->whereKey($article->project_id)->first();
                    $context['project_domain'] = $proj?->domain;
                    $context['niche'] = $proj?->niche;
                }
            }
        }

        return $context;
    }

    protected function guard(): void
    {
        if (! AiAvailability::enabled()) {
            throw new RuntimeException('AI is not enabled.');
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function cacheKey(string $feature, int $userId, array $context): string
    {
        return 'ai:helper:'.$feature.':'.$userId.':'.sha1(json_encode($this->sanitizer->sanitize($context)));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function fallbackMeta(array $context): string
    {
        $title = trim((string) ($context['title'] ?: $context['article_title'] ?: $context['project_domain'] ?: 'Content'));
        $keyword = (string) ($context['target_keyword'] ?? '');
        $domain = (string) ($context['project_domain'] ?? '');
        $metaTitle = mb_substr($title.($keyword ? ' | '.$keyword : '').($domain ? ' — '.$domain : ''), 0, 60);
        $metaDesc = mb_substr(
            'Learn about '.$title.($keyword ? ' focusing on '.$keyword : '').($domain ? ' on '.$domain : '').'.',
            0,
            155
        );

        return "Meta title: {$metaTitle}\nMeta description: {$metaDesc}";
    }

    /**
     * @param  list<array{type: string, title: string, reason: string, when: string|null}>  $reasons
     */
    protected function fallbackRejections(array $reasons): string
    {
        if ($reasons === []) {
            return 'No recent rejection reasons found in your scope.';
        }

        $themes = collect($reasons)->pluck('reason')->map(fn ($r) => mb_strtolower(trim($r)))->filter()->countBy();
        $top = $themes->sortDesc()->take(5);

        $lines = ['Themes in recent rejections (count):'];
        foreach ($top as $reason => $count) {
            $lines[] = "- ({$count}) {$reason}";
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function fallbackBrief(array $context): string
    {
        $title = (string) ($context['title'] ?? 'Task');
        $notes = trim((string) ($context['notes'] ?? ''));
        $domain = (string) ($context['project_domain'] ?? 'the assigned project');

        return "Task brief: {$title}\n\n"
            ."Project context: {$domain}\n"
            ."Objective: Complete “{$title}” to the quality bar used for similar work on {$domain}.\n"
            .'Inputs: '.($notes !== '' ? $notes : 'None provided — confirm requirements with the requester.')."\n"
            ."Deliverables: Concrete output matching the title; leave notes for the reviewer.\n"
            .'Out of scope: Credential vault access, production credentials, or payout details.';
    }
}
