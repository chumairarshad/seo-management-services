<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskTemplate;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SetupChecklistService
{
    /**
     * Create setup checklist tasks for a project from active templates.
     * Idempotent per template per project.
     *
     * @return int Number of tasks created
     */
    public function generateForProject(Project $project, ?User $createdBy = null): int
    {
        $templates = TaskTemplate::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $created = 0;

        DB::transaction(function () use ($project, $templates, $createdBy, &$created) {
            foreach ($templates as $template) {
                $exists = Task::query()
                    ->where('project_id', $project->id)
                    ->where('task_template_id', $template->id)
                    ->exists();

                if ($exists) {
                    continue;
                }

                Task::query()->create([
                    'project_id' => $project->id,
                    'task_template_id' => $template->id,
                    'title' => $template->title,
                    'description' => $template->description,
                    'type' => TaskType::Setup,
                    'status' => TaskStatus::Assigned,
                    'created_by' => $createdBy?->id,
                ]);

                $created++;
            }
        });

        return $created;
    }
}
