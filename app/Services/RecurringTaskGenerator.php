<?php

namespace App\Services;

use App\Enums\RecurrenceFrequency;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RecurringTaskGenerator
{
    /**
     * Generate due instances for recurrence sources. Safe to run twice.
     *
     * @return int Number of task instances created
     */
    public function generateDue(?Carbon $asOf = null): int
    {
        $asOf = ($asOf ?? now())->startOfDay();
        $created = 0;

        $sources = Task::query()
            ->where('is_recurrence_source', true)
            ->whereNotNull('recurrence_frequency')
            ->whereNotNull('next_occurrence_date')
            ->whereDate('next_occurrence_date', '<=', $asOf->toDateString())
            ->orderBy('id')
            ->get();

        foreach ($sources as $source) {
            $created += $this->generateForSource($source, $asOf);
        }

        return $created;
    }

    public function generateForSource(Task $source, ?Carbon $asOf = null): int
    {
        if (! $source->is_recurrence_source || ! $source->recurrence_frequency || ! $source->next_occurrence_date) {
            return 0;
        }

        $asOf = ($asOf ?? now())->startOfDay();
        $created = 0;

        DB::transaction(function () use ($source, $asOf, &$created) {
            $source->refresh();
            $cursor = $source->next_occurrence_date->copy()->startOfDay();
            $safety = 0;

            while ($cursor->lte($asOf) && $safety < 366) {
                $safety++;

                $exists = Task::query()
                    ->where('recurrence_source_id', $source->id)
                    ->whereDate('due_date', $cursor->toDateString())
                    ->exists();

                if (! $exists) {
                    Task::query()->create([
                        'project_id' => $source->project_id,
                        'title' => $source->title,
                        'description' => $source->description,
                        'type' => TaskType::Recurring,
                        'status' => TaskStatus::Assigned,
                        'assigned_to' => $source->assigned_to,
                        'due_date' => $cursor->toDateString(),
                        'recurrence_frequency' => $source->recurrence_frequency,
                        'is_recurrence_source' => false,
                        'recurrence_source_id' => $source->id,
                        'created_by' => $source->created_by,
                    ]);
                    $created++;
                }

                $cursor = $this->advance($cursor, $source->recurrence_frequency);
            }

            $source->update(['next_occurrence_date' => $cursor->toDateString()]);
        });

        return $created;
    }

    public function advance(Carbon $date, RecurrenceFrequency $frequency): Carbon
    {
        return match ($frequency) {
            RecurrenceFrequency::Daily => $date->copy()->addDay(),
            RecurrenceFrequency::Weekly => $date->copy()->addWeek(),
            RecurrenceFrequency::Monthly => $date->copy()->addMonthNoOverflow(),
        };
    }
}
