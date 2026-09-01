<?php

namespace App\Enums;

enum LinkLiveStatus: string
{
    case Live = 'live';
    case Removed = 'removed';

    public function label(): string
    {
        return match ($this) {
            self::Live => 'Live',
            self::Removed => 'Removed',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
