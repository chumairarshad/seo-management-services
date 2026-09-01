<?php

namespace Database\Seeders;

use App\Enums\ArticleStatus;
use App\Enums\AttendanceStatus;
use App\Enums\LinkLiveStatus;
use App\Enums\LinkType;
use App\Enums\LinkWorkflowStatus;
use App\Enums\PayRateType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Article;
use App\Models\Link;
use App\Models\LoginHistory;
use App\Models\PayRate;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkLog;
use App\Services\AttendanceService;
use App\Services\LoginHistoryService;
use App\Support\Currency;
use App\Support\DisplayTimezone;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DemoPeopleSeeder extends Seeder
{
    public function run(): void
    {
        $staff = User::query()->where('email', 'staff@example.com')->first();
        $supervisor = User::query()->where('email', 'supervisor@example.com')->first();
        $admin = User::query()->where('email', 'admin@example.com')->first();
        $alpha = Project::query()->where('domain', 'alpha-demo.test')->first();

        if (! $staff || ! $alpha) {
            return;
        }

        $login = app(LoginHistoryService::class);
        $attendance = app(AttendanceService::class);
        $tz = DisplayTimezone::name();
        $month = DisplayTimezone::now()->format('Y-m');
        $today = DisplayTimezone::today();

        // Seed a few historical logins for staff (local mornings)
        $loginDays = [
            DisplayTimezone::now()->subDays(5)->toDateString(),
            DisplayTimezone::now()->subDays(4)->toDateString(),
            DisplayTimezone::now()->subDays(3)->toDateString(),
            DisplayTimezone::now()->subDays(1)->toDateString(),
        ];

        foreach ($loginDays as $i => $localDate) {
            // ~09:15 local most days; one late day
            $hour = $i === 1 ? 11 : 9;
            $minute = $i === 1 ? 30 : 15;
            $at = Carbon::parse("{$localDate} {$hour}:{$minute}:00", $tz)->utc();

            LoginHistory::query()->updateOrCreate(
                [
                    'user_id' => $staff->id,
                    'logged_in_at' => $at,
                ],
                [
                    'ip_address' => '203.0.113.'.(10 + $i),
                    'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X) Chrome/120.0.0.0',
                    'device' => 'Chrome on macOS',
                ],
            );
            $attendance->syncFromLogin($staff, $at);
        }

        // Supervisor login today morning
        if ($supervisor) {
            $supAt = Carbon::parse("{$today} 08:45:00", $tz)->utc();
            LoginHistory::query()->updateOrCreate(
                [
                    'user_id' => $supervisor->id,
                    'logged_in_at' => $supAt,
                ],
                [
                    'ip_address' => '203.0.113.50',
                    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0) Firefox/121.0',
                    'device' => 'Firefox on Windows',
                ],
            );
            $attendance->syncFromLogin($supervisor, $supAt);
        }

        // Leave day for staff (two days ago if weekday, else three days)
        $leaveDate = DisplayTimezone::now()->subDays(2)->toDateString();
        if ($admin || $supervisor) {
            $attendance->markOverride(
                $staff,
                $leaveDate,
                AttendanceStatus::Leave,
                $supervisor ?? $admin,
                'Demo leave day for scorecard walkthrough',
            );
        }

        // Work logs
        WorkLog::query()->updateOrCreate(
            [
                'user_id' => $staff->id,
                'local_date' => DisplayTimezone::now()->subDays(1)->toDateString(),
            ],
            [
                'body' => "Published schema fix notes and drafted link outreach list for {$alpha->domain}.",
                'task_ids' => Task::query()->where('project_id', $alpha->id)->where('assigned_to', $staff->id)->limit(2)->pluck('id')->all(),
                'article_ids' => [],
                'link_ids' => [],
            ],
        );

        WorkLog::query()->updateOrCreate(
            [
                'user_id' => $staff->id,
                'local_date' => $today,
            ],
            [
                'body' => 'Checking pending approvals and finishing competitor gap notes.',
                'task_ids' => [],
                'article_ids' => Article::query()->where('writer_id', $staff->id)->limit(1)->pluck('id')->all(),
                'link_ids' => Link::query()->where('assigned_to', $staff->id)->limit(1)->pluck('id')->all(),
            ],
        );

        // Pay rates (read-only display)
        PayRate::query()->updateOrCreate(
            [
                'user_id' => $staff->id,
                'type' => PayRateType::MonthlySalary,
            ],
            [
                'amount_paisa' => Money::toMinor('4000'),
                'currency' => Currency::code(),
                'is_active' => true,
                'effective_from' => now()->subMonths(3)->toDateString(),
            ],
        );
        PayRate::query()->updateOrCreate(
            [
                'user_id' => $staff->id,
                'type' => PayRateType::PerArticle,
            ],
            [
                'amount_paisa' => Money::toMinor('40'),
                'currency' => Currency::code(),
                'is_active' => true,
            ],
        );

        // Extra completed / rejected work this month for deterministic-ish scorecard demo
        $startUtc = Carbon::parse(DisplayTimezone::now()->startOfMonth()->toDateString().' 10:00:00', $tz)->utc();

        Task::query()->updateOrCreate(
            [
                'project_id' => $alpha->id,
                'title' => 'Scorecard demo: on-time approve',
                'type' => TaskType::AdHoc,
            ],
            [
                'status' => TaskStatus::Approved,
                'assigned_to' => $staff->id,
                'due_date' => $today,
                'submitted_at' => $startUtc->copy()->addDays(2),
                'approved_at' => $startUtc->copy()->addDays(2)->addHours(4),
                'approved_by' => $supervisor?->id ?? $admin?->id,
                'created_by' => $admin?->id,
                'created_at' => $startUtc,
                'time_spent_minutes' => 60,
                'is_recurrence_source' => false,
            ],
        );

        Task::query()->updateOrCreate(
            [
                'project_id' => $alpha->id,
                'title' => 'Scorecard demo: late approve',
                'type' => TaskType::AdHoc,
            ],
            [
                'status' => TaskStatus::Approved,
                'assigned_to' => $staff->id,
                'due_date' => $startUtc->copy()->timezone($tz)->toDateString(),
                'submitted_at' => $startUtc->copy()->addDays(5),
                'approved_at' => $startUtc->copy()->addDays(8),
                'approved_by' => $supervisor?->id ?? $admin?->id,
                'created_by' => $admin?->id,
                'created_at' => $startUtc,
                'time_spent_minutes' => 120,
                'is_recurrence_source' => false,
            ],
        );

        Article::query()->updateOrCreate(
            [
                'project_id' => $alpha->id,
                'target_keyword' => 'scorecard demo article',
            ],
            [
                'title' => 'Scorecard demo published piece',
                'status' => ArticleStatus::Approved,
                'writer_id' => $staff->id,
                'word_count_actual' => 1500,
                'word_count_target' => 1500,
                'cost_paisa' => Money::toMinor('90'),
                'approved_at' => $startUtc->copy()->addDays(4),
                'submitted_at' => $startUtc->copy()->addDays(3),
                'approved_by' => $supervisor?->id ?? $admin?->id,
                'created_by' => $admin?->id,
                'created_at' => $startUtc,
            ],
        );

        Link::query()->updateOrCreate(
            [
                'project_id' => $alpha->id,
                'source_url' => 'https://scorecard-demo.example/guest-post',
            ],
            [
                'source_domain' => 'scorecard-demo.example',
                'target_page' => 'https://alpha-demo.test/',
                'anchor_text' => 'alpha demo',
                'type' => LinkType::GuestPost,
                'live_status' => LinkLiveStatus::Live,
                'workflow_status' => LinkWorkflowStatus::Approved,
                'cost_paisa' => Money::toMinor('65'),
                'assigned_to' => $staff->id,
                'created_by' => $staff->id,
                'approved_by' => $supervisor?->id ?? $admin?->id,
                'approved_at' => $startUtc->copy()->addDays(6),
                'submitted_at' => $startUtc->copy()->addDays(5),
                'link_date' => $today,
            ],
        );

        // Quiet unused warning if month not used elsewhere
        unset($month, $login);
    }
}
