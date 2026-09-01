<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Roles, permissions, settings, the admin account and task templates. Safe
     * to run anywhere, and idempotent.
     */
    protected array $foundation = [
        RolePermissionSeeder::class,
        SettingsSeeder::class,
        AdminUserSeeder::class,
        TaskTemplateSeeder::class,
    ];

    /**
     * Fake projects, credentials, work, people and money for evaluating the app.
     * Never appropriate on a real installation.
     */
    protected array $demo = [
        DemoPortfolioSeeder::class,
        DemoWorkSeeder::class,
        DemoPeopleSeeder::class,
        DemoMoneySeeder::class,
    ];

    public function run(): void
    {
        $this->call($this->foundation);

        if ($this->shouldSeedDemoData()) {
            $this->call($this->demo);

            return;
        }

        $this->command?->warn('Skipped the demo data seeders because this is not a local environment.');
        $this->command?->line('  Set SEED_DEMO_DATA=true to force them, but they create fake projects and financial records.');
    }

    /**
     * Demo data is opt-out locally and opt-in everywhere else, so that a
     * production `migrate --seed` cannot quietly invent revenue.
     */
    protected function shouldSeedDemoData(): bool
    {
        if (env('SEED_DEMO_DATA') !== null) {
            return filter_var(env('SEED_DEMO_DATA'), FILTER_VALIDATE_BOOLEAN);
        }

        return app()->environment(['local', 'testing']);
    }
}
