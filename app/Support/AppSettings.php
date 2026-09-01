<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class AppSettings
{
    public const CACHE_KEY = 'app.settings';

    /**
     * Settings resolved for the current request.
     *
     * The cache store is the database on shared hosting, so every `get()` was a
     * query. Layouts and dashboards read settings a dozen times per render.
     *
     * @var array<string, mixed>|null
     */
    protected static ?array $memo = null;

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = static::all();

        return data_get($all, $key, $default);
    }

    public static function set(string $key, mixed $value): void
    {
        Setting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );

        static::flush();
    }

    /**
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        return static::$memo ??= Cache::remember(static::CACHE_KEY, 3600, function () {
            return Setting::query()
                ->pluck('value', 'key')
                ->all();
        });
    }

    public static function flush(): void
    {
        static::$memo = null;
        Cache::forget(static::CACHE_KEY);
    }

    /**
     * Drop the per-request memo without touching the shared cache.
     *
     * Called on boot so the static does not outlive its request in any context
     * that reuses the PHP process, such as the test suite or a queue worker.
     */
    public static function forgetMemo(): void
    {
        static::$memo = null;
    }
}
