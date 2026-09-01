<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Base reporting currency
    |--------------------------------------------------------------------------
    |
    | Every report, P&L row, expense and distribution rolls up to this currency.
    | All monetary values are persisted as integer minor units — never floats.
    |
    | `exponent` is the number of minor-unit digits (2 = cents/pence/paisa,
    | 0 = currencies with no subunit such as JPY or KRW, 3 = KWD/BHD/OMR).
    |
    | Choose the exponent BEFORE entering data. Stored integers carry no scale
    | of their own, so changing it later silently reinterprets every existing
    | amount by a factor of ten. The code is deliberately env-only for this
    | reason; only the currency code and symbol are editable in Settings.
    |
    */

    'base' => [
        'code' => env('MONEY_BASE_CURRENCY', 'USD'),
        'exponent' => (int) env('MONEY_BASE_EXPONENT', 2),
        'symbol' => env('MONEY_BASE_SYMBOL', '$'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Secondary (source) currency for revenue input
    |--------------------------------------------------------------------------
    |
    | Revenue may be entered in this currency and is converted to the base
    | currency at a rate that is frozen onto each row, so historical revenue
    | never re-converts when the rate moves. Set `default_rate` to the value
    | you want pre-filled for new rows, or leave it and set the live default
    | under Settings → Money.
    |
    | Set `code` to the same value as the base currency to effectively run
    | single-currency.
    |
    */

    'source' => [
        'code' => env('MONEY_SOURCE_CURRENCY', 'EUR'),
        'exponent' => (int) env('MONEY_SOURCE_EXPONENT', 2),
        'symbol' => env('MONEY_SOURCE_SYMBOL', '€'),
        'default_rate' => (string) env('MONEY_SOURCE_DEFAULT_RATE', '1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Reporting thresholds
    |--------------------------------------------------------------------------
    |
    | `large_expense_threshold` is in base-currency MAJOR units and only affects
    | which expenses the reports flag as notable. It changes no stored amount.
    |
    */

    'large_expense_threshold' => (string) env('MONEY_LARGE_EXPENSE_THRESHOLD', '5000'),

];
