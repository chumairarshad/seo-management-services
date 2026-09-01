<?php

namespace App\Livewire\People;

use App\Models\User;
use App\Services\PeopleVisibilityService;
use App\Services\ScorecardService;
use App\Support\DisplayTimezone;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Scorecard')]
class ScorecardShow extends Component
{
    #[Url]
    public ?int $userId = null;

    #[Url]
    public string $month = '';

    public function mount(): void
    {
        abort_unless(Auth::user()?->hasPermission('scorecards.view'), 403);

        if ($this->month === '') {
            $this->month = DisplayTimezone::now()->format('Y-m');
        }

        if ($this->userId === null) {
            $this->userId = Auth::id();
        }
    }

    public function render(ScorecardService $scorecards, PeopleVisibilityService $visibility)
    {
        $viewer = Auth::user();
        $viewable = $visibility->viewableUsers($viewer);

        $subject = User::query()->find($this->userId);
        if (! $subject || ! $visibility->canViewSubject($viewer, $subject)) {
            abort(403, 'You cannot view this scorecard.');
        }

        if (! preg_match('/^\d{4}-\d{2}$/', $this->month)) {
            $this->month = DisplayTimezone::now()->format('Y-m');
        }

        $card = $scorecards->forUserMonth($subject, $this->month);

        return view('livewire.people.scorecard', [
            'subject' => $subject,
            'card' => $card,
            'viewableUsers' => $viewable,
            'canFilterUsers' => $viewable->count() > 1,
            'tz' => DisplayTimezone::name(),
        ]);
    }
}
