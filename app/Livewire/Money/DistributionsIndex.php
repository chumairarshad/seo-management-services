<?php

namespace App\Livewire\Money;

use App\Enums\DistributionStatus;
use App\Models\DistributionRun;
use App\Policies\FinancePolicy;
use App\Services\CsvExportService;
use App\Services\DistributionService;
use App\Support\Currency;
use App\Support\DisplayTimezone;
use App\Support\Money;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Distributions')]
class DistributionsIndex extends Component
{
    use WithPagination;

    public string $period_month = '';

    public string $holdback_percent = '0';

    public string $notes = '';

    public bool $showCreate = false;

    public function mount(): void
    {
        abort_unless(FinancePolicy::viewDistributions(Auth::user()), 403);
        $this->period_month = DisplayTimezone::now()->subMonth()->format('Y-m');
    }

    public function createDraft(DistributionService $distributions): void
    {
        abort_unless(FinancePolicy::manageDistributions(Auth::user()), 403);
        $this->validate([
            'period_month' => ['required', 'date_format:Y-m'],
            'holdback_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $bps = (int) round(((float) $this->holdback_percent) * 100);
        $run = $distributions->createDraft(
            $this->period_month,
            Auth::user(),
            $bps,
            $this->notes ?: null,
        );

        $this->showCreate = false;
        session()->flash('status', 'Draft distribution #'.$run->id.' created.');
        $this->redirect(route('money.distributions.show', $run), navigate: true);
    }

    public function exportCsv(CsvExportService $csv)
    {
        abort_unless(FinancePolicy::export(Auth::user()), 403);
        $rows = DistributionRun::query()->orderByDesc('period_month')->get()
            ->map(fn (DistributionRun $r) => [
                $r->id,
                $r->period_month->format('Y-m'),
                $r->status->value,
                Money::fromMinor((int) $r->total_net_profit_paisa),
                Money::fromMinor((int) $r->total_credited_paisa),
                Money::fromMinor((int) $r->total_holdback_paisa),
            ]);

        $code = strtolower(Currency::code());

        return $csv->download('distributions.csv', [
            'id', 'period', 'status', 'net_profit_'.$code, 'credited_'.$code, 'holdback_'.$code,
        ], $rows);
    }

    public function render()
    {
        return view('livewire.money.distributions-index', [
            'runs' => DistributionRun::query()->orderByDesc('period_month')->orderByDesc('id')->paginate(15),
            'canManage' => FinancePolicy::manageDistributions(Auth::user()),
            'statuses' => DistributionStatus::options(),
        ]);
    }
}
