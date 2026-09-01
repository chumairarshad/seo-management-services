<?php

namespace App\Livewire\Settings;

use App\Support\AppSettings;
use App\Support\Currency;
use App\Support\DisplayTimezone;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Settings')]
class Index extends Component
{
    public string $org_name = '';

    public string $base_currency = '';

    public string $currency_symbol = '';

    public string $display_timezone = '';

    public bool $two_factor_required = false;

    public string $fx_rate = '';

    public string $fx_note = '';

    public string $credential_expiry_thresholds = '30,14,7';

    public string $credential_expiry_notify_emails = '';

    public int $late_arrival_hour = 10;

    public string $ai_monthly_budget_cents = '';

    public function mount(): void
    {
        abort_unless(Auth::user()?->hasPermission('settings.view'), 403);

        $this->org_name = (string) AppSettings::get('org_name', config('app.name', 'Portfolio OS'));
        $this->base_currency = Currency::code();
        $this->currency_symbol = Currency::symbol();
        $this->display_timezone = DisplayTimezone::name();
        $this->two_factor_required = (bool) AppSettings::get('two_factor_required', false);
        $this->late_arrival_hour = (int) AppSettings::get('late_arrival_hour', 10);

        $budget = AppSettings::get('ai_monthly_budget_cents');
        $this->ai_monthly_budget_cents = $budget !== null && $budget !== ''
            ? (string) $budget
            : (string) config('ai.monthly_budget_cents', 2000);

        $fx = AppSettings::get('fx_defaults', []);
        $fxKey = Currency::fxKey();
        $this->fx_rate = isset($fx[$fxKey]) && $fx[$fxKey] !== null
            ? (string) $fx[$fxKey]
            : '';
        $this->fx_note = (string) ($fx['note'] ?? '');

        $thresholds = AppSettings::get('credential_expiry_thresholds', [30, 14, 7]);
        $this->credential_expiry_thresholds = is_array($thresholds)
            ? implode(',', $thresholds)
            : '30,14,7';

        $emails = AppSettings::get('credential_expiry_notify_emails', []);
        $this->credential_expiry_notify_emails = is_array($emails)
            ? implode(', ', $emails)
            : '';
    }

    public function save(): void
    {
        abort_unless(Auth::user()?->hasPermission('settings.update'), 403);

        $validated = $this->validate([
            'org_name' => ['required', 'string', 'max:255'],
            'base_currency' => ['required', 'string', 'size:3', 'alpha'],
            'currency_symbol' => ['nullable', 'string', 'max:8'],
            'display_timezone' => ['required', 'string', 'timezone'],
            'two_factor_required' => ['boolean'],
            'late_arrival_hour' => ['required', 'integer', 'min:0', 'max:23'],
            'fx_rate' => ['nullable', 'string', 'max:32'],
            'fx_note' => ['nullable', 'string', 'max:500'],
            'credential_expiry_thresholds' => ['required', 'string', 'max:64'],
            'credential_expiry_notify_emails' => ['nullable', 'string', 'max:1000'],
            'ai_monthly_budget_cents' => ['nullable', 'integer', 'min:0', 'max:100000000'],
        ]);

        $thresholds = collect(explode(',', $validated['credential_expiry_thresholds']))
            ->map(fn ($v) => (int) trim($v))
            ->filter(fn ($v) => $v > 0)
            ->unique()
            ->values()
            ->all();

        if ($thresholds === []) {
            $this->addError('credential_expiry_thresholds', 'Provide at least one positive day threshold.');

            return;
        }

        $emails = collect(explode(',', $validated['credential_expiry_notify_emails'] ?? ''))
            ->map(fn ($v) => trim($v))
            ->filter()
            ->values()
            ->all();

        AppSettings::set('org_name', $validated['org_name']);
        AppSettings::set('base_currency', strtoupper($validated['base_currency']));
        AppSettings::set('currency_symbol', $validated['currency_symbol'] ?? '');
        AppSettings::set('display_timezone', $validated['display_timezone']);
        AppSettings::set('two_factor_required', (bool) $validated['two_factor_required']);
        AppSettings::set('late_arrival_hour', (int) $validated['late_arrival_hour']);
        AppSettings::set('fx_defaults', [
            Currency::fxKey() => $validated['fx_rate'] !== '' ? $validated['fx_rate'] : null,
            'note' => $validated['fx_note'] ?: 'Default rate for new revenue rows; each row freezes its own rate.',
        ]);
        AppSettings::set('credential_expiry_thresholds', $thresholds);
        AppSettings::set('credential_expiry_notify_emails', $emails);

        if (array_key_exists('ai_monthly_budget_cents', $validated) && $validated['ai_monthly_budget_cents'] !== null && $validated['ai_monthly_budget_cents'] !== '') {
            AppSettings::set('ai_monthly_budget_cents', (int) $validated['ai_monthly_budget_cents']);
        }

        $this->dispatch('toast', message: 'Settings saved.', tone: 'success');
    }

    public function render()
    {
        return view('livewire.settings.index');
    }
}
