<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Throwable;

class DisplayTimezone
{
    /**
     * Timezone everything is displayed in. Instants are always stored in UTC.
     */
    public static function name(): string
    {
        $fallback = (string) config('app.display_timezone', 'UTC');

        try {
            return (string) AppSettings::get('display_timezone', $fallback);
        } catch (Throwable) {
            return $fallback;
        }
    }

    public static function now(): Carbon
    {
        return now(static::name());
    }

    public static function today(): string
    {
        return static::now()->toDateString();
    }

    /**
     * Local calendar day for a UTC (or any tz) instant, as Y-m-d in display timezone.
     */
    public static function localDate(CarbonInterface $instant): string
    {
        return $instant->copy()->timezone(static::name())->toDateString();
    }

    /**
     * UTC bounds for a local calendar day (inclusive start, exclusive end).
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function utcBoundsForLocalDate(string $localDate): array
    {
        $start = Carbon::parse($localDate, static::name())->startOfDay()->utc();
        $end = Carbon::parse($localDate, static::name())->addDay()->startOfDay()->utc();

        return [$start, $end];
    }

    /**
     * UTC bounds for a local calendar month (Y-m).
     *
     * @return array{0: Carbon, 1: Carbon, 2: string, 3: string} start, end, firstLocalDate, lastLocalDate
     */
    public static function utcBoundsForLocalMonth(string $yearMonth): array
    {
        $startLocal = Carbon::parse($yearMonth.'-01', static::name())->startOfMonth();
        $endLocal = $startLocal->copy()->endOfMonth();

        return [
            $startLocal->copy()->startOfDay()->utc(),
            $endLocal->copy()->addDay()->startOfDay()->utc(),
            $startLocal->toDateString(),
            $endLocal->toDateString(),
        ];
    }
}
