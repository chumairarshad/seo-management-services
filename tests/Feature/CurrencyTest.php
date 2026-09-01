<?php

use App\Models\Setting;
use App\Support\AppSettings;
use App\Support\Currency;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/**
 * A saved base_currency setting deliberately overrides config/money.php, so the
 * config-level tests below have to clear it to isolate the fallback path.
 */
function useCurrencyConfig(array $base, ?array $source = null): void
{
    config()->set('money.base', $base);

    if ($source !== null) {
        config()->set('money.source', $source);
    }

    if (Schema::hasTable('settings')) {
        Setting::query()->whereIn('key', ['base_currency', 'currency_symbol'])->delete();
    }

    AppSettings::flush();
}

it('parses and formats two-decimal currencies', function () {
    useCurrencyConfig(['code' => 'USD', 'exponent' => 2, 'symbol' => '$']);

    expect(Money::toMinor('1234.56'))->toBe(123456)
        ->and(Money::toMinor('1,234.5'))->toBe(123450)
        ->and(Money::toMinor('-10.99'))->toBe(-1099)
        ->and(Money::toMinor(''))->toBe(0)
        ->and(Money::toMinor('not a number'))->toBe(0)
        ->and(Money::fromMinor(123456))->toBe('1234.56')
        ->and(Money::fromMinor(-1099))->toBe('-10.99')
        ->and(Money::fromMinor(5))->toBe('0.05')
        ->and(Money::formatted(123456))->toBe('1,234.56 USD')
        ->and(Money::rounded(123456))->toBe('1,235');
});

it('parses and formats zero-decimal currencies', function () {
    useCurrencyConfig(['code' => 'JPY', 'exponent' => 0, 'symbol' => '¥']);

    expect(Currency::subunits())->toBe(1)
        ->and(Money::toMinor('1234'))->toBe(1234)
        ->and(Money::toMinor('1,234'))->toBe(1234)
        ->and(Money::fromMinor(1234))->toBe('1234')
        ->and(Money::formatted(1234))->toBe('1,234 JPY')
        ->and(Money::rounded(1234))->toBe('1,234');
});

it('parses and formats three-decimal currencies', function () {
    useCurrencyConfig(['code' => 'KWD', 'exponent' => 3, 'symbol' => 'د.ك']);

    expect(Currency::subunits())->toBe(1000)
        ->and(Money::toMinor('1.234'))->toBe(1234)
        ->and(Money::toMinor('2'))->toBe(2000)
        ->and(Money::fromMinor(1234))->toBe('1.234')
        ->and(Money::formatted(1234))->toBe('1.234 KWD');
});

it('converts source currency to base currency at a frozen rate', function () {
    useCurrencyConfig(
        ['code' => 'PKR', 'exponent' => 2, 'symbol' => 'Rs'],
        ['code' => 'USD', 'exponent' => 2, 'symbol' => '$', 'default_rate' => '278'],
    );

    // $100.00 at 280 PKR/USD = 28,000 PKR = 2,800,000 minor units
    expect(Money::sourceMinorToBaseMinor(100_00, Money::fxRateToE6('280')))->toBe(28_000_00)
        ->and(Money::sourceMinorToBaseMinor(0, Money::fxRateToE6('280')))->toBe(0)
        ->and(Money::sourceMinorToBaseMinor(100_00, 0))->toBe(0);
});

it('converts between currencies with different exponents', function () {
    useCurrencyConfig(
        ['code' => 'JPY', 'exponent' => 0, 'symbol' => '¥'],
        ['code' => 'USD', 'exponent' => 2, 'symbol' => '$', 'default_rate' => '150'],
    );

    // $10.00 at 150 JPY/USD = 1,500 JPY, and JPY has no subunit
    expect(Money::sourceMinorToBaseMinor(10_00, Money::fxRateToE6('150')))->toBe(1500);
});

it('round-trips FX rates through the e6 scale', function () {
    expect(Money::fxRateToE6('278.50'))->toBe(278_500_000)
        ->and(Money::fxRateFromE6(278_500_000))->toBe('278.5')
        ->and(Money::fxRateFromE6(Money::fxRateToE6('1')))->toBe('1')
        ->and(Money::fxRateToE6(''))->toBe(0);
});

it('lets a saved setting override the configured base currency', function () {
    useCurrencyConfig(['code' => 'USD', 'exponent' => 2, 'symbol' => '$']);

    expect(Currency::code())->toBe('USD')
        ->and(Currency::symbol())->toBe('$');

    AppSettings::set('base_currency', 'gbp');
    AppSettings::set('currency_symbol', '£');

    expect(Currency::code())->toBe('GBP')
        ->and(Currency::symbol())->toBe('£')
        ->and(Money::formatted(123456))->toBe('1,234.56 GBP');
});

it('applies basis points with half-up rounding', function () {
    expect(Money::applyBps(100_00, 10000))->toBe(100_00)
        ->and(Money::applyBps(100_00, 4000))->toBe(40_00)
        ->and(Money::applyBps(3, 5000))->toBe(2)
        ->and(Money::applyBps(0, 5000))->toBe(0);
});
