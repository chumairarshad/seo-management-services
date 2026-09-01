<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseAllocation extends Model
{
    protected $fillable = [
        'expense_id',
        'project_id',
        'period_month',
        'amount_paisa',
        'revenue_share_bps',
        'project_revenue_paisa',
        'portfolio_revenue_paisa',
    ];

    protected function casts(): array
    {
        return [
            'period_month' => 'date',
            'amount_paisa' => 'integer',
            'revenue_share_bps' => 'integer',
            'project_revenue_paisa' => 'integer',
            'portfolio_revenue_paisa' => 'integer',
        ];
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
