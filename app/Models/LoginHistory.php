<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginHistory extends Model
{
    protected $fillable = [
        'user_id',
        'logged_in_at',
        'ip_address',
        'user_agent',
        'device',
    ];

    protected function casts(): array
    {
        return [
            'logged_in_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
