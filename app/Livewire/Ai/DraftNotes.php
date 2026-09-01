<?php

namespace App\Livewire\Ai;

use App\Models\AiDraftNote;
use App\Support\AiAvailability;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('AI draft notes')]
class DraftNotes extends Component
{
    public function mount(): void
    {
        abort_unless(AiAvailability::enabled(), 404);
        abort_unless(Auth::user()?->isAdmin() || Auth::user()?->hasPermission('settings.view'), 403);
    }

    public function markReviewed(int $id): void
    {
        abort_unless(Auth::user()?->isAdmin() || Auth::user()?->hasPermission('settings.update'), 403);

        $note = AiDraftNote::query()->findOrFail($id);
        $note->update([
            'status' => AiDraftNote::STATUS_REVIEWED,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);
    }

    public function dismiss(int $id): void
    {
        abort_unless(Auth::user()?->isAdmin() || Auth::user()?->hasPermission('settings.update'), 403);

        $note = AiDraftNote::query()->findOrFail($id);
        $note->update([
            'status' => AiDraftNote::STATUS_DISMISSED,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);
    }

    public function render()
    {
        $notes = AiDraftNote::query()
            ->with(['subjectUser', 'project'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return view('livewire.ai.draft-notes', [
            'notes' => $notes,
        ]);
    }
}
