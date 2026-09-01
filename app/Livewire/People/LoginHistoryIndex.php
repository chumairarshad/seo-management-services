<?php

namespace App\Livewire\People;

use App\Models\LoginHistory;
use App\Services\PeopleVisibilityService;
use App\Support\DisplayTimezone;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Login history')]
class LoginHistoryIndex extends Component
{
    use WithPagination;

    #[Url]
    public ?int $userId = null;

    public function mount(): void
    {
        abort_unless(Auth::user()?->hasPermission('login_history.view'), 403);
    }

    public function updatingUserId(): void
    {
        $this->resetPage();
    }

    public function render(PeopleVisibilityService $visibility)
    {
        $viewer = Auth::user();
        $viewable = $visibility->viewableUsers($viewer);

        $subjectId = $this->userId;
        if ($subjectId && ! $viewable->pluck('id')->contains($subjectId)) {
            abort(403);
        }

        $histories = LoginHistory::query()
            ->with('user')
            ->when(
                $subjectId,
                fn ($q) => $q->where('user_id', $subjectId),
                fn ($q) => $q->whereIn('user_id', $viewable->pluck('id')->all() ?: [0]),
            )
            ->orderByDesc('logged_in_at')
            ->paginate(25);

        return view('livewire.people.login-history', [
            'histories' => $histories,
            'viewableUsers' => $viewable,
            'canFilterUsers' => $viewable->count() > 1,
            'tz' => DisplayTimezone::name(),
        ]);
    }
}
