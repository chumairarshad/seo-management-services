<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceDay;
use App\Models\LoginHistory;
use App\Models\User;
use App\Support\AppSettings;
use App\Support\DisplayTimezone;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    public function lateThresholdHour(): int
    {
        $hour = (int) AppSettings::get('late_arrival_hour', 10);

        return max(0, min(23, $hour));
    }

    /**
     * Ensure attendance_days reflects first login of the local day.
     * Does not overwrite leave/holiday supervisor overrides.
     */
    public function syncFromLogin(User $user, \DateTimeInterface $loggedInAt): AttendanceDay
    {
        $at = Carbon::parse($loggedInAt)->utc();
        $localDate = DisplayTimezone::localDate($at);
        $isLate = $this->isLateArrival($at);

        $existing = AttendanceDay::query()
            ->where('user_id', $user->id)
            ->whereDate('local_date', $localDate)
            ->first();

        if ($existing && $existing->status->isOverride()) {
            // Keep leave/holiday, but still note first login if missing
            if ($existing->first_login_at === null) {
                $existing->update([
                    'first_login_at' => $at,
                    'is_late' => $isLate,
                ]);
            }

            return $existing->fresh();
        }

        if ($existing) {
            // Keep earliest first_login_at
            $first = $existing->first_login_at && $existing->first_login_at->lt($at)
                ? $existing->first_login_at
                : $at;

            $existing->update([
                'status' => AttendanceStatus::Present,
                'first_login_at' => $first,
                'is_late' => $this->isLateArrival($first),
                'source' => 'login',
            ]);

            return $existing->fresh();
        }

        return AttendanceDay::query()->create([
            'user_id' => $user->id,
            'local_date' => $localDate,
            'status' => AttendanceStatus::Present,
            'first_login_at' => $at,
            'is_late' => $isLate,
            'source' => 'login',
        ]);
    }

    public function isLateArrival(\DateTimeInterface $loggedInAt): bool
    {
        $local = Carbon::parse($loggedInAt)->timezone(DisplayTimezone::name());
        $threshold = $this->lateThresholdHour();

        return $local->hour > $threshold
            || ($local->hour === $threshold && $local->minute > 0);
    }

    /**
     * Rebuild present rows for a user/month from login_histories without clobbering leave/holiday.
     */
    public function syncMonthFromLogins(User $user, string $yearMonth): int
    {
        [, , $firstDate, $lastDate] = DisplayTimezone::utcBoundsForLocalMonth($yearMonth);
        [$startUtc, $endUtc] = [
            Carbon::parse($firstDate, DisplayTimezone::name())->startOfDay()->utc(),
            Carbon::parse($lastDate, DisplayTimezone::name())->addDay()->startOfDay()->utc(),
        ];

        $logins = LoginHistory::query()
            ->where('user_id', $user->id)
            ->where('logged_in_at', '>=', $startUtc)
            ->where('logged_in_at', '<', $endUtc)
            ->orderBy('logged_in_at')
            ->get();

        $byDay = $logins->groupBy(fn (LoginHistory $h) => DisplayTimezone::localDate($h->logged_in_at));
        $synced = 0;

        foreach ($byDay as $localDate => $dayLogins) {
            /** @var LoginHistory $first */
            $first = $dayLogins->first();
            $this->syncFromLogin($user, $first->logged_in_at);
            $synced++;
        }

        return $synced;
    }

    /**
     * Supervisor/admin marks leave or holiday for a user on a local date.
     */
    public function markOverride(
        User $subject,
        string $localDate,
        AttendanceStatus $status,
        User $actor,
        ?string $notes = null,
    ): AttendanceDay {
        if (! in_array($status, [AttendanceStatus::Leave, AttendanceStatus::Holiday], true)) {
            throw ValidationException::withMessages([
                'status' => 'Only leave or holiday can be marked as overrides.',
            ]);
        }

        return AttendanceDay::query()->updateOrCreate(
            [
                'user_id' => $subject->id,
                'local_date' => $localDate,
            ],
            [
                'status' => $status,
                'source' => $status === AttendanceStatus::Leave ? 'leave' : 'holiday',
                'notes' => $notes,
                'marked_by' => $actor->id,
                // Preserve first_login_at / is_late if already present
            ],
        );
    }

    public function clearOverride(User $subject, string $localDate, User $actor): void
    {
        $day = AttendanceDay::query()
            ->where('user_id', $subject->id)
            ->whereDate('local_date', $localDate)
            ->first();

        if (! $day || ! $day->status->isOverride()) {
            return;
        }

        // If there was a login that day, re-sync to present; otherwise delete row
        [$startUtc, $endUtc] = DisplayTimezone::utcBoundsForLocalDate($localDate);
        $firstLogin = LoginHistory::query()
            ->where('user_id', $subject->id)
            ->where('logged_in_at', '>=', $startUtc)
            ->where('logged_in_at', '<', $endUtc)
            ->orderBy('logged_in_at')
            ->first();

        if ($firstLogin) {
            $day->delete();
            $this->syncFromLogin($subject, $firstLogin->logged_in_at);
        } else {
            $day->delete();
        }
    }

    /**
     * Monthly sheet rows for a user (present/leave/holiday + derived absent for weekdays without entry).
     *
     * @return array{
     *     year_month: string,
     *     days_present: int,
     *     days_late: int,
     *     days_leave: int,
     *     days_holiday: int,
     *     days_absent: int,
     *     rows: Collection<int, array<string, mixed>>
     * }
     */
    public function monthlySheet(User $user, string $yearMonth): array
    {
        $this->syncMonthFromLogins($user, $yearMonth);

        [, , $firstDate, $lastDate] = DisplayTimezone::utcBoundsForLocalMonth($yearMonth);
        $start = Carbon::parse($firstDate);
        $end = Carbon::parse($lastDate);

        $records = AttendanceDay::query()
            ->where('user_id', $user->id)
            ->whereDate('local_date', '>=', $firstDate)
            ->whereDate('local_date', '<=', $lastDate)
            ->get()
            ->keyBy(fn (AttendanceDay $d) => $d->local_date->toDateString());

        $rows = collect();
        $present = $late = $leave = $holiday = $absent = 0;
        $cursor = $start->copy();
        $today = DisplayTimezone::today();

        while ($cursor->lte($end)) {
            $dateStr = $cursor->toDateString();
            $isWeekend = $cursor->isWeekend();
            /** @var AttendanceDay|null $rec */
            $rec = $records->get($dateStr);

            if ($rec) {
                $status = $rec->status;
                $isLate = $rec->is_late && $status === AttendanceStatus::Present;
            } elseif ($isWeekend || $dateStr > $today) {
                $status = null; // no expectation
                $isLate = false;
            } else {
                $status = AttendanceStatus::Absent;
                $isLate = false;
                $absent++;
            }

            if ($status === AttendanceStatus::Present) {
                $present++;
                if ($isLate) {
                    $late++;
                }
            } elseif ($status === AttendanceStatus::Leave) {
                $leave++;
            } elseif ($status === AttendanceStatus::Holiday) {
                $holiday++;
            }

            $rows->push([
                'date' => $dateStr,
                'weekday' => $cursor->format('D'),
                'is_weekend' => $isWeekend,
                'status' => $status?->value,
                'status_label' => $status?->label(),
                'is_late' => $isLate,
                'first_login_at' => $rec?->first_login_at,
                'notes' => $rec?->notes,
                'source' => $rec?->source,
            ]);

            $cursor->addDay();
        }

        return [
            'year_month' => $yearMonth,
            'days_present' => $present,
            'days_late' => $late,
            'days_leave' => $leave,
            'days_holiday' => $holiday,
            'days_absent' => $absent,
            'rows' => $rows,
        ];
    }
}
