<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceDay extends Model
{
    protected $fillable = [
        'user_id',
        'local_date',
        'status',
        'first_login_at',
        'is_late',
        'source',
        'notes',
        'marked_by',
    ];

    protected function casts(): array
    {
        return [
            'local_date' => 'date',
            'status' => AttendanceStatus::class,
            'first_login_at' => 'datetime',
            'is_late' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function marker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }
}
