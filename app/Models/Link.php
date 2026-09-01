<?php

namespace App\Models;

use App\Enums\LinkLiveStatus;
use App\Enums\LinkType;
use App\Enums\LinkWorkflowStatus;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Link extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'project_id',
        'source_url',
        'source_domain',
        'dr',
        'da',
        'target_page',
        'anchor_text',
        'type',
        'cost_paisa',
        'link_date',
        'live_status',
        'workflow_status',
        'rejection_reason',
        'assigned_to',
        'created_by',
        'approved_by',
        'submitted_at',
        'approved_at',
        'expense_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => LinkType::class,
            'live_status' => LinkLiveStatus::class,
            'workflow_status' => LinkWorkflowStatus::class,
            'dr' => 'integer',
            'da' => 'integer',
            'cost_paisa' => 'integer',
            'link_date' => 'date',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    /**
     * @param  Builder<Link>  $query
     * @return Builder<Link>
     */
    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->whereIn('project_id', $user->accessibleProjectIds() ?: [0]);
    }

    /**
     * @param  Builder<Link>  $query
     * @return Builder<Link>
     */
    public function scopeAwaitingApproval(Builder $query): Builder
    {
        return $query->where('workflow_status', LinkWorkflowStatus::Submitted->value);
    }

    public static function extractDomain(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            // bare domain or malformed
            $host = preg_replace('#^https?://#i', '', $url) ?? $url;
            $host = explode('/', $host)[0];
        }

        $host = strtolower($host);
        $host = preg_replace('/^www\./', '', $host) ?? $host;

        return $host;
    }

    public function costFormatted(): string
    {
        return Money::formatted((int) $this->cost_paisa);
    }
}
