<?php

namespace App\Policies;

use App\Models\Link;
use App\Models\User;

class LinkPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('links.view');
    }

    public function view(User $user, Link $link): bool
    {
        return $user->hasPermission('links.view') && $user->canAccessProject($link->project);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('links.create');
    }

    public function update(User $user, Link $link): bool
    {
        if (! $user->canAccessProject($link->project)) {
            return false;
        }

        if ($user->hasPermission('links.approve')) {
            return true;
        }

        if ($user->hasPermission('links.update') && (
            (int) $link->assigned_to === (int) $user->id || (int) $link->created_by === (int) $user->id
        )) {
            return true;
        }

        return false;
    }

    public function approve(User $user, Link $link): bool
    {
        return $user->hasPermission('links.approve') && $user->canAccessProject($link->project);
    }

    public function submit(User $user, Link $link): bool
    {
        if (! $user->canAccessProject($link->project)) {
            return false;
        }

        if ($user->hasPermission('links.approve')) {
            return true;
        }

        return $user->hasPermission('links.update') && (
            (int) $link->assigned_to === (int) $user->id || (int) $link->created_by === (int) $user->id
        );
    }
}
