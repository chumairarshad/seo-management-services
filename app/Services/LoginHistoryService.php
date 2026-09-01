<?php

namespace App\Services;

use App\Models\LoginHistory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class LoginHistoryService
{
    public function __construct(
        protected AttendanceService $attendance,
    ) {}

    public function record(User $user, ?Request $request = null, ?\DateTimeInterface $loggedInAt = null): LoginHistory
    {
        $request ??= request();
        $at = $loggedInAt
            ? Carbon::parse($loggedInAt)->utc()
            : now()->utc();

        $userAgent = $request?->userAgent();

        $history = LoginHistory::query()->create([
            'user_id' => $user->id,
            'logged_in_at' => $at,
            'ip_address' => $request?->ip(),
            'user_agent' => $userAgent,
            'device' => $this->summarizeDevice($userAgent),
        ]);

        $this->attendance->syncFromLogin($user, $at);

        return $history;
    }

    public function summarizeDevice(?string $userAgent): ?string
    {
        if ($userAgent === null || $userAgent === '') {
            return null;
        }

        $ua = $userAgent;
        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'Chrome/') && ! str_contains($ua, 'Edg/') => 'Chrome',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Safari/') && ! str_contains($ua, 'Chrome/') => 'Safari',
            default => 'Browser',
        };

        $os = match (true) {
            str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') => 'iOS',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'Mac OS X') => 'macOS',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Linux') => 'Linux',
            default => 'Unknown OS',
        };

        return "{$browser} on {$os}";
    }
}
