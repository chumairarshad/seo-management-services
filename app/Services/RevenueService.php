<?php

namespace App\Services;

use App\Enums\RevenueSource;
use App\Models\Project;
use App\Models\Revenue;
use App\Models\RevenueImportBatch;
use App\Models\User;
use App\Support\AppSettings;
use App\Support\Currency;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RevenueService
{
    public function __construct(
        protected MoneyAuditService $audit,
        protected SharedExpenseAllocationService $allocations,
    ) {}

    /**
     * Default source→base FX rate from settings as e6 scale.
     */
    public function defaultFxRateE6(): int
    {
        $fx = AppSettings::get('fx_defaults', []);
        $rate = $fx[Currency::fxKey()] ?? null;

        if ($rate === null || $rate === '') {
            return Money::fxRateToE6(Currency::defaultFxRate());
        }

        return Money::fxRateToE6((string) $rate);
    }

    /**
     * @param  array{
     *   project_id: int,
     *   period_month: string,
     *   source: string,
     *   amount_usd?: string|float|int|null,
     *   amount_usd_cents?: int|null,
     *   fx_rate?: string|float|int|null,
     *   fx_rate_e6?: int|null,
     *   amount_pkr?: string|float|int|null,
     *   currency_input?: string,
     *   notes?: string|null,
     *   import_batch_id?: int|null,
     * }  $data
     */
    public function create(array $data, User $actor): Revenue
    {
        $payload = $this->normalizePayload($data);

        $revenue = Revenue::query()->create([
            ...$payload,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        $this->audit->log('revenue.created', $revenue, null, $revenue->toArray(), $actor);
        $this->allocations->rebuildForMonth($payload['period_month']);

        return $revenue;
    }

    /**
     * @param  array{
     *   project_id?: int,
     *   period_month?: string,
     *   source?: string,
     *   amount_usd?: string|float|int|null,
     *   amount_usd_cents?: int|null,
     *   fx_rate?: string|float|int|null,
     *   fx_rate_e6?: int|null,
     *   amount_pkr?: string|float|int|null,
     *   currency_input?: string,
     *   notes?: string|null,
     * }  $data
     */
    public function update(Revenue $revenue, array $data, User $actor): Revenue
    {
        $old = $revenue->toArray();
        $payload = $this->normalizePayload(array_merge([
            'project_id' => $revenue->project_id,
            'period_month' => $revenue->period_month->format('Y-m-d'),
            'source' => $revenue->source->value,
            'amount_usd_cents' => $revenue->amount_usd_cents,
            'fx_rate_e6' => $revenue->fx_rate_e6,
            'currency_input' => $revenue->currency_input,
            'notes' => $revenue->notes,
        ], $data), preserveUsdAndFx: true, existing: $revenue);

        // Critical: never re-derive amount_pkr from "today's" rate when only notes change;
        // normalizePayload freezes from provided/existing cents+fx.
        $revenue->update([
            ...$payload,
            'updated_by' => $actor->id,
        ]);

        $this->audit->log('revenue.updated', $revenue, $old, $revenue->fresh()->toArray(), $actor);
        $this->allocations->rebuildForMonth($payload['period_month']);
        if (($old['period_month'] ?? null) && $old['period_month'] !== $payload['period_month']) {
            $this->allocations->rebuildForMonth(Carbon::parse($old['period_month'])->startOfMonth()->toDateString());
        }

        return $revenue->fresh();
    }

    public function softDelete(Revenue $revenue, User $actor): void
    {
        $old = $revenue->toArray();
        $month = $revenue->period_month->format('Y-m-d');
        $revenue->delete();
        $this->audit->log('revenue.deleted', $revenue, $old, null, $actor);
        $this->allocations->rebuildForMonth($month);
    }

    /**
     * Import AdSense-style CSV: domain,month,amount_usd[,fx_rate]
     * Header row optional; month formats: YYYY-MM or YYYY-MM-DD.
     *
     * @return array{batch: RevenueImportBatch, imported: int, skipped: int}
     */
    public function importAdsenseCsv(string $contents, string $filename, User $actor, ?int $defaultFxE6 = null): array
    {
        $defaultFxE6 ??= $this->defaultFxRateE6();
        $lines = preg_split('/\r\n|\r|\n/', trim($contents)) ?: [];
        $rows = [];
        foreach ($lines as $i => $line) {
            if (trim($line) === '') {
                continue;
            }
            $cols = str_getcsv($line);
            if ($i === 0 && $this->looksLikeHeader($cols)) {
                continue;
            }
            $rows[] = $cols;
        }

        return DB::transaction(function () use ($rows, $filename, $actor, $defaultFxE6) {
            $batch = RevenueImportBatch::query()->create([
                'filename' => $filename,
                'source' => RevenueSource::Adsense->value,
                'row_count' => count($rows),
                'imported_count' => 0,
                'skipped_count' => 0,
                'status' => 'processing',
                'imported_by' => $actor->id,
            ]);

            $imported = 0;
            $skipped = 0;
            $months = [];

            foreach ($rows as $cols) {
                $domain = strtolower(trim((string) ($cols[0] ?? '')));
                $monthRaw = trim((string) ($cols[1] ?? ''));
                $amountUsd = trim((string) ($cols[2] ?? ''));
                $fxRaw = isset($cols[3]) && trim((string) $cols[3]) !== ''
                    ? trim((string) $cols[3])
                    : null;

                if ($domain === '' || $monthRaw === '' || $amountUsd === '') {
                    $skipped++;

                    continue;
                }

                $project = Project::query()->where('domain', $domain)->first();
                if (! $project) {
                    $skipped++;

                    continue;
                }

                try {
                    $period = $this->parsePeriodMonth($monthRaw);
                    $fxE6 = $fxRaw !== null ? Money::fxRateToE6($fxRaw) : $defaultFxE6;
                    $cents = Money::sourceToMinor($amountUsd);
                    $this->create([
                        'project_id' => $project->id,
                        'period_month' => $period,
                        'source' => RevenueSource::Adsense->value,
                        'amount_usd_cents' => $cents,
                        'fx_rate_e6' => $fxE6,
                        'currency_input' => Currency::sourceCode(),
                        'import_batch_id' => $batch->id,
                        'notes' => 'Imported from '.$filename,
                    ], $actor);
                    $imported++;
                    $months[$period] = true;
                } catch (\Throwable) {
                    $skipped++;
                }
            }

            $batch->update([
                'imported_count' => $imported,
                'skipped_count' => $skipped,
                'status' => 'completed',
            ]);

            $this->audit->log('revenue.import', $batch, null, [
                'imported' => $imported,
                'skipped' => $skipped,
                'filename' => $filename,
            ], $actor);

            return ['batch' => $batch->fresh(), 'imported' => $imported, 'skipped' => $skipped];
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *   project_id: int,
     *   period_month: string,
     *   source: string,
     *   amount_usd_cents: int,
     *   fx_rate_e6: int,
     *   amount_pkr_paisa: int,
     *   currency_input: string,
     *   notes: ?string,
     *   import_batch_id: ?int
     * }
     */
    protected function normalizePayload(array $data, bool $preserveUsdAndFx = false, ?Revenue $existing = null): array
    {
        $projectId = (int) ($data['project_id'] ?? 0);
        if ($projectId < 1 || ! Project::query()->whereKey($projectId)->exists()) {
            throw new InvalidArgumentException('Valid project_id is required.');
        }

        $period = $this->parsePeriodMonth((string) ($data['period_month'] ?? ''));
        $source = (string) ($data['source'] ?? RevenueSource::Adsense->value);
        if (! RevenueSource::tryFrom($source)) {
            throw new InvalidArgumentException('Invalid revenue source.');
        }

        $currency = strtoupper((string) ($data['currency_input'] ?? Currency::sourceCode()));

        if (array_key_exists('amount_usd', $data) && $data['amount_usd'] !== null && $data['amount_usd'] !== '') {
            $cents = Money::sourceToMinor($data['amount_usd']);
        } elseif (isset($data['amount_usd_cents'])) {
            $cents = (int) $data['amount_usd_cents'];
        } elseif ($existing) {
            $cents = (int) $existing->amount_usd_cents;
        } else {
            $cents = 0;
        }

        if (array_key_exists('fx_rate', $data) && $data['fx_rate'] !== null && $data['fx_rate'] !== '') {
            $fxE6 = Money::fxRateToE6($data['fx_rate']);
        } elseif (isset($data['fx_rate_e6'])) {
            $fxE6 = (int) $data['fx_rate_e6'];
        } elseif ($existing) {
            $fxE6 = (int) $existing->fx_rate_e6;
        } else {
            $fxE6 = $this->defaultFxRateE6();
        }

        // Always freeze the base amount from stored source+FX (never use a live rate on read).
        // Base-currency entry: amount_pkr provided, zero source amount.
        $baseCode = Currency::code();

        if ($currency === $baseCode && (array_key_exists('amount_pkr', $data) || ($existing && $existing->currency_input === $baseCode))) {
            if (array_key_exists('amount_pkr', $data) && $data['amount_pkr'] !== null && $data['amount_pkr'] !== '') {
                $pkr = Money::toMinor($data['amount_pkr']);
            } elseif ($existing) {
                $pkr = (int) $existing->amount_pkr_paisa;
            } else {
                $pkr = 0;
            }
            $cents = 0;
            $fxE6 = 0;
        } else {
            $pkr = Money::sourceMinorToBaseMinor($cents, $fxE6);
            // Optional explicit base amount only allowed as a first-write override when the source amount is zero.
            if (isset($data['amount_pkr']) && $data['amount_pkr'] !== null && $data['amount_pkr'] !== '' && $cents === 0) {
                $pkr = Money::toMinor($data['amount_pkr']);
            }
        }

        if ($pkr < 0) {
            throw new InvalidArgumentException('Revenue amount cannot be negative.');
        }

        return [
            'project_id' => $projectId,
            'period_month' => $period,
            'source' => $source,
            'amount_usd_cents' => max(0, $cents),
            'fx_rate_e6' => max(0, $fxE6),
            'amount_pkr_paisa' => $pkr,
            'currency_input' => $currency,
            'notes' => $data['notes'] ?? null,
            'import_batch_id' => isset($data['import_batch_id']) ? (int) $data['import_batch_id'] : null,
        ];
    }

    public function parsePeriodMonth(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            throw new InvalidArgumentException('period_month is required.');
        }

        try {
            if (preg_match('/^\d{4}-\d{2}$/', $raw)) {
                return Carbon::createFromFormat('Y-m', $raw)->startOfMonth()->toDateString();
            }

            return Carbon::parse($raw)->startOfMonth()->toDateString();
        } catch (\Throwable $e) {
            throw new InvalidArgumentException('Invalid period_month: '.$raw, 0, $e);
        }
    }

    /**
     * @param  array<int, string|null>  $cols
     */
    protected function looksLikeHeader(array $cols): bool
    {
        $joined = strtolower(implode(',', $cols));

        return str_contains($joined, 'domain') || str_contains($joined, 'site') || str_contains($joined, 'amount');
    }
}
