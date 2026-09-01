<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AttendanceService;
use App\Support\DisplayTimezone;
use Illuminate\Console\Command;

class SyncAttendanceCommand extends Command
{
    protected $signature = 'attendance:sync
                            {--month= : Local calendar month Y-m (default: current display-tz month)}
                            {--user= : Optional user id}';

    protected $description = 'Regenerate attendance_days present rows from login_histories for a month';

    public function handle(AttendanceService $attendance): int
    {
        $month = $this->option('month') ?: DisplayTimezone::now()->format('Y-m');
        $userId = $this->option('user');

        $users = User::query()
            ->when($userId, fn ($q) => $q->where('id', $userId))
            ->where('is_active', true)
            ->get();

        $total = 0;
        foreach ($users as $user) {
            $total += $attendance->syncMonthFromLogins($user, $month);
        }

        $this->info("Synced {$total} day(s) for {$users->count()} user(s) in {$month}.");

        return self::SUCCESS;
    }
}
