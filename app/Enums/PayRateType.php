<?php

namespace App\Enums;

enum PayRateType: string
{
    case MonthlySalary = 'monthly_salary';
    case PerArticle = 'per_article';
    case PerLink = 'per_link';
    case PerTask = 'per_task';

    public function label(): string
    {
        return match ($this) {
            self::MonthlySalary => 'Monthly salary',
            self::PerArticle => 'Per article',
            self::PerLink => 'Per link',
            self::PerTask => 'Per task',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
