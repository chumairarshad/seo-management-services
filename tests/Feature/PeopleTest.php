<?php

use App\Enums\ArticleStatus;
use App\Enums\AttendanceStatus;
use App\Enums\LinkLiveStatus;
use App\Enums\LinkType;
use App\Enums\LinkWorkflowStatus;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Livewire\Auth\Login;
use App\Livewire\People\AttendanceIndex;
use App\Livewire\People\ScorecardShow;
use App\Models\Article;
use App\Models\AttendanceDay;
use App\Models\Link;
use App\Models\LoginHistory;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\LoginHistoryService;
use App\Services\PeopleVisibilityService;
use App\Services\ProjectOwnershipService;
use App\Services\ScorecardService;
use App\Support\AppSettings;
use App\Support\DisplayTimezone;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(SettingsSeeder::class);
    AppSettings::flush();
});

function peopleAdmin(): User
{
    $user = User::factory()->create(['name' => 'Admin']);
    $user->assignRole('admin');

    return $user;
}

function peopleStaff(): User
{
    $user = User::factory()->create(['name' => 'Staffer', 'email' => 'staffer@test.com']);
    $user->assignRole('staff');

    return $user;
}

function peopleSupervisor(): User
{
    $user = User::factory()->create(['name' => 'Super', 'email' => 'super@test.com']);
    $user->assignRole('supervisor');

    return $user;
}

function peopleProject(User $admin, User $staff): Project
{
    $project = Project::factory()->create(['domain' => 'people-demo.test']);
    app(ProjectOwnershipService::class)->sync($project, [
        ['user_id' => $admin->id, 'share_bps' => 10000],
    ]);
    $project->teamMembers()->syncWithoutDetaching([$staff->id => ['assignment_note' => 'ops']]);

    return $project->fresh();
}

it('records login_history and attendance present on successful login', function () {
    $user = peopleStaff();
    $user->forceFill(['password' => 'password'])->save();

    expect(LoginHistory::query()->where('user_id', $user->id)->count())->toBe(0);

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard'));

    $history = LoginHistory::query()->where('user_id', $user->id)->first();
    expect($history)->not->toBeNull()
        ->and($history->logged_in_at)->not->toBeNull()
        ->and($history->ip_address)->not->toBeNull();

    $localDate = DisplayTimezone::localDate($history->logged_in_at);
    $day = AttendanceDay::query()
        ->where('user_id', $user->id)
        ->whereDate('local_date', $localDate)
        ->first();

    expect($day)->not->toBeNull()
        ->and($day->status)->toBe(AttendanceStatus::Present)
        ->and($day->first_login_at)->not->toBeNull();
});

it('marks leave for a team member by supervisor', function () {
    $admin = peopleAdmin();
    $staff = peopleStaff();
    $supervisor = peopleSupervisor();
    $project = peopleProject($admin, $staff);
    $supervisor->roles()->detach();
    $supervisor->assignRole('supervisor', $project->id);
    $project->teamMembers()->syncWithoutDetaching([
        $supervisor->id => ['assignment_note' => 'lead'],
        $staff->id => ['assignment_note' => 'ops'],
    ]);

    $date = DisplayTimezone::today();

    Livewire::actingAs($supervisor)
        ->test(AttendanceIndex::class)
        ->set('userId', $staff->id)
        ->set('markDate', $date)
        ->set('markStatus', 'leave')
        ->set('markNotes', 'Family matter')
        ->call('markLeave')
        ->assertHasNoErrors();

    $day = AttendanceDay::query()
        ->where('user_id', $staff->id)
        ->whereDate('local_date', $date)
        ->first();

    expect($day)->not->toBeNull()
        ->and($day->status)->toBe(AttendanceStatus::Leave)
        ->and($day->marked_by)->toBe($supervisor->id)
        ->and($day->notes)->toBe('Family matter');
});

it('computes scorecard numbers deterministically from fixtures', function () {
    $admin = peopleAdmin();
    $staff = peopleStaff();
    $project = peopleProject($admin, $staff);
    $tz = DisplayTimezone::name();
    $month = '2026-03';
    $firstLocal = '2026-03-05';

    // On-time completed: due 10th, approved 8th local
    $submitted1 = Carbon::parse('2026-03-07 09:00:00', $tz)->utc();
    $approved1 = Carbon::parse('2026-03-08 12:00:00', $tz)->utc();
    Task::query()->create([
        'project_id' => $project->id,
        'title' => 'On-time task',
        'type' => TaskType::AdHoc,
        'status' => TaskStatus::Approved,
        'assigned_to' => $staff->id,
        'due_date' => '2026-03-10',
        'submitted_at' => $submitted1,
        'approved_at' => $approved1,
        'approved_by' => $admin->id,
        'created_by' => $admin->id,
        'created_at' => Carbon::parse('2026-03-01 10:00:00', $tz)->utc(),
        'is_recurrence_source' => false,
    ]);

    // Late completed: due 5th, approved 12th
    $submitted2 = Carbon::parse('2026-03-10 09:00:00', $tz)->utc();
    $approved2 = Carbon::parse('2026-03-12 15:00:00', $tz)->utc();
    Task::query()->create([
        'project_id' => $project->id,
        'title' => 'Late task',
        'type' => TaskType::AdHoc,
        'status' => TaskStatus::Approved,
        'assigned_to' => $staff->id,
        'due_date' => '2026-03-05',
        'submitted_at' => $submitted2,
        'approved_at' => $approved2,
        'approved_by' => $admin->id,
        'created_by' => $admin->id,
        'created_at' => Carbon::parse('2026-03-01 10:00:00', $tz)->utc(),
        'is_recurrence_source' => false,
    ]);

    // Rejected once
    Task::query()->create([
        'project_id' => $project->id,
        'title' => 'Rejected task',
        'type' => TaskType::AdHoc,
        'status' => TaskStatus::Rejected,
        'assigned_to' => $staff->id,
        'due_date' => '2026-03-20',
        'submitted_at' => Carbon::parse('2026-03-15 10:00:00', $tz)->utc(),
        'rejection_reason' => 'Needs rewrite',
        'created_by' => $admin->id,
        'created_at' => Carbon::parse('2026-03-01 10:00:00', $tz)->utc(),
        'updated_at' => Carbon::parse('2026-03-16 10:00:00', $tz)->utc(),
        'is_recurrence_source' => false,
    ]);

    Article::query()->create([
        'project_id' => $project->id,
        'title' => 'Fixture article',
        'target_keyword' => 'fixture keyword unique',
        'writer_id' => $staff->id,
        'status' => ArticleStatus::Approved,
        'word_count_actual' => 1200,
        'cost_paisa' => 10_000_00,
        'created_at' => Carbon::parse("{$firstLocal} 10:00:00", $tz)->utc(),
        'submitted_at' => Carbon::parse('2026-03-06 10:00:00', $tz)->utc(),
        'approved_at' => Carbon::parse('2026-03-07 10:00:00', $tz)->utc(),
        'approved_by' => $admin->id,
        'created_by' => $admin->id,
    ]);

    Link::query()->create([
        'project_id' => $project->id,
        'source_url' => 'https://fixture-links.example/post',
        'source_domain' => 'fixture-links.example',
        'target_page' => 'https://people-demo.test/',
        'anchor_text' => 'demo',
        'type' => LinkType::GuestPost,
        'live_status' => LinkLiveStatus::Live,
        'workflow_status' => LinkWorkflowStatus::Approved,
        'cost_paisa' => 4_000_00,
        'assigned_to' => $staff->id,
        'created_by' => $staff->id,
        'approved_by' => $admin->id,
        'approved_at' => Carbon::parse('2026-03-09 12:00:00', $tz)->utc(),
        'submitted_at' => Carbon::parse('2026-03-08 12:00:00', $tz)->utc(),
        'link_date' => '2026-03-09',
    ]);

    $card = app(ScorecardService::class)->forUserMonth($staff, $month);

    expect($card['tasks']['completed'])->toBe(2)
        ->and($card['tasks']['on_time'])->toBe(1)
        ->and($card['tasks']['on_time_pct'])->toBe(50.0)
        ->and($card['tasks']['rejected'])->toBe(1)
        ->and($card['tasks']['rejection_rate_pct'])->toBe(round(1 / 3 * 100, 1))
        ->and($card['articles']['approved'])->toBe(1)
        ->and($card['articles']['words'])->toBe(1200)
        ->and($card['links']['approved'])->toBe(1)
        ->and($card['output_cost_paisa'])->toBe(10_000_00 + 4_000_00);

    // Turnaround: task1 07 09:00 → 08 12:00 = 27h; task2 10 09:00 → 12 15:00 = 54h; avg 40.5
    expect($card['tasks']['avg_turnaround_hours'])->toBe(40.5);
});

it('forbids staff from viewing another employee scorecard', function () {
    $admin = peopleAdmin();
    $staff = peopleStaff();
    $other = User::factory()->create(['name' => 'Other', 'email' => 'other@test.com']);
    $other->assignRole('staff');
    peopleProject($admin, $staff);

    Livewire::actingAs($staff)
        ->test(ScorecardShow::class, ['userId' => $other->id])
        ->assertForbidden();
});

it('allows staff to view own scorecard route', function () {
    $staff = peopleStaff();

    Livewire::actingAs($staff)
        ->test(ScorecardShow::class, ['userId' => $staff->id])
        ->assertSuccessful()
        ->assertSee('Scorecard');
});

it('keeps leave override when login still syncs first_login', function () {
    $admin = peopleAdmin();
    $staff = peopleStaff();
    $svc = app(AttendanceService::class);
    $tz = DisplayTimezone::name();
    $date = '2026-04-15';

    $svc->markOverride($staff, $date, AttendanceStatus::Leave, $admin, 'pre-marked');

    $loginAt = Carbon::parse("{$date} 11:00:00", $tz)->utc();
    app(LoginHistoryService::class)->record($staff, request(), $loginAt);

    $day = AttendanceDay::query()->where('user_id', $staff->id)->whereDate('local_date', $date)->first();
    expect($day->status)->toBe(AttendanceStatus::Leave)
        ->and($day->first_login_at)->not->toBeNull();
});

it('scopes people visibility: staff self only, supervisor team', function () {
    $admin = peopleAdmin();
    $staff = peopleStaff();
    $other = User::factory()->create();
    $other->assignRole('staff');
    $supervisor = peopleSupervisor();
    $project = peopleProject($admin, $staff);
    $supervisor->roles()->detach();
    $supervisor->assignRole('supervisor', $project->id);
    $project->teamMembers()->syncWithoutDetaching([
        $staff->id => ['assignment_note' => 'ops'],
        $supervisor->id => ['assignment_note' => 'lead'],
    ]);

    $visibility = app(PeopleVisibilityService::class);

    expect($visibility->canViewSubject($staff, $staff))->toBeTrue()
        ->and($visibility->canViewSubject($staff, $other))->toBeFalse()
        ->and($visibility->canViewSubject($supervisor, $staff))->toBeTrue()
        ->and($visibility->canViewSubject($admin, $other))->toBeTrue();
});
