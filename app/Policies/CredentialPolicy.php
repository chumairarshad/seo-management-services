<?php

namespace App\Policies;

use App\Models\Credential;
use App\Models\Project;
use App\Models\User;

class CredentialPolicy
{
    public function viewAny(User $user, ?Project $project = null): bool
    {
        if (! $user->hasPermission('credentials.view')) {
            return false;
        }

        if ($project) {
            return $user->canAccessProject($project);
        }

        return true;
    }

    public function view(User $user, Credential $credential): bool
    {
        return $user->hasPermission('credentials.view')
            && $user->canAccessProject($credential->project);
    }

    public function create(User $user, Project $project): bool
    {
        return $user->hasPermission('credentials.manage')
            && $user->canAccessProject($project);
    }

    public function update(User $user, Credential $credential): bool
    {
        return $user->hasPermission('credentials.manage')
            && $user->canAccessProject($credential->project);
    }

    public function delete(User $user, Credential $credential): bool
    {
        return $user->hasPermission('credentials.manage')
            && $user->canAccessProject($credential->project);
    }

    public function reveal(User $user, Credential $credential): bool
    {
        return $user->hasPermission('credentials.reveal')
            && $user->canAccessProject($credential->project);
    }
}
