<?php

namespace Database\Seeders;

use App\Enums\ArticleStatus;
use App\Enums\LinkLiveStatus;
use App\Enums\LinkType;
use App\Enums\LinkWorkflowStatus;
use App\Enums\RecurrenceFrequency;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Article;
use App\Models\Link;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\RecurringTaskGenerator;
use App\Services\SetupChecklistService;
use App\Support\Money;
use Illuminate\Database\Seeder;

class DemoWorkSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@example.com')->first();
        $staff = User::query()->where('email', 'staff@example.com')->first();
        $supervisor = User::query()->where('email', 'supervisor@example.com')->first();

        $alpha = Project::query()->where('domain', 'alpha-demo.test')->first();
        $beta = Project::query()->where('domain', 'beta-demo.test')->first();

        if (! $alpha || ! $staff) {
            return;
        }

        $checklists = app(SetupChecklistService::class);
        $checklists->generateForProject($alpha, $admin);
        if ($beta) {
            $checklists->generateForProject($beta, $admin);
        }

        $alpha->update([
            'monthly_link_target' => 10,
            'monthly_link_budget_paisa' => Money::toMinor('2000'),
        ]);

        // Mixed-status ad-hoc tasks
        $taskDefs = [
            [
                'title' => 'Refresh home page CTAs',
                'status' => TaskStatus::Assigned,
                'assigned_to' => $staff->id,
                'due_date' => now()->toDateString(),
            ],
            [
                'title' => 'Competitor content gap notes',
                'status' => TaskStatus::InProgress,
                'assigned_to' => $staff->id,
                'due_date' => now()->addDays(2)->toDateString(),
            ],
            [
                'title' => 'Publish author bio pages',
                'status' => TaskStatus::Submitted,
                'assigned_to' => $staff->id,
                'due_date' => now()->subDay()->toDateString(),
                'submitted_at' => now()->subHours(3),
                'time_spent_minutes' => 90,
            ],
            [
                'title' => 'Fix broken product schema',
                'status' => TaskStatus::Rejected,
                'assigned_to' => $staff->id,
                'due_date' => now()->addDay()->toDateString(),
                'rejection_reason' => 'Schema still missing review aggregateRating.',
                'time_spent_minutes' => 45,
            ],
            [
                'title' => 'Footer legal links pass',
                'status' => TaskStatus::Approved,
                'assigned_to' => $staff->id,
                'due_date' => now()->subDays(3)->toDateString(),
                'approved_by' => $supervisor?->id ?? $admin?->id,
                'approved_at' => now()->subDays(2),
            ],
        ];

        foreach ($taskDefs as $def) {
            Task::query()->updateOrCreate(
                [
                    'project_id' => $alpha->id,
                    'title' => $def['title'],
                    'type' => TaskType::AdHoc,
                ],
                [
                    'status' => $def['status'],
                    'assigned_to' => $def['assigned_to'],
                    'due_date' => $def['due_date'],
                    'submitted_at' => $def['submitted_at'] ?? null,
                    'rejection_reason' => $def['rejection_reason'] ?? null,
                    'time_spent_minutes' => $def['time_spent_minutes'] ?? 0,
                    'approved_by' => $def['approved_by'] ?? null,
                    'approved_at' => $def['approved_at'] ?? null,
                    'created_by' => $admin?->id,
                    'is_recurrence_source' => false,
                ],
            );
        }

        // Recurring source: weekly rank check
        $source = Task::query()->updateOrCreate(
            [
                'project_id' => $alpha->id,
                'title' => 'Weekly rank snapshot',
                'is_recurrence_source' => true,
            ],
            [
                'description' => 'Pull top-20 keyword positions into notes.',
                'type' => TaskType::Recurring,
                'status' => TaskStatus::Assigned,
                'assigned_to' => $staff->id,
                'recurrence_frequency' => RecurrenceFrequency::Weekly,
                'next_occurrence_date' => now()->toDateString(),
                'created_by' => $admin?->id,
            ],
        );
        app(RecurringTaskGenerator::class)->generateForSource($source);

        $articles = [
            [
                'title' => 'Best budgeting apps for freelancers',
                'target_keyword' => 'best budgeting apps',
                'status' => ArticleStatus::Brief,
                'writer_id' => null,
                'cost_paisa' => Money::toMinor('80'),
            ],
            [
                'title' => 'How to open a digital banking account',
                'target_keyword' => 'open digital banking account',
                'status' => ArticleStatus::Assigned,
                'writer_id' => $staff->id,
                'cost_paisa' => Money::toMinor('100'),
                'word_count_target' => 1800,
            ],
            [
                'title' => 'Salary negotiation tips for freelancers',
                'target_keyword' => 'salary negotiation freelancers',
                'status' => ArticleStatus::DraftSubmitted,
                'writer_id' => $staff->id,
                'cost_paisa' => Money::toMinor('120'),
                'word_count_target' => 2000,
                'word_count_actual' => 1950,
                'submitted_at' => now()->subHours(5),
            ],
            [
                'title' => 'Emergency fund basics',
                'target_keyword' => 'emergency fund',
                'status' => ArticleStatus::RevisionRequested,
                'writer_id' => $staff->id,
                'cost_paisa' => Money::toMinor('75'),
                'revision_notes' => 'Add local examples and update meta description.',
            ],
        ];

        foreach ($articles as $row) {
            Article::query()->updateOrCreate(
                [
                    'project_id' => $alpha->id,
                    'target_keyword' => $row['target_keyword'],
                ],
                array_merge($row, [
                    'created_by' => $admin?->id,
                    'meta_title' => $row['title'],
                ]),
            );
        }

        if ($beta) {
            Article::query()->updateOrCreate(
                [
                    'project_id' => $beta->id,
                    'target_keyword' => 'best laptops 2026',
                ],
                [
                    'title' => 'Best laptops for creators 2026',
                    'status' => ArticleStatus::Assigned,
                    'writer_id' => $staff->id,
                    'cost_paisa' => Money::toMinor('150'),
                    'created_by' => $admin?->id,
                ],
            );
        }

        $links = [
            [
                'source_url' => 'https://example-blog.com/finance-roundup',
                'target_page' => 'https://alpha-demo.test/guides/budgeting',
                'anchor_text' => 'budgeting guide',
                'workflow_status' => LinkWorkflowStatus::Pending,
                'cost_paisa' => Money::toMinor('50'),
            ],
            [
                'source_url' => 'https://news.example.org/resources',
                'target_page' => 'https://alpha-demo.test/',
                'anchor_text' => 'alpha demo',
                'workflow_status' => LinkWorkflowStatus::Submitted,
                'cost_paisa' => Money::toMinor('120'),
                'dr' => 42,
                'submitted_at' => now()->subHour(),
            ],
            [
                'source_url' => 'https://www.directory.example/listing/alpha',
                'target_page' => 'https://alpha-demo.test/about',
                'anchor_text' => 'about us',
                'workflow_status' => LinkWorkflowStatus::Rejected,
                'cost_paisa' => Money::toMinor('20'),
                'rejection_reason' => 'Spammy directory — do not use.',
            ],
            // duplicate domain seed (for UI warn demos on create)
            [
                'source_url' => 'https://example-blog.com/another-post',
                'target_page' => 'https://alpha-demo.test/blog',
                'anchor_text' => 'read more',
                'workflow_status' => LinkWorkflowStatus::Pending,
                'cost_paisa' => Money::toMinor('35'),
            ],
        ];

        foreach ($links as $row) {
            $domain = Link::extractDomain($row['source_url']);
            Link::query()->updateOrCreate(
                [
                    'project_id' => $alpha->id,
                    'source_url' => $row['source_url'],
                ],
                array_merge($row, [
                    'source_domain' => $domain,
                    'type' => LinkType::GuestPost,
                    'live_status' => LinkLiveStatus::Live,
                    'link_date' => now()->toDateString(),
                    'assigned_to' => $staff->id,
                    'created_by' => $staff->id,
                ]),
            );
        }
    }
}
