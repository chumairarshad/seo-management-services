<?php

use App\Livewire\Auth\Login;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('shows the login page', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Welcome back');
});

it('authenticates a valid active user', function () {
    $user = User::factory()->create([
        'email' => 'active@example.com',
        'password' => 'password',
        'is_active' => true,
    ]);
    $user->assignRole('staff');

    Livewire::test(Login::class)
        ->set('email', 'active@example.com')
        ->set('password', 'password')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

it('rejects invalid credentials', function () {
    User::factory()->create([
        'email' => 'active@example.com',
        'password' => 'password',
    ]);

    Livewire::test(Login::class)
        ->set('email', 'active@example.com')
        ->set('password', 'wrong-password')
        ->call('login')
        ->assertHasErrors(['email']);

    $this->assertGuest();
});

it('blocks inactive users from logging in', function () {
    User::factory()->inactive()->create([
        'email' => 'gone@example.com',
        'password' => 'password',
    ]);

    Livewire::test(Login::class)
        ->set('email', 'gone@example.com')
        ->set('password', 'password')
        ->call('login')
        ->assertHasErrors(['email']);

    $this->assertGuest();
});

it('logs out an authenticated user who is deactivated mid-session', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('staff');

    $this->actingAs($user);

    $user->update(['is_active' => false]);

    $this->get(route('dashboard'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});
