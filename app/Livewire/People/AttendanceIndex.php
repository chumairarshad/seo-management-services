<?php

namespace App\Livewire\People;

use App\Enums\AttendanceStatus;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\PeopleVisibilityService;
use App\Support\DisplayTimezone;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Attendance')]
class AttendanceIndex extends Component
{
    #[Url]
    public ?int $userId = null;

    #[Url]
    public string $month = '';

    public string $markStatus = 'leave';

    public string $markDate = '';

    public string $markNotes = '';

    public function mount(): void
    {
        abort_unless(Auth::user()?->hasPermission('attendance.view'), 403);

        if ($this->month === '') {
            $this->month = DisplayTimezone::now()->format('Y-m');
        }

        if ($this->userId === null) {
            $this->userId = Auth::id();
        }

        $this->markDate = DisplayTimezone::today();
    }

    public function markLeave(AttendanceService $attendance, PeopleVisibilityService $visibility): void
    {
        $actor = Auth::user();
        abort_unless($actor?->hasPermission('attendance.manage'), 403);

        $validated = $this->validate([
            'userId' => ['required', 'integer', 'exists:users,id'],
            'markDate' => ['required', 'date'],
            'markStatus' => ['required', 'in:leave,holiday'],
            'markNotes' => ['nullable', 'string', 'max:1000'],
        ]);

        $subject = User::query()->findOrFail($validated['userId']);
        abort_unless($visibility->canViewSubject($actor, $subject), 403);

        $attendance->markOverride(
            $subject,
            $validated['markDate'],
            AttendanceStatus::from($validated['markStatus']),
            $actor,
            $validated['markNotes'] ?: null,
        );

        $this->dispatch('toast', message: 'Attendance marked for '.$validated['markDate'].'.', tone: 'success');
    }

    public function clearMark(string $date, AttendanceService $attendance, PeopleVisibilityService $visibility): void
    {
        $actor = Auth::user();
        abort_unless($actor?->hasPermission('attendance.manage'), 403);

        $subject = User::query()->findOrFail($this->userId);
        abort_unless($visibility->canViewSubject($actor, $subject), 403);

        $attendance->clearOverride($subject, $date, $actor);
        $this->dispatch('toast', message: 'Override cleared for '.$date.'.', tone: 'success');
    }

    public function render(AttendanceService $attendance, PeopleVisibilityService $visibility)
    {
        $viewer = Auth::user();
        $viewable = $visibility->viewableUsers($viewer);

        $subject = User::query()->find($this->userId);
        if (! $subject || ! $visibility->canViewSubject($viewer, $subject)) {
            $subject = $viewer;
            $this->userId = $viewer->id;
        }

        if (! preg_match('/^\d{4}-\d{2}$/', $this->month)) {
            $this->month = DisplayTimezone::now()->format('Y-m');
        }

        $sheet = $attendance->monthlySheet($subject, $this->month);

        return view('livewire.people.attendance', [
            'subject' => $subject,
            'sheet' => $sheet,
            'viewableUsers' => $viewable,
            'canManage' => $viewer->hasPermission('attendance.manage'),
            'canFilterUsers' => $viewable->count() > 1,
            'tz' => DisplayTimezone::name(),
            'lateHour' => $attendance->lateThresholdHour(),
        ]);
    }
}
