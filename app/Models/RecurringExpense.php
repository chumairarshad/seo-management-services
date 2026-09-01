<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RecurringExpense extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'project_id',
        'is_shared',
        'expense_category_id',
        'amount_paisa',
        'currency',
        'description',
        'day_of_month',
        'next_run_date',
        'ends_on',
        'is_active',
        'last_generated_at',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_shared' => 'boolean',
            'amount_paisa' => 'integer',
            'day_of_month' => 'integer',
            'next_run_date' => 'date',
            'ends_on' => 'date',
            'is_active' => 'boolean',
            'last_generated_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function instances(): HasMany
    {
        return $this->hasMany(Expense::class, 'recurring_expense_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
