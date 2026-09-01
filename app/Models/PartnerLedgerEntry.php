<?php

namespace App\Models;

use App\Enums\PartnerLedgerType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PartnerLedgerEntry extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'type',
        'amount_paisa',
        'balance_after_paisa',
        'entry_date',
        'description',
        'project_id',
        'distribution_run_id',
        'distribution_line_id',
        'created_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => PartnerLedgerType::class,
            'amount_paisa' => 'integer',
            'balance_after_paisa' => 'integer',
            'entry_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function distributionRun(): BelongsTo
    {
        return $this->belongsTo(DistributionRun::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @param  Builder<PartnerLedgerEntry>  $query
     * @return Builder<PartnerLedgerEntry>
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }
}
