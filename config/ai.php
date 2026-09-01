<?php

/**
 * Milestone 7 — optional AI assistant.
 * Leave AI_API_KEY (or provider-specific keys) empty: AI stays fully hidden.
 */
$apiKey = env('AI_API_KEY')
    ?: env('OPENAI_API_KEY')
    ?: env('ANTHROPIC_API_KEY');

$provider = strtolower((string) env('AI_PROVIDER', 'openai'));

$defaultModels = [
    'openai' => 'gpt-4o-mini',
    'anthropic' => 'claude-3-5-haiku-latest',
];

return [

    'enabled' => filled($apiKey),

    'provider' => in_array($provider, ['openai', 'anthropic'], true) ? $provider : 'openai',

    'api_key' => $apiKey ?: null,

    'model' => env('AI_MODEL') ?: ($defaultModels[$provider] ?? $defaultModels['openai']),

    /*
    | Soft monthly spend cap in USD cents (est. from token counts).
    | Can be overridden by AppSettings key ai_monthly_budget_cents.
    */
    'monthly_budget_cents' => (int) env('AI_MONTHLY_BUDGET_CENTS', 2000),

    'cache_ttl' => (int) env('AI_CACHE_TTL', 3600),

    'timeout' => (int) env('AI_HTTP_TIMEOUT', 45),

    /*
    | Rough cost estimate (USD cents per 1_000 tokens) for budget tracking — not invoicing.
    */
    'cost_per_1k_tokens_cents' => (float) env('AI_COST_PER_1K_TOKENS_CENTS', 0.15),

    'openai' => [
        'base_url' => rtrim((string) env('OPENAI_BASE_URL', 'https://api.openai.com/v1'), '/'),
    ],

    'anthropic' => [
        'base_url' => rtrim((string) env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com/v1'), '/'),
        'version' => env('ANTHROPIC_VERSION', '2023-06-01'),
    ],

];
