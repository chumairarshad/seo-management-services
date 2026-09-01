<?php

namespace App\Enums;

enum TaskStatus: string
{
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Assigned => 'Assigned',
            self::InProgress => 'In progress',
            self::Submitted => 'Submitted',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Approved], true);
    }

    public function isTerminal(): bool
    {
        return $this === self::Approved;
    }
}
