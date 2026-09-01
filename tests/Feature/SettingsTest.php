<?php

use App\Livewire\Settings\Index as SettingsIndex;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Support\AppSettings;
use App\Support\Currency;
use App\Support\DisplayTimezone;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(SettingsSeeder::class);
});

it('seeds foundation settings defaults from config', function () {
    expect(AppSettings::get('org_name'))->toBe(config('app.name'))
        ->and(AppSettings::get('base_currency'))->toBe(strtoupper((string) config('money.base.code')))
        ->and(AppSettings::get('display_timezone'))->toBe(config('app.display_timezone'))
        ->and(AppSettings::get('two_factor_required'))->toBeFalse()
        ->and(AppSettings::get('fx_defaults.note'))->not->toBeEmpty();
});

it('allows admins to update settings', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    Livewire::actingAs($admin)
        ->test(SettingsIndex::class)
        ->set('org_name', 'Acme Sites')
        ->set('base_currency', 'GBP')
        ->set('currency_symbol', '£')
        ->set('display_timezone', 'Europe/London')
        ->set('fx_rate', '1.17')
        ->call('save')
        ->assertHasNoErrors();

    AppSettings::flush();

    expect(AppSettings::get('org_name'))->toBe('Acme Sites')
        ->and(Setting::query()->where('key', 'org_name')->exists())->toBeTrue()
        ->and(Currency::code())->toBe('GBP')
        ->and(Currency::symbol())->toBe('£')
        ->and(DisplayTimezone::name())->toBe('Europe/London')
        ->and(AppSettings::get('fx_defaults.'.Currency::fxKey()))->toBe('1.17');
});

it('prevents users without settings.update from saving', function () {
    $viewerRole = Role::query()->create([
        'name' => 'settings_viewer',
        'label' => 'Settings Viewer',
    ]);
    $viewerRole->permissions()->attach(
        Permission::query()->where('name', 'settings.view')->value('id')
    );

    $viewer = User::factory()->create();
    $viewer->assignRole('settings_viewer');

    Livewire::actingAs($viewer)
        ->test(SettingsIndex::class)
        ->set('org_name', 'Hacked')
        ->call('save')
        ->assertForbidden();
});
