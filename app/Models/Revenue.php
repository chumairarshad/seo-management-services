<?php

namespace App\Models;

use App\Enums\RevenueSource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Revenue extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'project_id',
        'period_month',
        'source',
        'amount_usd_cents',
        'fx_rate_e6',
        'amount_pkr_paisa',
        'currency_input',
        'notes',
        'import_batch_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'period_month' => 'date',
            'source' => RevenueSource::class,
            'amount_usd_cents' => 'integer',
            'fx_rate_e6' => 'integer',
            'amount_pkr_paisa' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(RevenueImportBatch::class, 'import_batch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @param  Builder<Revenue>  $query
     * @return Builder<Revenue>
     */
    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        if ($user->hasPortfolioFinanceAccess()) {
            return $query;
        }

        return $query->whereIn('project_id', $user->accessibleProjectIds() ?: [0]);
    }
}
