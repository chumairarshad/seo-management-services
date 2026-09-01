<?php

namespace App\Services;

use App\Enums\DistributionStatus;
use App\Enums\PartnerLedgerType;
use App\Models\DistributionLine;
use App\Models\DistributionRun;
use App\Models\PartnerLedgerEntry;
use App\Models\Project;
use App\Models\User;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class DistributionService
{
    public function __construct(
        protected ProfitAndLossService $pnl,
        protected PartnerLedgerService $ledger,
        protected MoneyAuditService $audit,
    ) {}

    /**
     * Create (or rebuild if still draft same month) a draft distribution for a calendar month.
     */
    public function createDraft(string $yearMonth, User $actor, int $holdbackBps = 0, ?string $notes = null): DistributionRun
    {
        $month = Carbon::parse(strlen($yearMonth) === 7 ? $yearMonth.'-01' : $yearMonth)
            ->startOfMonth()
            ->toDateString();

        if ($holdbackBps < 0 || $holdbackBps > 10000) {
            throw new InvalidArgumentException('Holdback must be between 0 and 10000 bps.');
        }

        // Block second non-voided approved for same month (allow multiple drafts only if previous voided/draft rebuilt)
        $existingApproved = DistributionRun::query()
            ->whereDate('period_month', $month)
            ->where('status', DistributionStatus::Approved->value)
            ->exists();

        if ($existingApproved) {
            throw new RuntimeException('An approved distribution already exists for this month. Void it before creating a replacement.');
        }

        return DB::transaction(function () use ($month, $actor, $holdbackBps, $notes) {
            // Soft-delete prior drafts for the month
            DistributionRun::query()
                ->whereDate('period_month', $month)
                ->where('status', DistributionStatus::Draft->value)
                ->each(function (DistributionRun $run) {
                    $run->lines()->delete();
                    $run->delete();
                });

            $report = $this->pnl->forMonth($actor, $month);
            $snapshot = $this->captureOwnershipSnapshot(
                collect($report['projects'])->pluck('project_id')->all()
            );

            $run = DistributionRun::query()->create([
                'period_month' => $month,
                'status' => DistributionStatus::Draft,
                'holdback_bps' => $holdbackBps,
                'total_revenue_paisa' => $report['totals']['revenue_paisa'],
                'total_direct_expense_paisa' => $report['totals']['direct_expense_paisa'],
                'total_shared_expense_paisa' => $report['totals']['shared_expense_paisa'],
                'total_net_profit_paisa' => $report['totals']['net_profit_paisa'],
                'total_holdback_paisa' => 0,
                'total_credited_paisa' => 0,
                'ownership_snapshot' => $snapshot,
                'notes' => $notes,
                'created_by' => $actor->id,
            ]);

            $totalHoldback = 0;
            $totalCredited = 0;

            foreach ($report['projects'] as $row) {
                $projectId = (int) $row['project_id'];
                $owners = $snapshot[(string) $projectId] ?? $snapshot[$projectId] ?? [];
                if ($owners === []) {
                    continue;
                }

                $net = (int) $row['net_profit_paisa'];
                // Only distribute positive net; negative nets still recorded with 0 credit
                $distributable = max(0, $net);

                // Rounding each share independently would not sum back to the net:
                // 101 paisa split 50/50 pays out 51 + 51 and invents a paisa, while
                // thirds lose one. Floor every share and give the last owner the
                // remainder, the same way shared expenses are allocated, so the
                // lines always add up to exactly the profit being distributed.
                $owners = array_values($owners);
                $lastOwnerIndex = count($owners) - 1;
                $allocated = 0;

                foreach ($owners as $index => $owner) {
                    $userId = (int) $owner['user_id'];
                    $bps = (int) $owner['share_bps'];

                    $gross = $index === $lastOwnerIndex
                        ? $distributable - $allocated
                        : intdiv($distributable * $bps, 10000);

                    $allocated += $gross;

                    $hold = Money::applyBps($gross, $holdbackBps);
                    $credit = $gross - $hold;

                    DistributionLine::query()->create([
                        'distribution_run_id' => $run->id,
                        'project_id' => $projectId,
                        'user_id' => $userId,
                        'revenue_paisa' => $row['revenue_paisa'],
                        'direct_expense_paisa' => $row['direct_expense_paisa'],
                        'shared_expense_paisa' => $row['shared_expense_paisa'],
                        'net_profit_paisa' => $net,
                        'share_bps' => $bps,
                        'gross_share_paisa' => $gross,
                        'holdback_paisa' => $hold,
                        'credited_paisa' => $credit,
                    ]);

                    $totalHoldback += $hold;
                    $totalCredited += $credit;
                }
            }

            $run->update([
                'total_holdback_paisa' => $totalHoldback,
                'total_credited_paisa' => $totalCredited,
            ]);

            $this->audit->log('distribution.draft_created', $run, null, $run->fresh()->toArray(), $actor);

            return $run->fresh(['lines']);
        });
    }

    public function approve(DistributionRun $run, User $actor): DistributionRun
    {
        if ($run->status !== DistributionStatus::Draft) {
            throw new RuntimeException('Only draft distribution runs can be approved.');
        }

        return DB::transaction(function () use ($run, $actor) {
            // Lock the row before re-reading the status: two concurrent approvals
            // (a double-click is enough) would otherwise both see a draft and both
            // post ledger credits, paying every partner twice.
            $locked = DistributionRun::query()
                ->whereKey($run->getKey())
                ->lockForUpdate()
                ->first();

            if (! $locked || $locked->status !== DistributionStatus::Draft) {
                throw new RuntimeException('Distribution run is no longer a draft.');
            }

            $run = $locked;

            // The ownership snapshot taken when the draft was computed is the
            // provenance of these line amounts. Re-capturing it here would replace
            // it with today's ownership and leave the record claiming the money was
            // split by shares it was not.
            $periodLabel = $run->period_month->format('Y-m');

            foreach ($run->lines as $line) {
                if ((int) $line->credited_paisa === 0) {
                    continue;
                }

                $entry = $this->ledger->credit(
                    userId: (int) $line->user_id,
                    amountPaisa: (int) $line->credited_paisa,
                    type: PartnerLedgerType::ProfitCredit,
                    description: 'Profit distribution '.$periodLabel.' — project #'.$line->project_id,
                    actor: $actor,
                    projectId: (int) $line->project_id,
                    distributionRunId: (int) $run->id,
                    distributionLineId: (int) $line->id,
                    entryDate: $run->period_month->copy()->endOfMonth()->toDateString(),
                );
            }

            $run->update([
                'status' => DistributionStatus::Approved,
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ]);

            $this->audit->log('distribution.approved', $run, null, $run->fresh()->toArray(), $actor);

            return $run->fresh(['lines']);
        });
    }

    public function void(DistributionRun $run, User $actor, string $reason): DistributionRun
    {
        if ($run->status !== DistributionStatus::Approved) {
            throw new RuntimeException('Only approved runs can be voided.');
        }

        return DB::transaction(function () use ($run, $actor, $reason) {
            // Reverse ledger credits with adjusting withdrawal/adjustment entries
            $entries = PartnerLedgerEntry::query()
                ->where('distribution_run_id', $run->id)
                ->where('type', PartnerLedgerType::ProfitCredit->value)
                ->get();

            foreach ($entries as $entry) {
                $this->ledger->credit(
                    userId: (int) $entry->user_id,
                    amountPaisa: -1 * (int) $entry->amount_paisa,
                    type: PartnerLedgerType::Adjustment,
                    description: 'Void distribution #'.$run->id.': '.$reason,
                    actor: $actor,
                    projectId: $entry->project_id,
                    distributionRunId: (int) $run->id,
                    entryDate: now()->toDateString(),
                    notes: 'Reversal of ledger entry #'.$entry->id,
                );
            }

            $run->update([
                'status' => DistributionStatus::Voided,
                'voided_by' => $actor->id,
                'voided_at' => now(),
                'void_reason' => $reason,
            ]);

            $this->audit->log('distribution.voided', $run, null, [
                'reason' => $reason,
            ], $actor);

            return $run->fresh();
        });
    }

    /**
     * Attempting to mutate an approved run must fail.
     */
    public function assertEditable(DistributionRun $run): void
    {
        if (! $run->isEditable()) {
            throw new RuntimeException('Approved or voided distribution runs cannot be edited. Void and create a new run.');
        }
    }

    /**
     * @param  list<int>  $projectIds
     * @return array<string, list<array{user_id: int, share_bps: int, name: string}>>
     */
    protected function captureOwnershipSnapshot(array $projectIds): array
    {
        $snapshot = [];
        $projects = Project::query()->with('owners')->whereIn('id', $projectIds)->get();

        foreach ($projects as $project) {
            $snapshot[(string) $project->id] = $project->owners->map(fn (User $u) => [
                'user_id' => (int) $u->id,
                'share_bps' => (int) $u->pivot->share_bps,
                'name' => $u->name,
            ])->values()->all();
        }

        return $snapshot;
    }
}
