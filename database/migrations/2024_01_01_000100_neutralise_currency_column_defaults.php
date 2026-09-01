<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The original tables shipped with a PKR column default from the app's first
 * deployment. Application code always writes the configured base currency
 * explicitly, so the default is only a fallback -- but a fresh install should
 * not inherit one organisation's currency. Neutralise it to match the
 * config/money.php default instead of editing the migrations already run.
 */
return new class extends Migration
{
    /**
     * @var array<string, string>
     */
    protected array $tables = [
        'expenses' => 'currency',
        'recurring_expenses' => 'currency',
        'pay_rates' => 'currency',
    ];

    public function up(): void
    {
        $this->setDefault('USD');
    }

    public function down(): void
    {
        $this->setDefault('PKR');
    }

    protected function setDefault(string $currency): void
    {
        foreach ($this->tables as $table => $column) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($column, $currency) {
                $blueprint->string($column, 3)->default($currency)->change();
            });
        }
    }
};
