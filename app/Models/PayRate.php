<?php

namespace App\Models;

use App\Enums\PayRateType;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayRate extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'amount_paisa',
        'currency',
        'is_active',
        'effective_from',
        'effective_to',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => PayRateType::class,
            'amount_paisa' => 'integer',
            'is_active' => 'boolean',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function amountFormatted(): string
    {
        return Money::formatted((int) $this->amount_paisa, $this->currency);
    }
}
