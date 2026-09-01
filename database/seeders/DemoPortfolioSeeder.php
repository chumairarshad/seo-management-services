<?php

namespace Database\Seeders;

use App\Enums\CredentialType;
use App\Enums\ProjectStatus;
use App\Models\Credential;
use App\Models\Project;
use App\Models\User;
use App\Services\ProjectOwnershipService;
use App\Support\Money;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoPortfolioSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@example.com')->first();

        $partner = User::query()->updateOrCreate(
            ['email' => 'partner@example.com'],
            [
                'name' => 'Demo Partner',
                'password' => Hash::make('password'),
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );
        $partner->assignRole('partner');

        $supervisor = User::query()->updateOrCreate(
            ['email' => 'supervisor@example.com'],
            [
                'name' => 'Demo Supervisor',
                'password' => Hash::make('password'),
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        $staff = User::query()->updateOrCreate(
            ['email' => 'staff@example.com'],
            [
                'name' => 'Demo Staff',
                'password' => Hash::make('password'),
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );
        $staff->assignRole('staff');

        $ownership = app(ProjectOwnershipService::class);

        $alpha = Project::query()->updateOrCreate(
            ['domain' => 'alpha-demo.test'],
            [
                'niche' => 'Finance tutorials',
                'cms' => 'WordPress',
                'start_date' => now()->subMonths(8)->toDateString(),
                'acquisition_cost_paisa' => Money::toMinor('25000'),
                'status' => ProjectStatus::Monetized,
                'notes' => 'Demo flagship site for Milestone 2 walkthrough.',
            ],
        );

        $beta = Project::query()->updateOrCreate(
            ['domain' => 'beta-demo.test'],
            [
                'niche' => 'Tech reviews',
                'cms' => 'Ghost',
                'start_date' => now()->subMonths(2)->toDateString(),
                'acquisition_cost_paisa' => Money::toMinor('8000'),
                'status' => ProjectStatus::Live,
                'notes' => 'Secondary demo project in setup→live transition.',
            ],
        );

        $ownership->sync($alpha, [
            ['user_id' => $admin?->id ?? $partner->id, 'share_bps' => 6000],
            ['user_id' => $partner->id, 'share_bps' => 4000],
        ]);

        $ownership->sync($beta, [
            ['user_id' => $admin?->id ?? $partner->id, 'share_bps' => 10000],
        ]);

        $alpha->teamMembers()->syncWithoutDetaching([
            $staff->id => ['assignment_note' => 'Content ops'],
            $supervisor->id => ['assignment_note' => 'QA lead'],
        ]);

        $beta->teamMembers()->syncWithoutDetaching([
            $staff->id => ['assignment_note' => 'Link building'],
        ]);

        // Supervisor access via role_user.project_id for alpha only
        $supervisor->roles()->detach();
        $supervisor->assignRole('supervisor', $alpha->id);

        Credential::query()->updateOrCreate(
            [
                'project_id' => $alpha->id,
                'type' => CredentialType::Registrar,
                'label' => 'Namecheap — alpha-demo.test',
            ],
            [
                'username' => 'alpha-registrar',
                'secret' => 'demo-registrar-secret',
                'url' => 'https://ap.www.namecheap.com',
                'expires_on' => now()->addDays(12)->toDateString(),
                'metadata' => [
                    'auto_renew' => true,
                    'transfer_lock' => true,
                ],
            ],
        );

        Credential::query()->updateOrCreate(
            [
                'project_id' => $alpha->id,
                'type' => CredentialType::Hosting,
                'label' => 'Hostinger shared — alpha',
            ],
            [
                'username' => 'u123456789',
                'secret' => 'demo-cpanel-password',
                'url' => 'https://hpanel.hostinger.com',
                'expires_on' => now()->addDays(28)->toDateString(),
                'metadata' => [
                    'provider' => 'Hostinger',
                    'ip' => '203.0.113.10',
                    'renewal_cost_paisa' => Money::toMinor('150'),
                ],
            ],
        );

        Credential::query()->updateOrCreate(
            [
                'project_id' => $alpha->id,
                'type' => CredentialType::Ssl,
                'label' => 'Let\'s Encrypt SSL',
            ],
            [
                'username' => null,
                'secret' => null,
                'url' => null,
                'expires_on' => now()->addDays(5)->toDateString(),
                'metadata' => ['issuer' => "Let's Encrypt"],
            ],
        );

        Credential::query()->updateOrCreate(
            [
                'project_id' => $alpha->id,
                'type' => CredentialType::CmsAdmin,
                'label' => 'WordPress admin',
            ],
            [
                'username' => 'wp-admin',
                'secret' => 'demo-wp-secret-do-not-use',
                'url' => 'https://alpha-demo.test/wp-admin',
                'expires_on' => null,
                'metadata' => [],
            ],
        );

        Credential::query()->updateOrCreate(
            [
                'project_id' => $beta->id,
                'type' => CredentialType::Registrar,
                'label' => 'Cloudflare Registrar — beta',
            ],
            [
                'username' => 'beta-reg',
                'secret' => 'beta-reg-secret',
                'url' => 'https://dash.cloudflare.com',
                'expires_on' => now()->addDays(60)->toDateString(),
                'metadata' => ['auto_renew' => false],
            ],
        );
    }
}
