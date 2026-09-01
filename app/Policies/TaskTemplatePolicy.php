<?php

namespace App\Policies;

use App\Models\TaskTemplate;
use App\Models\User;

class TaskTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('task_templates.manage');
    }

    public function manage(User $user): bool
    {
        return $user->hasPermission('task_templates.manage');
    }

    public function update(User $user, TaskTemplate $template): bool
    {
        return $user->hasPermission('task_templates.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('task_templates.manage');
    }

    public function delete(User $user, TaskTemplate $template): bool
    {
        return $user->hasPermission('task_templates.manage');
    }
}
