<?php

namespace App\Enums;

enum TaskType: string
{
    case Setup = 'setup';
    case Recurring = 'recurring';
    case AdHoc = 'ad_hoc';

    public function label(): string
    {
        return match ($this) {
            self::Setup => 'Setup checklist',
            self::Recurring => 'Recurring',
            self::AdHoc => 'Ad hoc',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
