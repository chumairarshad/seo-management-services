<?php

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Project;
use App\Models\RecurringExpense;
use App\Models\User;
use App\Services\ExpenseService;
use App\Services\ProjectOwnershipService;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

function idemAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

function idemProject(User $owner): Project
{
    $project = Project::factory()->create(['domain' => 'idem.test']);
    app(ProjectOwnershipService::class)->sync($project, [
        ['user_id' => $owner->id, 'share_bps' => 10000],
    ]);

    return $project;
}

it('raises one expense when the same article approval is submitted twice', function () {
    $admin = idemAdmin();
    $project = idemProject($admin);

    $article = Article::query()->create([
        'project_id' => $project->id,
        'title' => 'Double submitted article',
        'target_keyword' => 'double submit keyword',
        'status' => ArticleStatus::Approved,
        'cost_paisa' => 5_000_00,
        'created_by' => $admin->id,
        'approved_by' => $admin->id,
        'approved_at' => now(),
    ]);

    $service = app(ExpenseService::class);

    $first = $service->createFromArticleApproval($article->fresh(), $admin);
    $second = $service->createFromArticleApproval($article->fresh(), $admin);

    expect($second->id)->toBe($first->id)
        ->and(Expense::query()->where('source_id', $article->id)->count())->toBe(1)
        ->and((int) Expense::query()->sum('amount_paisa'))->toBe(5_000_00);
});

it('generates one recurring instance per due date however often cron runs', function () {
    $admin = idemAdmin();
    $project = idemProject($admin);
    $category = ExpenseCategory::query()->create(['name' => 'Hosting', 'slug' => 'hosting']);

    RecurringExpense::query()->create([
        'project_id' => $project->id,
        'is_shared' => false,
        'expense_category_id' => $category->id,
        'amount_paisa' => 1_200_00,
        'currency' => 'PKR',
        'description' => 'Monthly hosting',
        'day_of_month' => 1,
        'next_run_date' => '2026-01-01',
        'is_active' => true,
        'created_by' => $admin->id,
    ]);

    $service = app(ExpenseService::class);
    $asOf = Carbon::parse('2026-03-15');

    // Three due months on the first pass, nothing left on a repeat run.
    expect($service->generateDueRecurring($asOf))->toBe(3)
        ->and($service->generateDueRecurring($asOf))->toBe(0)
        ->and(Expense::query()->count())->toBe(3);
});

it('does not resurrect a recurring instance that was deleted on purpose', function () {
    $admin = idemAdmin();
    $project = idemProject($admin);
    $category = ExpenseCategory::query()->create(['name' => 'Hosting', 'slug' => 'hosting']);

    $template = RecurringExpense::query()->create([
        'project_id' => $project->id,
        'is_shared' => false,
        'expense_category_id' => $category->id,
        'amount_paisa' => 1_200_00,
        'currency' => 'PKR',
        'description' => 'Monthly hosting',
        'day_of_month' => 1,
        'next_run_date' => '2026-01-01',
        'is_active' => true,
        'created_by' => $admin->id,
    ]);

    $service = app(ExpenseService::class);
    $service->generateDueRecurring(Carbon::parse('2026-01-15'));

    $generated = Expense::query()->where('recurring_expense_id', $template->id)->sole();
    $generated->delete();

    // Financial records are soft-deleted, so the existence check has to see
    // through the soft-delete scope or the next cron run re-creates the charge.
    $template->update(['next_run_date' => '2026-01-01']);
    $service->generateDueRecurring(Carbon::parse('2026-01-15'));

    expect(Expense::withTrashed()->where('recurring_expense_id', $template->id)->count())->toBe(1)
        ->and(Expense::query()->where('recurring_expense_id', $template->id)->count())->toBe(0);
});
