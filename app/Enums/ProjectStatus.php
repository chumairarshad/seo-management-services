<?php

namespace App\Enums;

enum ProjectStatus: string
{
    case Setup = 'setup';
    case Live = 'live';
    case Monetized = 'monetized';
    case Paused = 'paused';
    case Sold = 'sold';

    public function label(): string
    {
        return match ($this) {
            self::Setup => 'Setup',
            self::Live => 'Live',
            self::Monetized => 'Monetized',
            self::Paused => 'Paused',
            self::Sold => 'Sold',
        };
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
