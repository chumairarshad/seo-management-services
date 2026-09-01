<?php

namespace App\Livewire\Money;

use App\Models\PartnerLedgerEntry;
use App\Models\User;
use App\Policies\FinancePolicy;
use App\Services\CsvExportService;
use App\Services\PartnerLedgerService;
use App\Support\Currency;
use App\Support\Money;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Partner statement')]
class PartnerStatement extends Component
{
    use WithPagination;

    /**
     * Whose statement is on screen. Resolved once in `mount()` against the
     * caller's permissions; locked so the client cannot swap in another
     * partner's id afterwards and read their ledger.
     */
    #[Locked]
    public ?int $userId = null;

    public function mount(?int $user = null): void
    {
        $actor = Auth::user();
        abort_unless(FinancePolicy::viewPartners($actor), 403);

        if ($user && FinancePolicy::managePartners($actor)) {
            $this->userId = $user;
        } elseif ($user && $user === $actor->id) {
            $this->userId = $user;
        } elseif ($actor->hasPermission('partners.statement') && ! FinancePolicy::managePartners($actor)) {
            $this->userId = $actor->id;
        } else {
            $this->userId = $user ?? $actor->id;
        }

        // Partners may only see own statement unless manage
        if (! FinancePolicy::managePartners($actor) && $this->userId !== $actor->id) {
            abort(403);
        }
    }

    public function exportCsv(CsvExportService $csv)
    {
        abort_unless(FinancePolicy::export(Auth::user()), 403);
        $rows = PartnerLedgerEntry::query()
            ->where('user_id', $this->userId)
            ->orderBy('id')
            ->get()
            ->map(fn (PartnerLedgerEntry $e) => [
                $e->id,
                $e->type->value,
                Money::fromMinor((int) $e->amount_paisa),
                Money::fromMinor((int) $e->balance_after_paisa),
                $e->entry_date->format('Y-m-d'),
                $e->description,
            ]);

        $code = strtolower(Currency::code());

        return $csv->download('partner-statement-'.$this->userId.'.csv', [
            'id', 'type', 'amount_'.$code, 'balance_after_'.$code, 'date', 'description',
        ], $rows);
    }

    public function render(PartnerLedgerService $ledger)
    {
        $partner = User::query()->findOrFail($this->userId);

        return view('livewire.money.partner-statement', [
            'partner' => $partner,
            'balance' => $ledger->balanceFor($partner->id),
            'entries' => PartnerLedgerEntry::query()
                ->where('user_id', $partner->id)
                ->orderByDesc('id')
                ->paginate(25),
        ]);
    }
}
