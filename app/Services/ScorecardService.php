<?php

namespace App\Services;

use App\Enums\ArticleStatus;
use App\Enums\LinkWorkflowStatus;
use App\Enums\TaskStatus;
use App\Models\Article;
use App\Models\Link;
use App\Models\PayRate;
use App\Models\Task;
use App\Models\User;
use App\Support\DisplayTimezone;
use App\Support\Money;

class ScorecardService
{
    /**
     * Query-time monthly performance scorecard for a user (local calendar month).
     *
     * @return array<string, mixed>
     */
    public function forUserMonth(User $user, string $yearMonth): array
    {
        [$startUtc, $endUtc, $firstDate, $lastDate] = DisplayTimezone::utcBoundsForLocalMonth($yearMonth);

        $assignedTasks = Task::query()
            ->where('assigned_to', $user->id)
            ->where(fn ($q) => $q->where('is_recurrence_source', false)->orWhereNull('is_recurrence_source'))
            ->where(function ($q) use ($firstDate, $lastDate, $startUtc, $endUtc) {
                $q->whereBetween('created_at', [$startUtc, $endUtc])
                    ->orWhereBetween('due_date', [$firstDate, $lastDate])
                    ->orWhereBetween('approved_at', [$startUtc, $endUtc])
                    ->orWhereBetween('submitted_at', [$startUtc, $endUtc]);
            })
            ->get();

        $tasksAssigned = $assignedTasks->count();

        $completedInMonth = Task::query()
            ->where('assigned_to', $user->id)
            ->where(fn ($q) => $q->where('is_recurrence_source', false)->orWhereNull('is_recurrence_source'))
            ->where('status', TaskStatus::Approved->value)
            ->whereBetween('approved_at', [$startUtc, $endUtc])
            ->get();

        $tasksCompleted = $completedInMonth->count();

        $onTime = $completedInMonth->filter(function (Task $task) {
            if ($task->due_date === null || $task->approved_at === null) {
                return false;
            }
            $approvedLocal = $task->approved_at->timezone(DisplayTimezone::name())->toDateString();

            return $approvedLocal <= $task->due_date->toDateString();
        })->count();

        $onTimePct = $tasksCompleted > 0
            ? round(($onTime / $tasksCompleted) * 100, 1)
            : null;

        $rejectedInMonth = Task::query()
            ->where('assigned_to', $user->id)
            ->where(fn ($q) => $q->where('is_recurrence_source', false)->orWhereNull('is_recurrence_source'))
            ->where('status', TaskStatus::Rejected->value)
            ->where(function ($q) use ($startUtc, $endUtc) {
                $q->whereBetween('updated_at', [$startUtc, $endUtc])
                    ->orWhereBetween('submitted_at', [$startUtc, $endUtc]);
            })
            ->count();

        $reworkDenom = $tasksCompleted + $rejectedInMonth;
        $rejectionRatePct = $reworkDenom > 0
            ? round(($rejectedInMonth / $reworkDenom) * 100, 1)
            : null;

        $turnarounds = $completedInMonth
            ->filter(fn (Task $t) => $t->submitted_at && $t->approved_at)
            ->map(fn (Task $t) => abs($t->submitted_at->diffInMinutes($t->approved_at)) / 60);

        $avgTurnaroundHours = $turnarounds->isNotEmpty()
            ? round((float) $turnarounds->avg(), 2)
            : null;

        $articlesInScope = Article::query()
            ->where('writer_id', $user->id)
            ->where(function ($q) use ($startUtc, $endUtc) {
                $q->whereBetween('created_at', [$startUtc, $endUtc])
                    ->orWhereBetween('approved_at', [$startUtc, $endUtc])
                    ->orWhereBetween('submitted_at', [$startUtc, $endUtc]);
            })
            ->get();

        $articlesCount = $articlesInScope->count();
        $articlesApproved = $articlesInScope->filter(
            fn (Article $a) => $a->status === ArticleStatus::Approved
        )->count();
        $wordsTotal = (int) $articlesInScope->sum(fn (Article $a) => (int) ($a->word_count_actual ?? 0));
        $articleCostPaisa = (int) $articlesInScope
            ->filter(fn (Article $a) => $a->status === ArticleStatus::Approved)
            ->sum('cost_paisa');

        $linksApproved = Link::query()
            ->where('assigned_to', $user->id)
            ->where('workflow_status', LinkWorkflowStatus::Approved->value)
            ->whereBetween('approved_at', [$startUtc, $endUtc])
            ->get();

        $linksApprovedCount = $linksApproved->count();
        $linkCostPaisa = (int) $linksApproved->sum('cost_paisa');
        $outputCostPaisa = $articleCostPaisa + $linkCostPaisa;

        $payRates = PayRate::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->orderBy('type')
            ->get()
            ->map(fn (PayRate $r) => [
                'type' => $r->type->value,
                'type_label' => $r->type->label(),
                'amount_paisa' => $r->amount_paisa,
                'amount_formatted' => $r->amountFormatted(),
                'currency' => $r->currency,
            ])
            ->values()
            ->all();

        return [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'year_month' => $yearMonth,
            'period' => [
                'local_from' => $firstDate,
                'local_to' => $lastDate,
                'timezone' => DisplayTimezone::name(),
            ],
            'tasks' => [
                'assigned' => $tasksAssigned,
                'completed' => $tasksCompleted,
                'on_time' => $onTime,
                'on_time_pct' => $onTimePct,
                'rejected' => $rejectedInMonth,
                'rejection_rate_pct' => $rejectionRatePct,
                'avg_turnaround_hours' => $avgTurnaroundHours,
            ],
            'articles' => [
                'count' => $articlesCount,
                'approved' => $articlesApproved,
                'words' => $wordsTotal,
                'cost_paisa' => $articleCostPaisa,
            ],
            'links' => [
                'approved' => $linksApprovedCount,
                'cost_paisa' => $linkCostPaisa,
            ],
            'output_cost_paisa' => $outputCostPaisa,
            'output_cost_formatted' => Money::formatted($outputCostPaisa),
            'pay_rates' => $payRates,
        ];
    }
}
