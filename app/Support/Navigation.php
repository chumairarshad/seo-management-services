<?php

namespace App\Support;

use App\Models\Article;
use App\Models\Link;
use App\Models\Task;
use App\Models\User;

/**
 * Single source of truth for app navigation.
 *
 * The desktop rail, the mobile drawer, the bottom bar and the command palette
 * all read this, so a permission gate is written once and honoured everywhere.
 */
class Navigation
{
    protected static ?int $approvalCount = null;

    /**
     * @return array<int, array{label: ?string, items: array<int, array<string, mixed>>}>
     */
    public static function groups(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $groups = [
            [
                'label' => null,
                'items' => array_values(array_filter([
                    self::item('Home', 'home', 'dashboard', 'dashboard'),
                ])),
            ],
            [
                'label' => 'Work',
                'items' => array_values(array_filter([
                    $user->hasPermission('projects.view') ? self::item('Projects', 'projects', 'projects.index', 'projects.*') : null,
                    $user->hasPermission('tasks.view') ? self::item('Tasks', 'tasks', 'tasks.index', 'tasks.*') : null,
                    $user->hasPermission('articles.view') ? self::item('Articles', 'articles', 'articles.index', 'articles.*') : null,
                    $user->hasPermission('links.view') ? self::item('Links', 'links', 'links.index', 'links.*') : null,
                    $user->hasAnyPermission('tasks.approve', 'articles.approve', 'links.approve')
                        ? self::item('Approvals', 'approvals', 'approvals.queue', 'approvals.*', self::approvalCount($user))
                        : null,
                ])),
            ],
            [
                'label' => 'People',
                'items' => array_values(array_filter([
                    $user->hasPermission('attendance.view') ? self::item('Attendance', 'attendance', 'people.attendance', 'people.attendance') : null,
                    $user->hasPermission('work_logs.view') ? self::item('Work logs', 'worklogs', 'people.work-logs', 'people.work-logs') : null,
                    $user->hasPermission('scorecards.view') ? self::item('Scorecard', 'scorecard', 'people.scorecard', 'people.scorecard') : null,
                    $user->hasPermission('login_history.view') ? self::item('Login history', 'history', 'people.login-history', 'people.login-history') : null,
                ])),
            ],
            [
                'label' => 'Money',
                'items' => array_values(array_filter([
                    $user->hasPermission('revenue.view') ? self::item('Revenue', 'revenue', 'money.revenues', 'money.revenues') : null,
                    $user->hasPermission('expenses.view') ? self::item('Expenses', 'expenses', 'money.expenses', 'money.expenses') : null,
                    $user->hasPermission('pnl.view') ? self::item('Profit & loss', 'pnl', 'money.pnl', 'money.pnl') : null,
                    $user->hasPermission('distributions.view') ? self::item('Distributions', 'distributions', 'money.distributions', 'money.distributions*') : null,
                    self::partnersItem($user),
                ])),
            ],
            [
                'label' => 'Workspace',
                'items' => array_values(array_filter([
                    AiAvailability::enabled() ? self::item('AI assistant', 'ai', 'ai.ask', 'ai.*') : null,
                    $user->hasPermission('users.view') ? self::item('Users', 'users', 'users.index', 'users.*') : null,
                    $user->hasPermission('settings.view') ? self::item('Settings', 'settings', 'settings.index', 'settings.index') : null,
                    $user->hasPermission('task_templates.manage') ? self::item('Task templates', 'templates', 'settings.task-templates', 'settings.task-templates') : null,
                ])),
            ],
        ];

        return array_values(array_filter($groups, fn (array $group) => $group['items'] !== []));
    }

    /**
     * Flattened nav for the command palette and the mobile drawer.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function flat(?User $user): array
    {
        $items = [];

        foreach (self::groups($user) as $group) {
            foreach ($group['items'] as $item) {
                $item['group'] = $group['label'] ?? 'Overview';
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * The 4 destinations that earn a thumb-reachable slot on phones.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function bottomBar(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $preferred = ['dashboard', 'approvals.queue', 'tasks.index', 'projects.index', 'articles.index', 'people.attendance', 'money.revenues'];
        $flat = collect(self::flat($user))->keyBy('route');

        return collect($preferred)
            ->map(fn (string $route) => $flat->get($route))
            ->filter()
            ->take(4)
            ->values()
            ->all();
    }

    /**
     * Quick-create actions — each lands on its index with the form already open.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function quickCreate(?User $user): array
    {
        if (! $user) {
            return [];
        }

        return array_values(array_filter([
            $user->hasPermission('tasks.create') ? self::action('New task', 'tasks', 'tasks.index') : null,
            $user->hasPermission('articles.create') ? self::action('New article', 'articles', 'articles.index') : null,
            $user->hasPermission('links.create') ? self::action('New link', 'links', 'links.index') : null,
            $user->hasPermission('projects.create') ? self::action('New project', 'projects', 'projects.index') : null,
            $user->hasPermission('revenue.manage') ? self::action('New revenue row', 'revenue', 'money.revenues') : null,
            $user->hasPermission('expenses.manage') ? self::action('New expense', 'expenses', 'money.expenses') : null,
            $user->hasPermission('users.create') ? self::action('New user', 'users', 'users.index') : null,
        ]));
    }

    public static function approvalCount(?User $user): int
    {
        if (! $user || ! $user->hasAnyPermission('tasks.approve', 'articles.approve', 'links.approve')) {
            return 0;
        }

        if (self::$approvalCount !== null) {
            return self::$approvalCount;
        }

        $count = 0;

        if ($user->hasPermission('tasks.approve')) {
            $count += Task::query()->accessibleBy($user)->awaitingApproval()->count();
        }

        if ($user->hasPermission('articles.approve')) {
            $count += Article::query()->accessibleBy($user)->awaitingApproval()->count();
        }

        if ($user->hasPermission('links.approve')) {
            $count += Link::query()->accessibleBy($user)->awaitingApproval()->count();
        }

        return self::$approvalCount = $count;
    }

    public static function flushCache(): void
    {
        self::$approvalCount = null;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected static function partnersItem(?User $user): ?array
    {
        if (! $user?->hasAnyPermission('partners.view', 'partners.statement')) {
            return null;
        }

        return $user->hasPermission('partners.view')
            ? self::item('Partners', 'partners', 'money.partners', 'money.partners*')
            : self::item('My statement', 'partners', 'money.partners.statement', 'money.partners*');
    }

    /**
     * @return array<string, mixed>
     */
    protected static function item(string $label, string $icon, string $route, string $activePattern, int $badge = 0): array
    {
        return [
            'label' => $label,
            'icon' => $icon,
            'route' => $route,
            'href' => route($route),
            'active' => request()->routeIs($activePattern),
            'badge' => $badge,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function action(string $label, string $icon, string $route): array
    {
        return [
            'label' => $label,
            'icon' => $icon,
            'route' => $route,
            'href' => route($route, ['new' => 1]),
            'active' => false,
            'badge' => 0,
        ];
    }
}
