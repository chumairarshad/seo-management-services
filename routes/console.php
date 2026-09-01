<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('credentials:check-expiry --notify')->dailyAt('06:00');
Schedule::command('tasks:generate-recurring')->dailyAt('00:15');
Schedule::command('expenses:generate-recurring')->dailyAt('00:30');
// Milestone 7 — drafts only when AI key is set (command no-ops when disabled)
Schedule::command('ai:draft-monthly-summaries --no-llm')->monthlyOn(1, '07:00');
// Optional local/ops: php artisan db:backup → storage/app/backups/
