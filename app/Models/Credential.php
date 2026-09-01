<?php

namespace App\Models;

use App\Enums\CredentialType;
use Database\Factories\CredentialFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Credential extends Model
{
    /** @use HasFactory<CredentialFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'project_id',
        'type',
        'label',
        'username',
        'secret',
        'url',
        'expires_on',
        'metadata',
    ];

    protected $hidden = [
        'secret',
        'username',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'type' => CredentialType::class,
            'username' => 'encrypted',
            'secret' => 'encrypted',
            'metadata' => 'encrypted:array',
            'expires_on' => 'date',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function accessLogs(): HasMany
    {
        return $this->hasMany(CredentialAccessLog::class);
    }

    public function maskedSecret(): string
    {
        if (blank($this->secret)) {
            return '—';
        }

        return '••••••••';
    }

    public function maskedUsername(): string
    {
        if (blank($this->username)) {
            return '—';
        }

        $value = (string) $this->username;
        $len = mb_strlen($value);

        if ($len <= 2) {
            return str_repeat('•', $len);
        }

        return mb_substr($value, 0, 1).str_repeat('•', max(1, $len - 2)).mb_substr($value, -1);
    }

    public function daysUntilExpiry(): ?int
    {
        if (! $this->expires_on) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays(
            $this->expires_on->copy()->startOfDay(),
            false,
        );
    }

    /**
     * @param  Builder<Credential>  $query
     * @param  array<int, int>  $thresholds
     * @return Builder<Credential>
     */
    public function scopeExpiringWithin(Builder $query, array $thresholds = [30, 14, 7]): Builder
    {
        $max = max($thresholds) ?: 30;
        $until = Carbon::today()->addDays($max);

        return $query
            ->whereNotNull('expires_on')
            ->whereDate('expires_on', '<=', $until)
            ->whereDate('expires_on', '>=', Carbon::today()->subDays(7));
    }

    public function expiryUrgency(array $thresholds = [30, 14, 7]): ?string
    {
        $days = $this->daysUntilExpiry();

        if ($days === null) {
            return null;
        }

        if ($days < 0) {
            return 'expired';
        }

        sort($thresholds);

        foreach ($thresholds as $threshold) {
            if ($days <= $threshold) {
                return (string) $threshold;
            }
        }

        return null;
    }
}
