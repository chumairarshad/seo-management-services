<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaskWorkflowService
{
    public function start(Task $task, User $actor): Task
    {
        $this->assertTransition($task, [TaskStatus::Assigned, TaskStatus::Rejected], TaskStatus::InProgress);

        $task->update([
            'status' => TaskStatus::InProgress,
            'rejection_reason' => null,
        ]);

        return $task->fresh();
    }

    public function submit(Task $task, User $actor): Task
    {
        $this->assertTransition($task, [TaskStatus::Assigned, TaskStatus::InProgress, TaskStatus::Rejected], TaskStatus::Submitted);

        $task->update([
            'status' => TaskStatus::Submitted,
            'submitted_at' => now(),
            'rejection_reason' => null,
        ]);

        return $task->fresh();
    }

    public function approve(Task $task, User $actor): Task
    {
        $this->assertTransition($task, [TaskStatus::Submitted], TaskStatus::Approved);

        $task->update([
            'status' => TaskStatus::Approved,
            'approved_by' => $actor->id,
            'approved_at' => now(),
            'rejection_reason' => null,
        ]);

        return $task->fresh();
    }

    public function reject(Task $task, User $actor, string $reason): Task
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages([
                'rejection_reason' => 'A rejection reason is required.',
            ]);
        }

        $this->assertTransition($task, [TaskStatus::Submitted], TaskStatus::Rejected);

        $task->update([
            'status' => TaskStatus::Rejected,
            'rejection_reason' => $reason,
            'approved_by' => null,
            'approved_at' => null,
        ]);

        return $task->fresh();
    }

    public function assign(Task $task, ?int $userId): Task
    {
        $task->update([
            'assigned_to' => $userId,
            'status' => $task->status === TaskStatus::Approved ? $task->status : ($task->status === TaskStatus::Submitted ? TaskStatus::Submitted : TaskStatus::Assigned),
        ]);

        // If was unassigned open task, keep status; if assigning new work keep assigned
        if (! in_array($task->status, [TaskStatus::Approved, TaskStatus::Submitted], true)) {
            $task->update(['status' => TaskStatus::Assigned]);
        }

        return $task->fresh();
    }

    /**
     * Bulk assign for list actions.
     *
     * @param  array<int, int>  $taskIds
     */
    public function bulkAssign(array $taskIds, int $userId, User $actor): int
    {
        $count = 0;

        DB::transaction(function () use ($taskIds, $userId, &$count) {
            $tasks = Task::query()->whereIn('id', $taskIds)->get();
            foreach ($tasks as $task) {
                if ($task->status === TaskStatus::Approved) {
                    continue;
                }
                $task->update([
                    'assigned_to' => $userId,
                    'status' => $task->status === TaskStatus::Submitted
                        ? TaskStatus::Submitted
                        : TaskStatus::Assigned,
                ]);
                $count++;
            }
        });

        return $count;
    }

    /**
     * @param  array<int, TaskStatus>  $from
     */
    protected function assertTransition(Task $task, array $from, TaskStatus $to): void
    {
        if (! in_array($task->status, $from, true)) {
            throw ValidationException::withMessages([
                'status' => "Cannot move task from {$task->status->label()} to {$to->label()}.",
            ]);
        }
    }
}
