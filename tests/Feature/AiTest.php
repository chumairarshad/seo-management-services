<?php

use App\Enums\RevenueSource;
use App\Enums\TaskStatus;
use App\Livewire\Ai\Ask as AiAsk;
use App\Models\AiDraftNote;
use App\Models\AiUsageLog;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\Ai\AiAssistantService;
use App\Services\Ai\MessageBuilder;
use App\Services\Ai\QuestionMapper;
use App\Services\Ai\SafePayloadSanitizer;
use App\Services\Ai\WhitelistReportService;
use App\Services\ProjectOwnershipService;
use App\Services\RevenueService;
use App\Support\AiAvailability;
use App\Support\DisplayTimezone;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

function enableAiForTests(string $provider = 'openai'): void
{
    config([
        'ai.enabled' => true,
        'ai.api_key' => 'test-key-not-real',
        'ai.provider' => $provider,
        'ai.model' => $provider === 'anthropic' ? 'claude-test' : 'gpt-test',
        'ai.monthly_budget_cents' => 5000,
        'ai.cache_ttl' => 0, // disable cache noise in most tests
        'ai.cost_per_1k_tokens_cents' => 1.0,
        'ai.openai.base_url' => 'https://api.openai.com/v1',
        'ai.anthropic.base_url' => 'https://api.anthropic.com/v1',
    ]);
}

function disableAiForTests(): void
{
    config([
        'ai.enabled' => false,
        'ai.api_key' => null,
    ]);
}

function aiAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

function aiPartnerOn(Project $project): User
{
    $user = User::factory()->create();
    $user->assignRole('partner');
    app(ProjectOwnershipService::class)->sync($project, [
        ['user_id' => $user->id, 'share_bps' => 10000],
    ]);

    return $user->fresh();
}

function aiStaffOn(Project $project): User
{
    $user = User::factory()->create();
    $user->assignRole('staff');
    $project->teamMembers()->syncWithoutDetaching([$user->id]);

    return $user->fresh();
}

function fakeOpenAiChat(string $content = 'AI says the portfolio looks fine based on the source figures.'): void
{
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [
                ['message' => ['content' => $content]],
            ],
            'usage' => [
                'prompt_tokens' => 120,
                'completion_tokens' => 40,
            ],
        ], 200),
        'api.anthropic.com/*' => Http::response([
            'content' => [
                ['type' => 'text', 'text' => $content],
            ],
            'usage' => [
                'input_tokens' => 100,
                'output_tokens' => 30,
            ],
        ], 200),
    ]);
}

it('hides AI completely when no API key is configured', function () {
    disableAiForTests();

    expect(AiAvailability::enabled())->toBeFalse();

    $admin = aiAdmin();

    $this->actingAs($admin)
        ->get(route('ai.ask'))
        ->assertNotFound();

    $this->actingAs($admin)
        ->get(route('ai.drafts'))
        ->assertNotFound();

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Ask your data', false)
        ->assertDontSee('AI assistant', false);
});

it('maps plain English questions to whitelist report keys', function () {
    $mapper = new QuestionMapper;

    expect($mapper->map('Which sites dropped revenue this month vs prior?'))->toBe('revenue_drops')
        ->and($mapper->map('How many overdue tasks by assignee?'))->toBe('overdue_tasks')
        ->and($mapper->map('Give me a portfolio summary'))->toBe('portfolio_summary')
        ->and($mapper->map('Any expense spikes this month?'))->toBe('expense_spikes')
        ->and($mapper->map('Attendance absence overview today'))->toBe('attendance')
        ->and($mapper->map('Top performing sites by profit'))->toBe('top_sites')
        ->and($mapper->map('How many open approvals?'))->toBe('open_approvals')
        ->and($mapper->map('Employee scorecard summary for my team'))->toBe('scorecard');
});

it('answers a whitelisted question with AI-generated label and source figures when key is set', function () {
    enableAiForTests();
    fakeOpenAiChat('Revenue dips are limited based on source figures.');

    $admin = aiAdmin();
    $project = Project::factory()->create(['domain' => 'alpha.test']);
    app(ProjectOwnershipService::class)->sync($project, [
        ['user_id' => $admin->id, 'share_bps' => 10000],
    ]);

    $thisMonth = DisplayTimezone::now()->format('Y-m');
    $prior = DisplayTimezone::now()->subMonth()->format('Y-m');
    $revenue = app(RevenueService::class);
    $revenue->create([
        'project_id' => $project->id,
        'period_month' => $prior,
        'source' => RevenueSource::Adsense->value,
        'amount_usd' => '200.00',
        'fx_rate' => '280.00',
    ], $admin);
    $revenue->create([
        'project_id' => $project->id,
        'period_month' => $thisMonth,
        'source' => RevenueSource::Adsense->value,
        'amount_usd' => '50.00',
        'fx_rate' => '280.00',
    ], $admin);

    Livewire::actingAs($admin)
        ->test(AiAsk::class)
        ->set('question', 'Which sites dropped revenue this month vs prior?')
        ->call('ask')
        ->assertHasNoErrors()
        ->assertSet('aiGenerated', true)
        ->assertSet('reportKey', 'revenue_drops')
        ->assertSee('AI-generated')
        ->assertSee('Source figures');

    expect(AiUsageLog::query()->count())->toBeGreaterThan(0)
        ->and(AiUsageLog::query()->where('feature', 'ask')->where('success', true)->exists())->toBeTrue();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'chat/completions'));
});

it('scopes partner ask answers to owned projects only', function () {
    enableAiForTests();
    fakeOpenAiChat('Scoped report returned.');

    $admin = aiAdmin();
    $owned = Project::factory()->create(['domain' => 'owned.test']);
    $other = Project::factory()->create(['domain' => 'other.test']);
    app(ProjectOwnershipService::class)->sync($owned, [
        ['user_id' => $admin->id, 'share_bps' => 10000],
    ]);
    app(ProjectOwnershipService::class)->sync($other, [
        ['user_id' => $admin->id, 'share_bps' => 10000],
    ]);

    $partner = User::factory()->create();
    $partner->assignRole('partner');
    // Partner owns only "owned"
    app(ProjectOwnershipService::class)->sync($owned, [
        ['user_id' => $partner->id, 'share_bps' => 10000],
    ]);
    // other project stays with admin only
    app(ProjectOwnershipService::class)->sync($other, [
        ['user_id' => $admin->id, 'share_bps' => 10000],
    ]);

    $month = DisplayTimezone::now()->format('Y-m');
    $svc = app(RevenueService::class);
    $svc->create([
        'project_id' => $owned->id,
        'period_month' => $month,
        'source' => RevenueSource::Adsense->value,
        'amount_usd' => '100.00',
        'fx_rate' => '280.00',
    ], $admin);
    $svc->create([
        'project_id' => $other->id,
        'period_month' => $month,
        'source' => RevenueSource::Adsense->value,
        'amount_usd' => '900.00',
        'fx_rate' => '280.00',
    ], $admin);

    $report = app(WhitelistReportService::class)->topPerformingSites($partner->fresh());
    $domains = collect($report['rows'])->pluck('domain')->all();

    expect($domains)->toContain('owned.test')
        ->and($domains)->not->toContain('other.test');

    $assistant = app(AiAssistantService::class);
    $answer = $assistant->ask($partner->fresh(), 'Top performing sites by profit');
    expect($answer['report_key'])->toBe('top_sites')
        ->and($answer['ai_generated'])->toBeTrue()
        ->and(json_encode($answer['source_figures']))->not->toContain('other.test');
});

it('denies staff finance reports outside their permissions', function () {
    enableAiForTests();

    $project = Project::factory()->create();
    $staff = aiStaffOn($project);

    $report = app(WhitelistReportService::class)->revenueDrops($staff);

    expect($report['denied'] ?? false)->toBeTrue()
        ->and($report['rows'])->toBe([]);
});

it('never includes credential secrets or bank details in outgoing context', function () {
    $sanitizer = new SafePayloadSanitizer;
    $builder = new MessageBuilder($sanitizer);

    $dirty = [
        'key' => 'portfolio_summary',
        'title' => 'Portfolio',
        'figures' => [
            'revenue_paisa' => 1000,
            'api_key' => 'sk-super-secret-should-go',
            'password' => 'hunter2',
        ],
        'rows' => [
            [
                'domain' => 'a.test',
                'secret' => 'vault-password-value',
                'payout_details' => 'IBAN PK00BANK123',
                'bank_account' => '9999',
                'credential_username' => 'admin@host',
            ],
        ],
        'narrative_seed' => 'ok',
    ];

    $safe = $builder->outgoingContextForAsk($dirty);
    $encoded = json_encode($safe);

    expect($encoded)->not->toContain('sk-super-secret')
        ->and($encoded)->not->toContain('hunter2')
        ->and($encoded)->not->toContain('IBAN PK00BANK')
        ->and($encoded)->not->toContain('vault-password')
        ->and($encoded)->toContain('[redacted]')
        ->and($encoded)->toContain('a.test');

    $messages = $builder->buildAskMessages('portfolio summary', $dirty);
    $joined = collect($messages['messages'])->pluck('content')->implode("\n");
    expect($joined)->not->toContain('sk-super-secret')
        ->and($joined)->not->toContain('hunter2')
        ->and($sanitizer->containsSecretMaterial($joined))->toBeFalse();
});

it('shows the AI ask box on the dashboard when enabled', function () {
    enableAiForTests();
    $admin = aiAdmin();

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Ask your data')
        ->assertSee('AI assistant');

    $this->actingAs($admin)
        ->get(route('ai.ask'))
        ->assertOk();
});

it('logs token usage for ask and refuses over budget', function () {
    enableAiForTests();
    config(['ai.monthly_budget_cents' => 1]); // tiny budget
    fakeOpenAiChat('ok');

    $admin = aiAdmin();

    // First call consumes budget
    app(AiAssistantService::class)->ask($admin, 'Give me a portfolio summary');
    expect(AiUsageLog::query()->sum('estimated_cost_cents'))->toBeGreaterThan(0);

    // Force spent past budget
    AiUsageLog::query()->create([
        'user_id' => $admin->id,
        'feature' => 'ask',
        'provider' => 'openai',
        'model' => 'gpt-test',
        'report_key' => 'portfolio_summary',
        'prompt_tokens' => 1000,
        'completion_tokens' => 1000,
        'total_tokens' => 2000,
        'estimated_cost_cents' => 999,
        'cached' => false,
        'success' => true,
    ]);

    expect(fn () => app(AiAssistantService::class)->ask($admin, 'Give me a portfolio summary'))
        ->toThrow(RuntimeException::class, 'budget');
});

it('scopes overdue tasks report to accessible projects for staff', function () {
    enableAiForTests();

    $mine = Project::factory()->create(['domain' => 'mine.test']);
    $theirs = Project::factory()->create(['domain' => 'theirs.test']);
    $staff = aiStaffOn($mine);
    $other = User::factory()->create();

    Task::query()->create([
        'project_id' => $mine->id,
        'title' => 'Mine overdue',
        'status' => TaskStatus::Assigned->value,
        'assigned_to' => $staff->id,
        'due_date' => DisplayTimezone::now()->subDays(3)->toDateString(),
        'created_by' => $staff->id,
    ]);
    Task::query()->create([
        'project_id' => $theirs->id,
        'title' => 'Theirs overdue',
        'status' => TaskStatus::Assigned->value,
        'assigned_to' => $other->id,
        'due_date' => DisplayTimezone::now()->subDays(3)->toDateString(),
        'created_by' => $other->id,
    ]);

    $report = app(WhitelistReportService::class)->overdueTasks($staff);
    $titles = collect($report['rows'])->pluck('title')->all();

    expect($titles)->toContain('Mine overdue')
        ->and($titles)->not->toContain('Theirs overdue');
});

it('drafts monthly notes without requiring LLM', function () {
    enableAiForTests();
    $admin = aiAdmin();
    Project::factory()->create();

    $this->artisan('ai:draft-monthly-summaries', ['--no-llm' => true])
        ->assertSuccessful();

    expect(AiDraftNote::query()->count())->toBeGreaterThan(0);
});
