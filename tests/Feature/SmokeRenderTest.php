<?php

use App\Models\DistributionRun;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\DemoMoneySeeder;
use Database\Seeders\DemoPeopleSeeder;
use Database\Seeders\DemoPortfolioSeeder;
use Database\Seeders\DemoWorkSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\TaskTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

/**
 * Renders every parameterless GET page as an admin against the demo dataset.
 * Catches Blade/view regressions that per-feature tests miss.
 */
it('renders every page against the demo seed data', function () {
    $this->seed([
        RolePermissionSeeder::class,
        SettingsSeeder::class,
        AdminUserSeeder::class,
        TaskTemplateSeeder::class,
        DemoPortfolioSeeder::class,
        DemoWorkSeeder::class,
        DemoPeopleSeeder::class,
        DemoMoneySeeder::class,
    ]);

    $admin = User::query()->where('email', AdminUserSeeder::EMAIL)->firstOrFail();

    $paths = collect(Route::getRoutes())
        ->filter(fn ($route) => in_array('GET', $route->methods(), true))
        ->reject(fn ($route) => str_contains($route->uri(), '{'))
        ->reject(fn ($route) => str_starts_with($route->uri(), '_ops'))
        // AI routes only exist when a provider key is configured; AiTest covers both states.
        ->reject(fn ($route) => $route->uri() === 'ai' || str_starts_with($route->uri(), 'ai/'))
        ->reject(fn ($route) => in_array($route->uri(), ['logout', 'up', 'storage/{path}'], true))
        ->map(fn ($route) => $route->uri())
        ->unique()
        ->values();

    expect($paths)->not->toBeEmpty();

    foreach ($paths as $path) {
        $status = $this->actingAs($admin)->get('/'.ltrim($path, '/'))->baseResponse->getStatusCode();

        expect($status)->toBeIn([200, 302], "GET /{$path} returned {$status}");
    }
})->group('smoke');

it('renders detail pages for the seeded records', function () {
    $this->seed([
        RolePermissionSeeder::class,
        SettingsSeeder::class,
        AdminUserSeeder::class,
        TaskTemplateSeeder::class,
        DemoPortfolioSeeder::class,
        DemoWorkSeeder::class,
        DemoPeopleSeeder::class,
        DemoMoneySeeder::class,
    ]);

    $admin = User::query()->where('email', AdminUserSeeder::EMAIL)->firstOrFail();
    $project = Project::query()->firstOrFail();
    $task = Task::query()->first();
    $run = DistributionRun::query()->first();

    $urls = array_filter([
        route('projects.show', $project, false),
        $task ? route('tasks.show', $task, false) : null,
        route('people.scorecard', $admin, false),
        route('money.partners.statement', $admin, false),
        $run ? route('money.distributions.show', $run, false) : null,
    ]);

    foreach ($urls as $url) {
        expect($this->actingAs($admin)->get($url)->baseResponse->getStatusCode())
            ->toBe(200, "GET {$url} did not return 200");
    }
})->group('smoke');
