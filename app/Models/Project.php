<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use App\Services\ProfitAndLossService;
use App\Support\Money;
use Carbon\Carbon;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;

class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'domain',
        'niche',
        'cms',
        'start_date',
        'acquisition_cost_paisa',
        'status',
        'notes',
        'monthly_link_target',
        'monthly_link_budget_paisa',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'acquisition_cost_paisa' => 'integer',
            'status' => ProjectStatus::class,
            'monthly_link_target' => 'integer',
            'monthly_link_budget_paisa' => 'integer',
        ];
    }

    public function owners(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_owners')
            ->withPivot('share_bps')
            ->withTimestamps();
    }

    public function teamMembers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_user')
            ->withPivot('assignment_note')
            ->withTimestamps();
    }

    public function credentials(): HasMany
    {
        return $this->hasMany(Credential::class);
    }

    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    public function links(): HasMany
    {
        return $this->hasMany(Link::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function revenues(): HasMany
    {
        return $this->hasMany(Revenue::class);
    }

    /**
     * Month's revenue in paisa (base reporting currency).
     */
    public function monthRevenuePaisa(?string $yearMonth = null): int
    {
        if (! Schema::hasTable('revenues')) {
            return 0;
        }

        $month = $yearMonth
            ? Carbon::parse(strlen($yearMonth) === 7 ? $yearMonth.'-01' : $yearMonth)->startOfMonth()->toDateString()
            : now('UTC')->startOfMonth()->toDateString();

        return (int) $this->revenues()
            ->whereDate('period_month', $month)
            ->sum('amount_pkr_paisa');
    }

    /**
     * Month's cost: direct expenses + share of shared costs.
     */
    public function monthCostPaisa(?string $yearMonth = null): int
    {
        $row = app(ProfitAndLossService::class)
            ->projectRowForMonth($this, $yearMonth ?? now('UTC')->format('Y-m'));

        return (int) $row['total_expense_paisa'];
    }

    public function monthProfitPaisa(?string $yearMonth = null): int
    {
        return $this->monthRevenuePaisa($yearMonth) - $this->monthCostPaisa($yearMonth);
    }

    /**
     * Open task counts for several projects at once, keyed by project id.
     *
     * Lists render this per row, so doing it one COUNT at a time turns a page of
     * projects into a page of queries.
     *
     * @param  array<int, int>  $projectIds
     * @return array<int, int>
     */
    public static function openTaskCountsFor(array $projectIds): array
    {
        if ($projectIds === [] || ! Schema::hasTable('tasks')) {
            return [];
        }

        return Task::query()
            ->whereIn('project_id', $projectIds)
            ->open()
            ->where(function ($q) {
                $q->where('is_recurrence_source', false)
                    ->orWhereNull('is_recurrence_source');
            })
            ->groupBy('project_id')
            ->selectRaw('project_id, COUNT(*) as aggregate')
            ->pluck('aggregate', 'project_id')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    /**
     * Open (non-approved) tasks for this project.
     */
    public function openTasksCount(): int
    {
        if (! Schema::hasTable('tasks')) {
            return 0;
        }

        return (int) $this->tasks()
            ->open()
            ->where(function ($q) {
                $q->where('is_recurrence_source', false)
                    ->orWhereNull('is_recurrence_source');
            })
            ->count();
    }

    public function ownershipShareTotalBps(): int
    {
        return (int) $this->owners()->sum('project_owners.share_bps');
    }

    public function acquisitionCostFormatted(): string
    {
        return Money::formatted((int) $this->acquisition_cost_paisa);
    }

    /**
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        $projectIds = $user->accessibleProjectIds();

        return $query->whereIn($query->getModel()->getQualifiedKeyName(), $projectIds);
    }
}
