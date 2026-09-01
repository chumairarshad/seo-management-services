<?php

namespace App\Services\Ai;

use App\Models\AiUsageLog;
use App\Models\User;
use App\Support\AiAvailability;
use App\Support\DisplayTimezone;
use RuntimeException;

class AiBudgetService
{
    public function spentThisMonthCents(): int
    {
        $start = DisplayTimezone::now()->startOfMonth()->utc();
        $end = DisplayTimezone::now()->endOfMonth()->utc();

        return (int) AiUsageLog::query()
            ->where('success', true)
            ->whereBetween('created_at', [$start, $end])
            ->sum('estimated_cost_cents');
    }

    public function remainingCents(): int
    {
        return max(0, AiAvailability::monthlyBudgetCents() - $this->spentThisMonthCents());
    }

    public function canSpend(): bool
    {
        if (! AiAvailability::enabled()) {
            return false;
        }

        $budget = AiAvailability::monthlyBudgetCents();
        if ($budget <= 0) {
            return true; // 0 = unlimited for tests/local
        }

        return $this->remainingCents() > 0;
    }

    public function assertCanSpend(): void
    {
        if (! $this->canSpend()) {
            throw new RuntimeException('AI monthly budget has been reached. Try again next month or raise the spend cap in Settings.');
        }
    }

    public function estimateCostCents(int $totalTokens): int
    {
        if ($totalTokens <= 0) {
            return 0;
        }

        $per1k = (float) config('ai.cost_per_1k_tokens_cents', 0.15);

        // round up to whole cents
        return max(1, (int) ceil(($totalTokens / 1000) * $per1k));
    }

    public function log(
        ?User $user,
        string $feature,
        ?string $reportKey,
        int $promptTokens,
        int $completionTokens,
        bool $cached = false,
        bool $success = true,
        ?string $errorMessage = null,
        ?array $safeMeta = null,
    ): AiUsageLog {
        $total = $promptTokens + $completionTokens;
        $cost = $cached ? 0 : $this->estimateCostCents($total);

        return AiUsageLog::query()->create([
            'user_id' => $user?->id,
            'feature' => $feature,
            'provider' => AiAvailability::provider(),
            'model' => AiAvailability::model(),
            'report_key' => $reportKey,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens' => $total,
            'estimated_cost_cents' => $success ? $cost : 0,
            'cached' => $cached,
            'success' => $success,
            'error_message' => $errorMessage ? mb_substr($errorMessage, 0, 500) : null,
            'safe_meta' => $safeMeta,
        ]);
    }
}
