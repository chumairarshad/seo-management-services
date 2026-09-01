<?php

namespace App\Models;

use App\Enums\TaskTemplateCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskTemplate extends Model
{
    protected $fillable = [
        'key',
        'title',
        'description',
        'category',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'category' => TaskTemplateCategory::class,
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
