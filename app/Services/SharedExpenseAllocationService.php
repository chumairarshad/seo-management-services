<?php

namespace App\Services;

use App\Enums\ProjectStatus;
use App\Models\Expense;
use App\Models\ExpenseAllocation;
use App\Models\Project;
use App\Models\Revenue;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Allocate shared expenses across active sites by that month's revenue share.
 */
class SharedExpenseAllocationService
{
    /**
     * Bring a month's shared-cost allocations in line with current revenue.
     *
     * Reports call this because an allocation depends on the month's revenue
     * split, which can change after the expense was entered. It only writes when
     * the result actually differs, so opening a report is a read in the normal
     * case instead of a delete-and-reinsert of every allocation row.
     */
    public function rebuildForMonth(string $periodMonth): void
    {
        $month = Carbon::parse($periodMonth)->startOfMonth()->toDateString();

        $shared = Expense::query()
            ->shared()
            ->whereBetween('expense_date', [$month, Carbon::parse($month)->endOfMonth()->toDateString()])
            ->get();

        if ($shared->isEmpty()) {
            return;
        }

        // Hoisted out of the loop: the project list and revenue split are the same
        // for every shared expense in the month.
        $projects = $this->allocationProjects();
        $revenues = $this->revenueByProject($month);

        foreach ($shared as $expense) {
            $this->syncAllocations($expense, $month, $projects, $revenues);
        }
    }

    /**
     * @return Collection<int, Project>
     */
    public function allocationProjects(): Collection
    {
        return Project::query()
            ->whereIn('status', [
                ProjectStatus::Live->value,
                ProjectStatus::Monetized->value,
                ProjectStatus::Setup->value,
            ])
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<int, int> project_id => paisa
     */
    public function revenueByProject(string $periodMonth): array
    {
        $month = Carbon::parse($periodMonth)->startOfMonth()->toDateString();

        return Revenue::query()
            ->whereDate('period_month', $month)
            ->selectRaw('project_id, SUM(amount_pkr_paisa) as total')
            ->groupBy('project_id')
            ->pluck('total', 'project_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    public function allocateExpense(Expense $expense, ?string $periodMonth = null): void
    {
        if (! $expense->is_shared) {
            ExpenseAllocation::query()->where('expense_id', $expense->id)->delete();

            return;
        }

        $month = $periodMonth
            ? Carbon::parse($periodMonth)->startOfMonth()->toDateString()
            : $expense->expense_date->copy()->startOfMonth()->toDateString();

        $this->syncAllocations(
            $expense,
            $month,
            $this->allocationProjects(),
            $this->revenueByProject($month),
        );
    }

    /**
     * Write the allocation rows for one shared expense, but only if they changed.
     *
     * @param  Collection<int, Project>  $projects
     * @param  array<int, int>  $revenues
     */
    protected function syncAllocations(Expense $expense, string $month, Collection $projects, array $revenues): void
    {
        if ($projects->isEmpty()) {
            return;
        }

        $intended = $this->intendedAllocations($expense, $month, $projects, $revenues);

        $existing = ExpenseAllocation::query()
            ->where('expense_id', $expense->id)
            ->orderBy('project_id')
            ->get();

        if ($this->allocationsMatch($existing, $intended)) {
            return;
        }

        DB::transaction(function () use ($expense, $intended) {
            ExpenseAllocation::query()->where('expense_id', $expense->id)->delete();

            if ($intended !== []) {
                ExpenseAllocation::query()->insert(array_map(
                    fn (array $row) => $row + ['created_at' => now(), 'updated_at' => now()],
                    $intended,
                ));
            }
        });
    }

    /**
     * @param  Collection<int, Project>  $projects
     * @param  array<int, int>  $revenues
     * @return list<array<string, mixed>>
     */
    protected function intendedAllocations(Expense $expense, string $month, Collection $projects, array $revenues): array
    {
        $portfolioRevenue = (int) array_sum($revenues);
        $amount = (int) $expense->amount_paisa;
        $allocations = [];

        if ($portfolioRevenue > 0) {
            $targets = $projects->filter(fn (Project $p) => ($revenues[$p->id] ?? 0) > 0)->values();
            if ($targets->isEmpty()) {
                $targets = $projects->values();
            }
            $allocated = 0;
            foreach ($targets as $i => $project) {
                $rev = (int) ($revenues[$project->id] ?? 0);
                if ($i === $targets->count() - 1) {
                    $share = $amount - $allocated;
                } else {
                    $share = intdiv($amount * $rev, $portfolioRevenue);
                    $allocated += $share;
                }
                $allocations[] = [
                    'expense_id' => $expense->id,
                    'project_id' => $project->id,
                    'period_month' => $month,
                    'amount_paisa' => max(0, $share),
                    // Half-up in integer arithmetic, matching the previous float round.
                    'revenue_share_bps' => intdiv($rev * 20000 + $portfolioRevenue, $portfolioRevenue * 2),
                    'project_revenue_paisa' => $rev,
                    'portfolio_revenue_paisa' => $portfolioRevenue,
                ];
            }
        } else {
            $count = $projects->count();
            $base = intdiv($amount, $count);
            $remainder = $amount - ($base * $count);
            foreach ($projects->values() as $i => $project) {
                $allocations[] = [
                    'expense_id' => $expense->id,
                    'project_id' => $project->id,
                    'period_month' => $month,
                    'amount_paisa' => $base + ($i === 0 ? $remainder : 0),
                    'revenue_share_bps' => intdiv(10000, $count),
                    'project_revenue_paisa' => 0,
                    'portfolio_revenue_paisa' => 0,
                ];
            }
        }

        usort($allocations, fn (array $a, array $b) => $a['project_id'] <=> $b['project_id']);

        return $allocations;
    }

    /**
     * @param  Collection<int, ExpenseAllocation>  $existing
     * @param  list<array<string, mixed>>  $intended
     */
    protected function allocationsMatch(Collection $existing, array $intended): bool
    {
        if ($existing->count() !== count($intended)) {
            return false;
        }

        foreach ($intended as $i => $row) {
            $current = $existing[$i];

            if ((int) $current->project_id !== (int) $row['project_id']
                || (int) $current->amount_paisa !== (int) $row['amount_paisa']
                || (int) $current->revenue_share_bps !== (int) $row['revenue_share_bps']
                || (int) $current->project_revenue_paisa !== (int) $row['project_revenue_paisa']
                || (int) $current->portfolio_revenue_paisa !== (int) $row['portfolio_revenue_paisa']
                || $current->period_month->toDateString() !== $row['period_month']) {
                return false;
            }
        }

        return true;
    }

    public function sharedCostForProject(int $projectId, string $from, string $to): int
    {
        $fromMonth = Carbon::parse($from)->startOfMonth()->toDateString();
        $toMonth = Carbon::parse($to)->startOfMonth()->toDateString();

        $cursor = Carbon::parse($fromMonth)->startOfMonth();
        $end = Carbon::parse($toMonth)->startOfMonth();
        while ($cursor->lte($end)) {
            $this->rebuildForMonth($cursor->toDateString());
            $cursor->addMonth();
        }

        return (int) ExpenseAllocation::query()
            ->where('project_id', $projectId)
            ->whereDate('period_month', '>=', $fromMonth)
            ->whereDate('period_month', '<=', $toMonth)
            ->sum('amount_paisa');
    }
}
