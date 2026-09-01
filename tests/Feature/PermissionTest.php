<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('unions permissions across multiple roles', function () {
    $user = User::factory()->create();
    $user->assignRole('staff');
    $user->assignRole('admin');

    expect($user->hasPermission('dashboard.view'))->toBeTrue()
        ->and($user->hasPermission('users.view'))->toBeTrue()
        ->and($user->hasPermission('settings.update'))->toBeTrue();
});

it('denies missing permissions', function () {
    $user = User::factory()->create();
    $user->assignRole('staff');

    expect($user->hasPermission('users.view'))->toBeFalse()
        ->and($user->hasPermission('settings.update'))->toBeFalse()
        ->and($user->hasPermission('dashboard.view'))->toBeTrue();
});

it('forbids users without users.view from the users page', function () {
    $user = User::factory()->create();
    $user->assignRole('staff');

    $this->actingAs($user)
        ->get(route('users.index'))
        ->assertForbidden();
});

it('allows admins into the users page', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $this->actingAs($user)
        ->get(route('users.index'))
        ->assertOk();
});

it('forbids users without settings.view from settings', function () {
    $user = User::factory()->create();
    $user->assignRole('partner');

    $this->actingAs($user)
        ->get(route('settings.index'))
        ->assertForbidden();
});
