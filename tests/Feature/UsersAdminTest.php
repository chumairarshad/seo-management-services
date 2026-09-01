<?php

use App\Livewire\Users\Index as UsersIndex;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('creates a user with global role assignment', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $staffRoleId = Role::query()->where('name', 'staff')->value('id');

    Livewire::actingAs($admin)
        ->test(UsersIndex::class)
        ->call('create')
        ->set('name', 'New Hire')
        ->set('email', 'hire@example.com')
        ->set('password', 'password')
        ->set('is_active', true)
        ->set('selectedRoles', [$staffRoleId])
        ->call('save')
        ->assertHasNoErrors();

    $created = User::query()->where('email', 'hire@example.com')->first();

    expect($created)->not->toBeNull()
        ->and($created->roles->pluck('name')->all())->toContain('staff');
});

it('deactivates a user', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $target = User::factory()->create(['is_active' => true]);
    $target->assignRole('staff');

    Livewire::actingAs($admin)
        ->test(UsersIndex::class)
        ->call('toggleActive', $target->id)
        ->assertHasNoErrors();

    expect($target->fresh()->is_active)->toBeFalse();
});
