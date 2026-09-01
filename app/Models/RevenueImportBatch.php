<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RevenueImportBatch extends Model
{
    protected $fillable = [
        'filename',
        'source',
        'row_count',
        'imported_count',
        'skipped_count',
        'status',
        'notes',
        'imported_by',
    ];

    protected function casts(): array
    {
        return [
            'row_count' => 'integer',
            'imported_count' => 'integer',
            'skipped_count' => 'integer',
        ];
    }

    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function revenues(): HasMany
    {
        return $this->hasMany(Revenue::class, 'import_batch_id');
    }
}
