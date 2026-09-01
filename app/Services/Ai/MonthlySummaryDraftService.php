<?php

namespace App\Services\Ai;

use App\Models\AiDraftNote;
use App\Models\User;
use App\Services\ScorecardService;
use App\Support\AiAvailability;
use App\Support\DisplayTimezone;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Monthly portfolio / employee performance draft notes + anomaly flags.
 * Stored for human review — no auto-email requiring a live LLM in tests.
 */
class MonthlySummaryDraftService
{
    public function __construct(
        protected WhitelistReportService $reports,
        protected MessageBuilder $messages,
        protected AiHttpClient $client,
        protected AiBudgetService $budget,
        protected SafePayloadSanitizer $sanitizer,
    ) {}

    /**
     * @return array{created: int, skipped: int}
     */
    public function draftForPeriod(?string $period = null, bool $useLlm = true): array
    {
        if (! AiAvailability::enabled()) {
            throw new RuntimeException('AI is not enabled.');
        }

        $period = $period ?: DisplayTimezone::now()->subMonthNoOverflow()->format('Y-m');
        $created = 0;
        $skipped = 0;

        // Admin-scoped portfolio summary uses first admin, then falls back system-like with system user null
        $admin = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'admin'))
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if ($admin) {
            // Temporarily prefer current calendar math reports (service uses "now"); force via reports as-is.
            $portfolio = $this->reports->run('portfolio_summary', $admin);
            $drops = $this->reports->run('revenue_drops', $admin);
            $expenses = $this->reports->run('expense_spikes', $admin);

            $created += $this->upsertNote(
                AiDraftNote::TYPE_PORTFOLIO_MONTHLY,
                $period,
                null,
                null,
                'Portfolio monthly draft — '.$period,
                $this->narrate('portfolio_monthly', $portfolio, $useLlm, $admin),
                $this->sanitizer->sanitize([
                    'portfolio' => $portfolio['figures'] ?? [],
                    'revenue_drops_count' => count($drops['rows'] ?? []),
                    'expense_spikes_count' => count($expenses['rows'] ?? []),
                ]),
            ) ? 1 : 0;

            foreach (array_slice($drops['rows'] ?? [], 0, 10) as $row) {
                $ok = $this->upsertNote(
                    AiDraftNote::TYPE_ANOMALY_REVENUE,
                    $period,
                    null,
                    $row['project_id'] ?? null,
                    'Revenue drop: '.($row['domain'] ?? 'site').' — '.$period,
                    'Flagged revenue drop for human review. '.$row['domain']
                        .' this month '.$row['this_month_revenue']
                        .' vs prior '.$row['prior_month_revenue']
                        .' ('.$row['delta_pct'].'%).',
                    $this->sanitizer->sanitize($row),
                );
                $created += $ok ? 1 : 0;
                $skipped += $ok ? 0 : 1;
            }

            foreach (array_slice($expenses['rows'] ?? [], 0, 10) as $row) {
                $ok = $this->upsertNote(
                    AiDraftNote::TYPE_ANOMALY_EXPENSE,
                    $period,
                    null,
                    null,
                    'Expense spike: '.($row['description'] ?? 'expense').' — '.$period,
                    'Flagged expense for human review. Amount '.$row['amount']
                        .' on '.($row['date'] ?? 'n/a').' ('.$row['flag'].').',
                    $this->sanitizer->sanitize($row),
                );
                $created += $ok ? 1 : 0;
                $skipped += $ok ? 0 : 1;
            }

            // Employee performance drafts for active staff/supervisors admin can see
            $subjects = User::query()
                ->where('is_active', true)
                ->whereHas('roles', fn ($q) => $q->whereIn('name', ['staff', 'supervisor']))
                ->orderBy('name')
                ->limit(25)
                ->get();

            foreach ($subjects as $subject) {
                $card = $this->reports->run('scorecard', $admin);
                // filter to subject if present
                $row = collect($card['rows'] ?? [])->firstWhere('user_id', $subject->id)
                    ?? [
                        'user_name' => $subject->name,
                        'tasks_completed' => 0,
                    ];
                $own = app(ScorecardService::class)->forUserMonth($subject, $period);
                unset($own['pay_rates']);
                $body = $this->narrate('employee_monthly', [
                    'key' => 'scorecard',
                    'title' => 'Employee '.$subject->name,
                    'figures' => [
                        'user' => $subject->name,
                        'tasks' => $own['tasks'] ?? [],
                        'articles' => $own['articles'] ?? [],
                        'links' => $own['links'] ?? [],
                    ],
                    'rows' => [],
                    'narrative_seed' => $subject->name.' completed '.($own['tasks']['completed'] ?? 0).' tasks in '.$period.'.',
                ], $useLlm, $admin);

                $ok = $this->upsertNote(
                    AiDraftNote::TYPE_EMPLOYEE_MONTHLY,
                    $period,
                    $subject->id,
                    null,
                    'Performance draft — '.$subject->name.' — '.$period,
                    $body,
                    $this->sanitizer->sanitize([
                        'user_id' => $subject->id,
                        'user_name' => $subject->name,
                        'tasks' => $own['tasks'] ?? [],
                        'articles_approved' => $own['articles']['approved'] ?? 0,
                        'links_approved' => $own['links']['approved'] ?? 0,
                    ]),
                );
                $created += $ok ? 1 : 0;
                $skipped += $ok ? 0 : 1;
            }
        } else {
            Log::info('ai.monthly_summary: no admin user; skipped drafts');
        }

        return compact('created', 'skipped');
    }

    /**
     * @param  array<string, mixed>  $report
     * @param  array<string, mixed>|null  $figures
     */
    protected function narrate(string $kind, array $report, bool $useLlm, ?User $actor): string
    {
        $seed = (string) ($report['narrative_seed'] ?? 'Draft review note.');
        $figuresJson = json_encode($this->sanitizer->sanitize($report['figures'] ?? []), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $fallback = "[Draft for human review]\n\n{$seed}\n\nFigures:\n{$figuresJson}";

        if (! $useLlm || ! AiAvailability::enabled() || ! $this->budget->canSpend()) {
            return $fallback;
        }

        try {
            $built = $this->messages->buildMonthlySummaryMessages($kind, $report);
            $result = $this->client->chat($built['messages'], 0.3, 600);
            $this->budget->log($actor, 'monthly_summary', $kind, $result['prompt_tokens'], $result['completion_tokens']);

            return $result['text'] !== '' ? $result['text'] : $fallback;
        } catch (RuntimeException $e) {
            $this->budget->log($actor, 'monthly_summary', $kind, 0, 0, false, false, $e->getMessage());

            return $fallback;
        }
    }

    /**
     * @param  array<string, mixed>|null  $figures
     */
    protected function upsertNote(
        string $type,
        string $period,
        ?int $userId,
        ?int $projectId,
        string $title,
        string $body,
        ?array $figures,
    ): bool {
        $existing = AiDraftNote::query()
            ->where('type', $type)
            ->where('period', $period)
            ->when($userId === null, fn ($q) => $q->whereNull('user_id'), fn ($q) => $q->where('user_id', $userId))
            ->when($projectId === null, fn ($q) => $q->whereNull('project_id'), fn ($q) => $q->where('project_id', $projectId))
            ->where('status', AiDraftNote::STATUS_DRAFT)
            ->first();

        if ($existing) {
            $existing->update([
                'title' => $title,
                'body' => $body,
                'source_figures' => $figures,
            ]);

            return true;
        }

        AiDraftNote::query()->create([
            'type' => $type,
            'period' => $period,
            'user_id' => $userId,
            'project_id' => $projectId,
            'title' => $title,
            'body' => $body,
            'source_figures' => $figures,
            'status' => AiDraftNote::STATUS_DRAFT,
        ]);

        return true;
    }
}
