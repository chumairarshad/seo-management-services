<?php

use App\Enums\LinkLiveStatus;
use App\Enums\LinkType;
use App\Enums\LinkWorkflowStatus;
use App\Enums\PartnerLedgerType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Livewire\Approvals\Queue as ApprovalQueue;
use App\Livewire\Links\Index as LinksIndex;
use App\Livewire\Money\PartnerStatement;
use App\Livewire\Tasks\Index as TasksIndex;
use App\Models\Link;
use App\Models\Task;
use App\Services\PartnerLedgerService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Security\SecurityHelpers;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('stops a partner reading another partner statement by changing the id', function () {
    $partner = SecurityHelpers::user('partner');
    $other = SecurityHelpers::user('partner');

    app(PartnerLedgerService::class)->credit(
        $other->id,
        5_000_00,
        PartnerLedgerType::CapitalIn,
        'Other partner capital',
        SecurityHelpers::user('admin'),
    );

    $component = Livewire::actingAs($partner)->test(PartnerStatement::class);

    expect($component->get('userId'))->toBe($partner->id);

    // `userId` is #[Locked]: mount resolves it against the caller's permissions,
    // and Livewire refuses a client-side change rather than rendering someone
    // else's ledger.
    $component->set('userId', $other->id);
})->throws(Exception::class);

it('will not load approval queue work from a project the user cannot access', function () {
    $supervisor = SecurityHelpers::user('supervisor');
    $mine = SecurityHelpers::project($supervisor, ['domain' => 'mine.test']);
    $mine->teamMembers()->attach($supervisor->id);

    $theirs = SecurityHelpers::project(SecurityHelpers::user('admin'), ['domain' => 'theirs.test']);

    $foreignTask = Task::query()->create([
        'project_id' => $theirs->id,
        'title' => 'Someone else project work',
        'type' => TaskType::AdHoc,
        'status' => TaskStatus::Submitted,
        'submitted_at' => now(),
        'created_by' => $supervisor->id,
    ]);

    $component = Livewire::actingAs($supervisor)->test(ApprovalQueue::class);

    // The queue is built from accessible projects only.
    expect(collect($component->get('queueKeys'))->pluck('id'))->not->toContain($foreignTask->id);

    // And the key list is locked, so it cannot be swapped for a foreign id to
    // render that item's full submission detail.
    $component->set('queueKeys', [['type' => 'task', 'id' => $foreignTask->id]]);
})->throws(Exception::class);

it('hides link budget totals for a project the user cannot access', function () {
    $staff = SecurityHelpers::user('staff');
    $mine = SecurityHelpers::project(SecurityHelpers::user('admin'), ['domain' => 'staff-mine.test']);
    $mine->teamMembers()->attach($staff->id);

    $theirs = SecurityHelpers::project(SecurityHelpers::user('admin'), ['domain' => 'staff-theirs.test']);

    Link::query()->create([
        'project_id' => $theirs->id,
        'source_url' => 'https://foreign-link.example/post',
        'source_domain' => 'foreign-link.example',
        'target_page' => 'https://staff-theirs.test/',
        'anchor_text' => 'foreign',
        'type' => LinkType::GuestPost,
        'live_status' => LinkLiveStatus::Live,
        'workflow_status' => LinkWorkflowStatus::Approved,
        'cost_paisa' => 12_345_00,
        'created_by' => SecurityHelpers::user('admin')->id,
        'link_date' => now('UTC')->toDateString(),
    ]);

    // `projectFilter` is bound to the query string, so the budget lookup must be
    // scoped even though the list rows already are.
    Livewire::actingAs($staff)
        ->test(LinksIndex::class)
        ->set('projectFilter', (string) $theirs->id)
        ->assertDontSee('12,345');
});

it('will not let a staff user reassign their own task without tasks.assign', function () {
    $staff = SecurityHelpers::user('staff');
    $other = SecurityHelpers::user('staff');
    $project = SecurityHelpers::project(SecurityHelpers::user('admin'), ['domain' => 'assign.test']);
    $project->teamMembers()->attach([$staff->id, $other->id]);

    $task = Task::query()->create([
        'project_id' => $project->id,
        'title' => 'Original title',
        'type' => TaskType::AdHoc,
        'assigned_to' => $staff->id,
        'status' => TaskStatus::Assigned,
        'created_by' => $staff->id,
    ]);

    Livewire::actingAs($staff)
        ->test(TasksIndex::class)
        ->call('edit', $task->id)
        ->set('title', 'Edited title')
        ->set('assigned_to', (string) $other->id)
        ->call('save');

    $task->refresh();

    // The edit they are entitled to lands; the reassignment does not.
    expect($task->title)->toBe('Edited title')
        ->and((int) $task->assigned_to)->toBe($staff->id);
});
