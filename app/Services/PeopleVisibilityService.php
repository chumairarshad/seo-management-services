<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Who may see whose people data (login history, attendance, scorecards, work logs).
 * Admin: everyone. Supervisor: self + team on accessible projects. Staff: self only.
 */
class PeopleVisibilityService
{
    public function canViewSubject(User $viewer, User $subject): bool
    {
        if ((int) $viewer->id === (int) $subject->id) {
            return true;
        }

        if ($viewer->isAdmin()) {
            return true;
        }

        if ($viewer->hasPermission('people.view_team')) {
            return $this->viewableUserIds($viewer)->contains((int) $subject->id);
        }

        return false;
    }

    /**
     * User IDs the viewer may inspect (always includes self).
     *
     * @return Collection<int, int>
     */
    public function viewableUserIds(User $viewer): Collection
    {
        $ids = collect([(int) $viewer->id]);

        if ($viewer->isAdmin()) {
            return User::query()->pluck('id')->map(fn ($id) => (int) $id)->values();
        }

        if (! $viewer->hasPermission('people.view_team')) {
            return $ids->unique()->values();
        }

        $projectIds = $viewer->accessibleProjectIds();
        if ($projectIds === []) {
            return $ids->unique()->values();
        }

        $teamOnProjects = DB::table('project_user')
            ->whereIn('project_id', $projectIds)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id);

        // Also supervisors/staff who have role_user rows scoped to those projects
        $roleScoped = DB::table('role_user')
            ->whereIn('project_id', $projectIds)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id);

        return $ids
            ->merge($teamOnProjects)
            ->merge($roleScoped)
            ->unique()
            ->values();
    }

    /**
     * @return Collection<int, User>
     */
    public function viewableUsers(User $viewer): Collection
    {
        $ids = $this->viewableUserIds($viewer);

        return User::query()
            ->whereIn('id', $ids->all())
            ->orderBy('name')
            ->get();
    }

    public function canManageAttendance(User $actor): bool
    {
        return $actor->hasPermission('attendance.manage');
    }
}
