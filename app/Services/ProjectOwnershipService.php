<?php

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectOwnershipService
{
    public const TOTAL_BPS = 10000;

    /**
     * @param  array<int, array{user_id: int, share_bps: int}>  $owners
     *
     * @throws ValidationException
     */
    public function sync(Project $project, array $owners): void
    {
        $normalized = collect($owners)
            ->filter(fn (array $row) => ($row['user_id'] ?? null) && ($row['share_bps'] ?? 0) > 0)
            ->map(fn (array $row) => [
                'user_id' => (int) $row['user_id'],
                'share_bps' => (int) $row['share_bps'],
            ])
            ->values();

        if ($normalized->isEmpty()) {
            throw ValidationException::withMessages([
                'owners' => 'At least one ownership share is required and total must be 100%.',
            ]);
        }

        $total = (int) $normalized->sum('share_bps');

        if ($total !== self::TOTAL_BPS) {
            $percent = number_format($total / 100, 2);
            throw ValidationException::withMessages([
                'owners' => "Ownership must total 100% (10,000 bps). Current total: {$percent}% ({$total} bps).",
            ]);
        }

        $userIds = $normalized->pluck('user_id')->all();

        if (count($userIds) !== count(array_unique($userIds))) {
            throw ValidationException::withMessages([
                'owners' => 'Each owner may appear only once per project.',
            ]);
        }

        $existing = User::query()->whereIn('id', $userIds)->pluck('id')->all();
        if (count($existing) !== count($userIds)) {
            throw ValidationException::withMessages([
                'owners' => 'One or more selected owners are invalid.',
            ]);
        }

        DB::transaction(function () use ($project, $normalized) {
            $project->owners()->detach();

            $sync = $normalized
                ->mapWithKeys(fn (array $row) => [
                    $row['user_id'] => ['share_bps' => $row['share_bps']],
                ])
                ->all();

            $project->owners()->attach($sync);
        });
    }
}
