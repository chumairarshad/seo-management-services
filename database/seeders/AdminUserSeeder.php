<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Creates the first admin account.
 *
 * Password resolution, in order:
 *   1. ADMIN_PASSWORD from the environment, if set.
 *   2. "password" — only in the local and testing environments, for the demo.
 *   3. A freshly generated random password, printed once to the console.
 *
 * So `migrate --seed` on a real server can never quietly create an admin whose
 * password is published in this repository.
 */
class AdminUserSeeder extends Seeder
{
    public const EMAIL = 'admin@example.com';

    public const DEMO_PASSWORD = 'password';

    public function run(): void
    {
        [$password, $generated] = $this->resolvePassword();

        $admin = User::query()->updateOrCreate(
            ['email' => static::EMAIL],
            [
                'name' => 'System Admin',
                'password' => Hash::make($password),
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        $adminRoleId = Role::query()->where('name', 'admin')->value('id');

        if ($adminRoleId) {
            $admin->roles()->syncWithPivotValues(
                [$adminRoleId],
                ['project_id' => null],
            );
        }

        if ($generated) {
            $this->command?->newLine();
            $this->command?->warn('Generated an admin password. It is not stored anywhere else — copy it now:');
            $this->command?->line('  email:    '.static::EMAIL);
            $this->command?->line('  password: '.$password);
            $this->command?->newLine();
        }
    }

    /**
     * @return array{0: string, 1: bool} password, whether it was generated
     */
    protected function resolvePassword(): array
    {
        $fromEnv = (string) env('ADMIN_PASSWORD', '');

        if ($fromEnv !== '') {
            return [$fromEnv, false];
        }

        if (app()->environment('local', 'testing')) {
            return [static::DEMO_PASSWORD, false];
        }

        return [Str::password(20, symbols: false), true];
    }
}
