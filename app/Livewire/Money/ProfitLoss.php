<?php

namespace App\Livewire\Money;

use App\Policies\FinancePolicy;
use App\Services\CsvExportService;
use App\Services\ProfitAndLossService;
use App\Support\Currency;
use App\Support\DisplayTimezone;
use App\Support\Money;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Profit & Loss')]
class ProfitLoss extends Component
{
    #[Url]
    public string $month = '';

    public function mount(): void
    {
        abort_unless(FinancePolicy::viewPnl(Auth::user()), 403);
        if ($this->month === '') {
            $this->month = DisplayTimezone::now()->format('Y-m');
        }
    }

    public function exportCsv(ProfitAndLossService $pnl, CsvExportService $csv)
    {
        abort_unless(FinancePolicy::export(Auth::user()), 403);
        $report = $pnl->forMonth(Auth::user(), $this->month);
        $rows = collect($report['projects'])->map(fn ($r) => [
            $r['domain'],
            Money::fromMinor($r['revenue_paisa']),
            Money::fromMinor($r['direct_expense_paisa']),
            Money::fromMinor($r['shared_expense_paisa']),
            Money::fromMinor($r['total_expense_paisa']),
            Money::fromMinor($r['net_profit_paisa']),
        ]);

        $code = strtolower(Currency::code());

        return $csv->download('pnl-'.$this->month.'.csv', [
            'domain', 'revenue_'.$code, 'direct_expense_'.$code, 'shared_expense_'.$code,
            'total_expense_'.$code, 'net_profit_'.$code,
        ], $rows);
    }

    public function render(ProfitAndLossService $pnl)
    {
        $report = $pnl->forMonth(Auth::user(), $this->month);

        return view('livewire.money.profit-loss', [
            'report' => $report,
        ]);
    }
}
