<?php

use App\Enums\CredentialType;
use App\Livewire\Projects\Show as ProjectShow;
use App\Models\Credential;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Security\SecurityHelpers;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('keeps a revealed secret out of the Livewire component snapshot', function () {
    $admin = SecurityHelpers::user('admin');
    $project = SecurityHelpers::project($admin, ['domain' => 'vault.test']);

    $credential = Credential::factory()->create([
        'project_id' => $project->id,
        'type' => CredentialType::CmsAdmin,
        'label' => 'WP Admin',
        'username' => 'vault-username',
        'secret' => 'plaintext-secret-value-xyz',
    ]);

    $component = Livewire::actingAs($admin)
        ->test(ProjectShow::class, ['project' => $project])
        ->call('revealCredential', $credential->id);

    // The secret is on screen because the user asked for it...
    $component->assertSee('plaintext-secret-value-xyz');

    // ...but it must not live in component state, which would re-transmit it on
    // every later request on this page and expose it to any XSS on that page.
    $state = SecurityHelpers::serialisedState($component->instance());

    expect($state)->not->toContain('plaintext-secret-value-xyz')
        ->and($state)->toContain('revealedIds');
});

it('stops rendering a revealed secret once the reveal permission is gone', function () {
    $admin = SecurityHelpers::user('admin');
    $project = SecurityHelpers::project($admin, ['domain' => 'revoke.test']);

    $credential = Credential::factory()->create([
        'project_id' => $project->id,
        'type' => CredentialType::CmsAdmin,
        'label' => 'WP Admin',
        'secret' => 'revoked-secret-value',
    ]);

    $component = Livewire::actingAs($admin)
        ->test(ProjectShow::class, ['project' => $project])
        ->call('revealCredential', $credential->id)
        ->assertSee('revoked-secret-value');

    // Authorisation is re-checked per render, not only at reveal time.
    $admin->roles()->detach();
    $admin->assignRole('supervisor');
    $admin->refresh();

    $component->call('$refresh')->assertDontSee('revoked-secret-value');
});

it('forgets a revealed secret when the credential is hidden again', function () {
    $admin = SecurityHelpers::user('admin');
    $project = SecurityHelpers::project($admin, ['domain' => 'hide.test']);

    $credential = Credential::factory()->create([
        'project_id' => $project->id,
        'type' => CredentialType::CmsAdmin,
        'label' => 'WP Admin',
        'secret' => 'hide-me-secret',
    ]);

    Livewire::actingAs($admin)
        ->test(ProjectShow::class, ['project' => $project])
        ->call('revealCredential', $credential->id)
        ->assertSee('hide-me-secret')
        ->call('hideCredential', $credential->id)
        ->assertDontSee('hide-me-secret')
        ->assertSet('revealedIds', []);
});
