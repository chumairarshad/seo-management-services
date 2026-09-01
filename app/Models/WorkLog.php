<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkLog extends Model
{
    protected $fillable = [
        'user_id',
        'local_date',
        'body',
        'task_ids',
        'article_ids',
        'link_ids',
    ];

    protected function casts(): array
    {
        return [
            'local_date' => 'date',
            'task_ids' => 'array',
            'article_ids' => 'array',
            'link_ids' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
