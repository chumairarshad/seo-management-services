<?php

namespace App\Console\Commands;

use App\Services\Ai\MonthlySummaryDraftService;
use App\Support\AiAvailability;
use Illuminate\Console\Command;

class DraftAiMonthlySummaries extends Command
{
    protected $signature = 'ai:draft-monthly-summaries
                            {--period= : Year-month (Y-m); default previous local month}
                            {--no-llm : Build drafts from reports only (no provider call)}';

    protected $description = 'Draft portfolio/employee monthly AI summary notes and anomaly flags for human review';

    public function handle(MonthlySummaryDraftService $service): int
    {
        if (! AiAvailability::enabled()) {
            $this->warn('AI is not configured (no API key). Nothing drafted.');

            return self::SUCCESS;
        }

        $period = $this->option('period') ?: null;
        $useLlm = ! $this->option('no-llm');

        $result = $service->draftForPeriod($period, $useLlm);

        $this->info('Draft notes created/updated: '.$result['created'].' (skipped: '.$result['skipped'].').');

        return self::SUCCESS;
    }
}
