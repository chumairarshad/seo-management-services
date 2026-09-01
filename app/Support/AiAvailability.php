<?php

namespace App\Support;

/**
 * Gate for all AI features. Empty API key → fully hidden, no routes worth using.
 */
class AiAvailability
{
    public static function enabled(): bool
    {
        return (bool) config('ai.enabled') && filled(config('ai.api_key'));
    }

    public static function provider(): string
    {
        return (string) config('ai.provider', 'openai');
    }

    public static function model(): string
    {
        return (string) config('ai.model');
    }

    public static function monthlyBudgetCents(): int
    {
        $override = AppSettings::get('ai_monthly_budget_cents');
        if ($override !== null && $override !== '') {
            return max(0, (int) $override);
        }

        return max(0, (int) config('ai.monthly_budget_cents', 2000));
    }

    public static function cacheTtl(): int
    {
        return max(0, (int) config('ai.cache_ttl', 3600));
    }
}
