<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Support\AppSettings;
use App\Support\Currency;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'org_name' => ['value' => config('app.name', 'Portfolio OS')],
            'base_currency' => ['value' => strtoupper((string) config('money.base.code', 'USD'))],
            'currency_symbol' => ['value' => (string) config('money.base.symbol', '')],
            'display_timezone' => ['value' => (string) config('app.display_timezone', 'UTC')],
            'two_factor_required' => ['value' => false],
            'late_arrival_hour' => ['value' => 10],
            'fx_defaults' => [
                'value' => [
                    Currency::fxKey() => Currency::defaultFxRate(),
                    'note' => 'Default '.Currency::fxLabel().' rate used when recording new revenue; historical rows keep their own frozen rate.',
                ],
            ],
            'credential_expiry_thresholds' => [
                'value' => [30, 14, 7],
            ],
            'credential_expiry_notify_emails' => [
                'value' => [],
            ],
            'ai_monthly_budget_cents' => [
                'value' => (int) config('ai.monthly_budget_cents', 2000),
            ],
        ];

        foreach ($defaults as $key => $payload) {
            Setting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $payload['value']],
            );
        }

        AppSettings::flush();
    }
}
