<?php

namespace App\Services\Ai;

use App\Enums\AttendanceStatus;
use App\Enums\TaskStatus;
use App\Models\Article;
use App\Models\AttendanceDay;
use App\Models\Expense;
use App\Models\Link;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\PeopleVisibilityService;
use App\Services\ProfitAndLossService;
use App\Services\ScorecardService;
use App\Support\DisplayTimezone;
use App\Support\Money;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Fixed whitelist of read-only report methods for Ask-your-data.
 * Never generates SQL; each method uses Eloquent + scoped queries.
 */
class WhitelistReportService
{
    public function __construct(
        protected ProfitAndLossService $pnl,
        protected ScorecardService $scorecards,
        protected PeopleVisibilityService $people,
    ) {}

    /**
     * @return array{
     *   key: string,
     *   title: string,
     *   figures: array<string, mixed>,
     *   rows: list<array<string, mixed>>,
     *   narrative_seed: string
     * }
     */
    public function run(string $key, User $user): array
    {
        return match ($key) {
            'revenue_drops' => $this->revenueDrops($user),
            'overdue_tasks' => $this->overdueTasks($user),
            'portfolio_summary' => $this->portfolioSummary($user),
            'expense_spikes' => $this->expenseSpikes($user),
            'attendance' => $this->attendanceOverview($user),
            'top_sites' => $this->topPerformingSites($user),
            'open_approvals' => $this->openApprovals($user),
            'scorecard' => $this->scorecardSummary($user),
            default => throw new InvalidArgumentException("Unknown report key [{$key}]."),
        };
    }

    /**
     * Sites where this month revenue is lower than prior month, scoped to asker.
     *
     * @return array<string, mixed>
     */
    public function revenueDrops(User $user): array
    {
        if (! $user->hasPermission('pnl.view') && ! $user->hasPermission('revenue.view')) {
            return $this->denied('revenue_drops', 'You do not have permission to view revenue/P&L.');
        }

        $thisMonth = DisplayTimezone::now()->format('Y-m');
        $priorMonth = DisplayTimezone::now()->subMonth()->format('Y-m');

        $current = $this->pnl->forMonth($user, $thisMonth);
        $prior = $this->pnl->forMonth($user, $priorMonth);

        $priorById = collect($prior['projects'])->keyBy('project_id');
        $drops = [];

        foreach ($current['projects'] as $row) {
            $prev = $priorById->get($row['project_id']);
            $prevRev = (int) ($prev['revenue_paisa'] ?? 0);
            $curRev = (int) $row['revenue_paisa'];
            if ($prevRev <= 0) {
                continue;
            }
            if ($curRev < $prevRev) {
                $delta = $curRev - $prevRev;
                $pct = round(($delta / $prevRev) * 100, 1);
                $drops[] = [
                    'project_id' => $row['project_id'],
                    'domain' => $row['domain'],
                    'this_month_revenue' => Money::formatted($curRev),
                    'this_month_revenue_paisa' => $curRev,
                    'prior_month_revenue' => Money::formatted($prevRev),
                    'prior_month_revenue_paisa' => $prevRev,
                    'delta' => Money::formatted($delta),
                    'delta_paisa' => $delta,
                    'delta_pct' => $pct,
                ];
            }
        }

        usort($drops, fn ($a, $b) => $a['delta_paisa'] <=> $b['delta_paisa']);

        return [
            'key' => 'revenue_drops',
            'title' => 'Sites with revenue drops ('.$thisMonth.' vs '.$priorMonth.')',
            'figures' => [
                'period_this' => $thisMonth,
                'period_prior' => $priorMonth,
                'sites_with_drop' => count($drops),
                'portfolio_revenue_this' => Money::formatted((int) $current['totals']['revenue_paisa']),
                'portfolio_revenue_prior' => Money::formatted((int) $prior['totals']['revenue_paisa']),
            ],
            'rows' => $drops,
            'narrative_seed' => count($drops) === 0
                ? "No sites in scope had lower revenue in {$thisMonth} than {$priorMonth}."
                : count($drops).' site(s) dropped revenue in '.$thisMonth.' vs '.$priorMonth.'.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function overdueTasks(User $user): array
    {
        if (! $user->hasPermission('tasks.view')) {
            return $this->denied('overdue_tasks', 'You do not have permission to view tasks.');
        }

        $today = DisplayTimezone::today();

        $tasks = Task::query()
            ->with(['project', 'assignee'])
            ->accessibleBy($user)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today)
            ->where('status', '!=', TaskStatus::Approved->value)
            ->where(fn ($q) => $q->where('is_recurrence_source', false)->orWhereNull('is_recurrence_source'))
            ->orderBy('due_date')
            ->limit(100)
            ->get();

        $byAssignee = $tasks->groupBy(fn (Task $t) => $t->assignee?->name ?? 'Unassigned')
            ->map(fn (Collection $group) => $group->count())
            ->all();

        $byProject = $tasks->groupBy(fn (Task $t) => $t->project?->domain ?? '—')
            ->map(fn (Collection $group) => $group->count())
            ->all();

        $rows = $tasks->map(fn (Task $t) => [
            'id' => $t->id,
            'title' => $t->title,
            'project' => $t->project?->domain,
            'assignee' => $t->assignee?->name ?? 'Unassigned',
            'due_date' => $t->due_date?->toDateString(),
            'status' => $t->status->value,
        ])->all();

        return [
            'key' => 'overdue_tasks',
            'title' => 'Overdue tasks by assignee / project',
            'figures' => [
                'as_of' => $today,
                'total_overdue' => $tasks->count(),
                'by_assignee' => $byAssignee,
                'by_project' => $byProject,
            ],
            'rows' => $rows,
            'narrative_seed' => $tasks->count() === 0
                ? 'No overdue open tasks in your scope as of '.$today.'.'
                : $tasks->count().' overdue open task(s) as of '.$today.'.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function portfolioSummary(User $user): array
    {
        $canProjects = $user->hasPermission('projects.view');
        $canPnl = $user->hasPermission('pnl.view') || $user->hasPermission('revenue.view');

        if (! $canProjects && ! $canPnl) {
            return $this->denied('portfolio_summary', 'You do not have permission to view portfolio data.');
        }

        $month = DisplayTimezone::now()->format('Y-m');
        $projects = $canProjects
            ? Project::query()->accessibleBy($user)->orderBy('domain')->get()
            : collect();

        $openTasks = $user->hasPermission('tasks.view')
            ? Task::query()->accessibleBy($user)->open()
                ->where(fn ($q) => $q->where('is_recurrence_source', false)->orWhereNull('is_recurrence_source'))
                ->count()
            : null;

        $pnl = $canPnl ? $this->pnl->forMonth($user, $month) : null;

        $byStatus = $projects->groupBy(fn (Project $p) => $p->status->value)
            ->map(fn (Collection $g) => $g->count())
            ->all();

        return [
            'key' => 'portfolio_summary',
            'title' => 'Portfolio summary ('.$month.')',
            'figures' => [
                'period' => $month,
                'project_count' => $projects->count(),
                'projects_by_status' => $byStatus,
                'open_tasks' => $openTasks,
                'revenue' => $pnl ? Money::formatted((int) $pnl['totals']['revenue_paisa']) : null,
                'revenue_paisa' => $pnl ? (int) $pnl['totals']['revenue_paisa'] : null,
                'expenses' => $pnl ? Money::formatted((int) $pnl['totals']['total_expense_paisa']) : null,
                'expenses_paisa' => $pnl ? (int) $pnl['totals']['total_expense_paisa'] : null,
                'net_profit' => $pnl ? Money::formatted((int) $pnl['totals']['net_profit_paisa']) : null,
                'net_profit_paisa' => $pnl ? (int) $pnl['totals']['net_profit_paisa'] : null,
            ],
            'rows' => $pnl
                ? collect($pnl['projects'])->take(20)->map(fn ($r) => [
                    'domain' => $r['domain'],
                    'revenue' => Money::formatted((int) $r['revenue_paisa']),
                    'net_profit' => Money::formatted((int) $r['net_profit_paisa']),
                ])->all()
                : $projects->take(20)->map(fn (Project $p) => [
                    'domain' => $p->domain,
                    'status' => $p->status->value,
                ])->all(),
            'narrative_seed' => 'Portfolio for '.$month.': '.$projects->count().' project(s)'
                .($pnl ? ', net '.Money::formatted((int) $pnl['totals']['net_profit_paisa']) : '').'.',
        ];
    }

    /**
     * Expenses this month that are ≥ 1.5× category average from prior 3 months, or top absolute spends.
     *
     * @return array<string, mixed>
     */
    public function expenseSpikes(User $user): array
    {
        if (! $user->hasPermission('expenses.view')) {
            return $this->denied('expense_spikes', 'You do not have permission to view expenses.');
        }

        $today = DisplayTimezone::now();
        $monthStart = $today->copy()->startOfMonth()->toDateString();
        $monthEnd = $today->copy()->endOfMonth()->toDateString();
        $lookbackStart = $today->copy()->subMonths(3)->startOfMonth()->toDateString();
        $priorEnd = $today->copy()->subMonth()->endOfMonth()->toDateString();

        $thisMonth = Expense::query()
            ->accessibleBy($user)
            ->with(['project', 'category'])
            ->whereDate('expense_date', '>=', $monthStart)
            ->whereDate('expense_date', '<=', $monthEnd)
            ->orderByDesc('amount_paisa')
            ->get();

        $prior = Expense::query()
            ->accessibleBy($user)
            ->whereDate('expense_date', '>=', $lookbackStart)
            ->whereDate('expense_date', '<=', $priorEnd)
            ->get();

        $avgByCategory = $prior->groupBy('expense_category_id')->map(function (Collection $g) {
            $months = max(1, $g->groupBy(fn (Expense $e) => $e->expense_date?->format('Y-m'))->count());

            return (int) round($g->sum('amount_paisa') / $months);
        });

        $largeThreshold = Money::toMinor(config('money.large_expense_threshold', '5000'));

        $spikes = [];
        foreach ($thisMonth as $expense) {
            $avg = (int) ($avgByCategory[$expense->expense_category_id] ?? 0);
            $isSpike = $avg > 0 && $expense->amount_paisa >= (int) round($avg * 1.5);
            $isLarge = $expense->amount_paisa >= $largeThreshold;
            if (! $isSpike && ! $isLarge) {
                continue;
            }
            $spikes[] = [
                'id' => $expense->id,
                'description' => $expense->description,
                'project' => $expense->is_shared ? 'Shared' : ($expense->project?->domain ?? '—'),
                'category' => $expense->category?->name ?? 'Uncategorized',
                'amount' => Money::formatted((int) $expense->amount_paisa),
                'amount_paisa' => (int) $expense->amount_paisa,
                'date' => $expense->expense_date?->toDateString(),
                'vs_category_monthly_avg' => $avg > 0 ? Money::formatted($avg) : null,
                'flag' => $isSpike ? 'above_category_avg' : 'large_absolute',
            ];
        }

        usort($spikes, fn ($a, $b) => $b['amount_paisa'] <=> $a['amount_paisa']);

        $totalThis = (int) $thisMonth->sum('amount_paisa');

        return [
            'key' => 'expense_spikes',
            'title' => 'Expense spikes ('.DisplayTimezone::now()->format('Y-m').')',
            'figures' => [
                'period' => DisplayTimezone::now()->format('Y-m'),
                'total_expenses_this_month' => Money::formatted($totalThis),
                'total_expenses_this_month_paisa' => $totalThis,
                'flagged_count' => count($spikes),
            ],
            'rows' => array_slice($spikes, 0, 25),
            'narrative_seed' => count($spikes) === 0
                ? 'No material expense spikes flagged for the current month in your scope.'
                : count($spikes).' expense line(s) flagged as spikes; month total '.Money::formatted($totalThis).'.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function attendanceOverview(User $user): array
    {
        if (! $user->hasPermission('attendance.view')) {
            return $this->denied('attendance', 'You do not have permission to view attendance.');
        }

        $today = DisplayTimezone::today();
        $ids = $this->people->viewableUserIds($user);
        $users = User::query()
            ->whereIn('id', $ids->all())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $days = AttendanceDay::query()
            ->whereIn('user_id', $users->pluck('id'))
            ->whereDate('local_date', $today)
            ->get()
            ->keyBy('user_id');

        $counts = [
            'present' => 0,
            'late' => 0,
            'leave' => 0,
            'holiday' => 0,
            'absent' => 0,
        ];

        $rows = [];
        foreach ($users as $u) {
            /** @var AttendanceDay|null $day */
            $day = $days->get($u->id);
            $status = $day?->status?->value ?? AttendanceStatus::Absent->value;
            $isLate = (bool) ($day?->is_late);
            if ($status === AttendanceStatus::Present->value && $isLate) {
                $counts['late']++;
            } elseif (isset($counts[$status])) {
                $counts[$status]++;
            } else {
                $counts['absent']++;
            }
            $rows[] = [
                'user' => $u->name,
                'status' => $status,
                'is_late' => $isLate,
            ];
        }

        return [
            'key' => 'attendance',
            'title' => 'Attendance overview ('.$today.')',
            'figures' => [
                'date' => $today,
                'team_size' => $users->count(),
                'counts' => $counts,
            ],
            'rows' => $rows,
            'narrative_seed' => 'Attendance for '.$today.': '
                .$counts['present'].' present, '.$counts['late'].' late, '
                .$counts['leave'].' leave, '.$counts['absent'].' absent (of '.$users->count().').',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function topPerformingSites(User $user): array
    {
        if (! $user->hasPermission('pnl.view')) {
            return $this->denied('top_sites', 'You do not have permission to view P&L.');
        }

        $month = DisplayTimezone::now()->format('Y-m');
        $report = $this->pnl->forMonth($user, $month);
        $rows = collect($report['projects'])
            ->sortByDesc('net_profit_paisa')
            ->take(10)
            ->values()
            ->map(fn ($r, $i) => [
                'rank' => $i + 1,
                'domain' => $r['domain'],
                'revenue' => Money::formatted((int) $r['revenue_paisa']),
                'expenses' => Money::formatted((int) $r['total_expense_paisa']),
                'net_profit' => Money::formatted((int) $r['net_profit_paisa']),
                'net_profit_paisa' => (int) $r['net_profit_paisa'],
            ])
            ->all();

        return [
            'key' => 'top_sites',
            'title' => 'Top performing sites by profit ('.$month.')',
            'figures' => [
                'period' => $month,
                'portfolio_net' => Money::formatted((int) $report['totals']['net_profit_paisa']),
            ],
            'rows' => $rows,
            'narrative_seed' => count($rows) === 0
                ? 'No projects in scope for '.$month.'.'
                : 'Top site by net profit: '.($rows[0]['domain'] ?? '—').' ('.$rows[0]['net_profit'].').',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function openApprovals(User $user): array
    {
        $tasks = 0;
        $articles = 0;
        $links = 0;

        if ($user->hasPermission('tasks.approve') || $user->hasPermission('tasks.view')) {
            $tasks = Task::query()->accessibleBy($user)->awaitingApproval()->count();
        }
        if ($user->hasPermission('articles.approve') || $user->hasPermission('articles.view')) {
            $articles = Article::query()->accessibleBy($user)->awaitingApproval()->count();
        }
        if ($user->hasPermission('links.approve') || $user->hasPermission('links.view')) {
            $links = Link::query()->accessibleBy($user)->awaitingApproval()->count();
        }

        if (! $user->hasAnyPermission('tasks.view', 'articles.view', 'links.view', 'tasks.approve', 'articles.approve', 'links.approve')) {
            return $this->denied('open_approvals', 'You do not have permission to view approvals.');
        }

        $total = $tasks + $articles + $links;

        return [
            'key' => 'open_approvals',
            'title' => 'Open approvals',
            'figures' => [
                'tasks_submitted' => $tasks,
                'articles_submitted' => $articles,
                'links_submitted' => $links,
                'total' => $total,
            ],
            'rows' => [
                ['type' => 'tasks', 'count' => $tasks],
                ['type' => 'articles', 'count' => $articles],
                ['type' => 'links', 'count' => $links],
            ],
            'narrative_seed' => $total === 0
                ? 'No items awaiting approval in your scope.'
                : "{$total} item(s) awaiting approval (tasks {$tasks}, articles {$articles}, links {$links}).",
        ];
    }

    /**
     * Self / team scorecard summary for the current month.
     *
     * @return array<string, mixed>
     */
    public function scorecardSummary(User $user): array
    {
        if (! $user->hasPermission('scorecards.view')) {
            return $this->denied('scorecard', 'You do not have permission to view scorecards.');
        }

        $month = DisplayTimezone::now()->format('Y-m');
        $subjects = $this->people->viewableUsers($user)->take(20);
        $rows = [];

        foreach ($subjects as $subject) {
            $card = $this->scorecards->forUserMonth($subject, $month);
            // Never pass pay rates to AI/context path
            unset($card['pay_rates']);
            $rows[] = [
                'user_id' => $subject->id,
                'user_name' => $subject->name,
                'tasks_completed' => $card['tasks']['completed'],
                'tasks_on_time_pct' => $card['tasks']['on_time_pct'],
                'tasks_rejected' => $card['tasks']['rejected'],
                'articles_approved' => $card['articles']['approved'],
                'links_approved' => $card['links']['approved'],
            ];
        }

        return [
            'key' => 'scorecard',
            'title' => 'Scorecard summary ('.$month.')',
            'figures' => [
                'period' => $month,
                'people_count' => count($rows),
                'scope' => $user->hasPermission('people.view_team') || $user->isAdmin() ? 'team' : 'self',
            ],
            'rows' => $rows,
            'narrative_seed' => count($rows) === 0
                ? 'No scorecard subjects in scope.'
                : 'Scorecards for '.count($rows).' people for '.$month.'.',
        ];
    }

    /**
     * Recent rejection reasons for helper (scoped).
     *
     * @return list<array{type: string, title: string, reason: string, when: string|null}>
     */
    public function recentRejectionReasons(User $user, int $limit = 15): array
    {
        $items = collect();

        if ($user->hasPermission('tasks.view')) {
            $tasks = Task::query()
                ->accessibleBy($user)
                ->where('status', TaskStatus::Rejected->value)
                ->whereNotNull('rejection_reason')
                ->orderByDesc('updated_at')
                ->limit($limit)
                ->get(['id', 'title', 'rejection_reason', 'updated_at']);

            foreach ($tasks as $t) {
                $items->push([
                    'type' => 'task',
                    'title' => $t->title,
                    'reason' => (string) $t->rejection_reason,
                    'when' => $t->updated_at?->toIso8601String(),
                ]);
            }
        }

        if ($user->hasPermission('articles.view')) {
            $articles = Article::query()
                ->accessibleBy($user)
                ->whereNotNull('revision_notes')
                ->where('revision_notes', '!=', '')
                ->orderByDesc('updated_at')
                ->limit($limit)
                ->get(['id', 'title', 'revision_notes', 'updated_at']);

            foreach ($articles as $a) {
                $items->push([
                    'type' => 'article',
                    'title' => $a->title,
                    'reason' => (string) $a->revision_notes,
                    'when' => $a->updated_at?->toIso8601String(),
                ]);
            }
        }

        if ($user->hasPermission('links.view')) {
            $links = Link::query()
                ->accessibleBy($user)
                ->whereNotNull('rejection_reason')
                ->where('rejection_reason', '!=', '')
                ->orderByDesc('updated_at')
                ->limit($limit)
                ->get(['id', 'source_domain', 'rejection_reason', 'updated_at']);

            foreach ($links as $l) {
                $items->push([
                    'type' => 'link',
                    'title' => $l->source_domain,
                    'reason' => (string) $l->rejection_reason,
                    'when' => $l->updated_at?->toIso8601String(),
                ]);
            }
        }

        return $items->sortByDesc('when')->take($limit)->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function denied(string $key, string $message): array
    {
        return [
            'key' => $key,
            'title' => QuestionMapper::labels()[$key] ?? $key,
            'figures' => ['access' => 'denied'],
            'rows' => [],
            'narrative_seed' => $message,
            'denied' => true,
        ];
    }
}
