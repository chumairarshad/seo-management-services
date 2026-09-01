<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiDraftNote extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_REVIEWED = 'reviewed';

    public const STATUS_DISMISSED = 'dismissed';

    public const TYPE_PORTFOLIO_MONTHLY = 'portfolio_monthly';

    public const TYPE_EMPLOYEE_MONTHLY = 'employee_monthly';

    public const TYPE_ANOMALY_REVENUE = 'anomaly_revenue';

    public const TYPE_ANOMALY_EXPENSE = 'anomaly_expense';

    protected $fillable = [
        'type',
        'period',
        'user_id',
        'project_id',
        'title',
        'body',
        'source_figures',
        'status',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'source_figures' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function subjectUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
