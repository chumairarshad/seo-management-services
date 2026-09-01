<?php

namespace App\Support;

use Throwable;

/**
 * Resolves the configured currencies.
 *
 * Currency code and symbol come from Settings when available and fall back to
 * config/money.php. The minor-unit exponent is config-only: changing it after
 * amounts exist would reinterpret every stored integer.
 */
class Currency
{
    public static function code(): string
    {
        return strtoupper((string) (static::setting('base_currency') ?: config('money.base.code', 'USD')));
    }

    public static function symbol(): string
    {
        $symbol = static::setting('currency_symbol');

        return (string) ($symbol !== null && $symbol !== '' ? $symbol : config('money.base.symbol', ''));
    }

    /**
     * Number of minor-unit digits in the base currency (2 = cents).
     */
    public static function exponent(): int
    {
        return max(0, (int) config('money.base.exponent', 2));
    }

    /**
     * Minor units per major unit in the base currency (100 for cents).
     */
    public static function subunits(): int
    {
        return 10 ** static::exponent();
    }

    public static function sourceCode(): string
    {
        return strtoupper((string) config('money.source.code', 'EUR'));
    }

    public static function sourceSymbol(): string
    {
        return (string) config('money.source.symbol', '');
    }

    public static function sourceExponent(): int
    {
        return max(0, (int) config('money.source.exponent', 2));
    }

    public static function sourceSubunits(): int
    {
        return 10 ** static::sourceExponent();
    }

    /**
     * True when revenue can be entered in a second currency.
     */
    public static function hasSourceCurrency(): bool
    {
        return static::sourceCode() !== static::code();
    }

    /**
     * Settings key holding the default source→base rate, e.g. "EUR_to_USD".
     */
    public static function fxKey(): string
    {
        return static::sourceCode().'_to_'.static::code();
    }

    /**
     * Human label for the FX rate, e.g. "USD per 1 EUR".
     */
    public static function fxLabel(): string
    {
        return static::code().' per 1 '.static::sourceCode();
    }

    public static function defaultFxRate(): string
    {
        $rate = (string) config('money.source.default_rate', '1');

        return $rate === '' ? '1' : $rate;
    }

    /**
     * Settings are read lazily and tolerate a missing/unmigrated database so
     * model accessors and console commands never depend on it.
     */
    protected static function setting(string $key): mixed
    {
        try {
            return AppSettings::get($key);
        } catch (Throwable) {
            return null;
        }
    }
}
