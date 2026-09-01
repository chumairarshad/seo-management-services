<?php

use App\Enums\CredentialType;
use App\Enums\ProjectStatus;
use App\Livewire\Projects\Index as ProjectsIndex;
use App\Livewire\Projects\Show as ProjectShow;
use App\Models\Credential;
use App\Models\CredentialAccessLog;
use App\Models\Project;
use App\Models\User;
use App\Services\ProjectOwnershipService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

function makeAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

function makeProjectWithOwner(User $owner, array $overrides = []): Project
{
    $project = Project::factory()->create($overrides);

    app(ProjectOwnershipService::class)->sync($project, [
        ['user_id' => $owner->id, 'share_bps' => 10000],
    ]);

    return $project->fresh();
}

it('rejects project ownership that does not total 100%', function () {
    $admin = makeAdmin();
    $partner = User::factory()->create();
    $partner->assignRole('partner');

    Livewire::actingAs($admin)
        ->test(ProjectsIndex::class)
        ->call('create')
        ->set('domain', 'broken-shares.test')
        ->set('status', ProjectStatus::Setup->value)
        ->set('acquisition_cost', '1000')
        ->set('owners', [
            ['user_id' => (string) $admin->id, 'share_percent' => '60'],
            ['user_id' => (string) $partner->id, 'share_percent' => '30'],
        ])
        ->call('save')
        ->assertHasErrors(['owners']);

    expect(Project::query()->where('domain', 'broken-shares.test')->exists())->toBeFalse();
});

it('saves a project when ownership totals 100%', function () {
    $admin = makeAdmin();
    $partner = User::factory()->create();
    $partner->assignRole('partner');

    Livewire::actingAs($admin)
        ->test(ProjectsIndex::class)
        ->call('create')
        ->set('domain', 'balanced-shares.test')
        ->set('status', ProjectStatus::Live->value)
        ->set('acquisition_cost', '2500.50')
        ->set('owners', [
            ['user_id' => (string) $admin->id, 'share_percent' => '60'],
            ['user_id' => (string) $partner->id, 'share_percent' => '40'],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $project = Project::query()->where('domain', 'balanced-shares.test')->first();

    expect($project)->not->toBeNull()
        ->and($project->acquisition_cost_paisa)->toBe(250050)
        ->and($project->ownershipShareTotalBps())->toBe(10000);
});

it('masks credential secrets in HTML by default and logs reveal', function () {
    $admin = makeAdmin();
    $project = makeProjectWithOwner($admin, ['domain' => 'vault.test']);

    $credential = Credential::factory()->create([
        'project_id' => $project->id,
        'type' => CredentialType::CmsAdmin,
        'label' => 'WP Admin',
        'username' => 'admin-user',
        'secret' => 'plaintext-secret-value-xyz',
        'expires_on' => now()->addDays(20)->toDateString(),
    ]);

    Livewire::actingAs($admin)
        ->test(ProjectShow::class, ['project' => $project])
        ->assertSee('••••••••')
        ->assertDontSee('plaintext-secret-value-xyz')
        ->call('revealCredential', $credential->id)
        ->assertSee('plaintext-secret-value-xyz');

    expect(CredentialAccessLog::query()
        ->where('credential_id', $credential->id)
        ->where('user_id', $admin->id)
        ->where('action', 'reveal')
        ->exists())->toBeTrue();
});

it('blocks inactive users from protected routes even mid-session', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('admin');

    $this->actingAs($user);
    $this->get(route('projects.index'))->assertOk();

    $user->update(['is_active' => false]);

    $this->get(route('projects.index'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('scopes staff to assigned projects only', function () {
    $admin = makeAdmin();
    $staff = User::factory()->create();
    $staff->assignRole('staff');

    $assigned = makeProjectWithOwner($admin, ['domain' => 'assigned.test']);
    $hidden = makeProjectWithOwner($admin, ['domain' => 'hidden.test']);

    $assigned->teamMembers()->attach($staff->id);

    $this->actingAs($staff)
        ->get(route('projects.index'))
        ->assertOk()
        ->assertSee('assigned.test')
        ->assertDontSee('hidden.test');

    $this->actingAs($staff)
        ->get(route('projects.show', $hidden))
        ->assertForbidden();
});

it('only allows admin to manage ownership for m2', function () {
    $admin = makeAdmin();
    $supervisor = User::factory()->create();
    $project = makeProjectWithOwner($admin, ['domain' => 'own.test']);
    $supervisor->assignRole('supervisor', $project->id);

    expect($admin->can('manageOwnership', $project))->toBeTrue()
        ->and($supervisor->can('manageOwnership', $project))->toBeFalse();
});
