<?php

namespace App\Console\Commands;

use App\Services\RecurringTaskGenerator;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateRecurringTasks extends Command
{
    protected $signature = 'tasks:generate-recurring {--date= : Generate as of Y-m-d (UTC)}';

    protected $description = 'Generate due recurring task instances (idempotent; safe for shared hosting cron)';

    public function handle(RecurringTaskGenerator $generator): int
    {
        $asOf = $this->option('date')
            ? Carbon::parse($this->option('date'), 'UTC')->startOfDay()
            : null;

        $count = $generator->generateDue($asOf);

        $this->info("Created {$count} recurring task instance(s).");

        return self::SUCCESS;
    }
}
