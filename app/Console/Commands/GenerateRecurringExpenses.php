<?php

namespace App\Console\Commands;

use App\Services\ExpenseService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateRecurringExpenses extends Command
{
    protected $signature = 'expenses:generate-recurring {--date= : As-of date Y-m-d}';

    protected $description = 'Materialize due monthly recurring expenses (idempotent)';

    public function handle(ExpenseService $expenses): int
    {
        $asOf = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : now();

        $count = $expenses->generateDueRecurring($asOf);
        $this->info("Generated {$count} recurring expense instance(s).");

        return self::SUCCESS;
    }
}
