<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsageLog extends Model
{
    protected $fillable = [
        'user_id',
        'feature',
        'provider',
        'model',
        'report_key',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'estimated_cost_cents',
        'cached',
        'success',
        'error_message',
        'safe_meta',
    ];

    protected function casts(): array
    {
        return [
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'total_tokens' => 'integer',
            'estimated_cost_cents' => 'integer',
            'cached' => 'boolean',
            'success' => 'boolean',
            'safe_meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
