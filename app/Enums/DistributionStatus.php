<?php

namespace App\Enums;

enum DistributionStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Approved => 'Approved',
            self::Voided => 'Voided',
        };
    }

    public function isLocked(): bool
    {
        return $this === self::Approved || $this === self::Voided;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
