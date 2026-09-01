<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case Present = 'present';
    case Leave = 'leave';
    case Holiday = 'holiday';
    case Absent = 'absent';

    public function label(): string
    {
        return match ($this) {
            self::Present => 'Present',
            self::Leave => 'Leave',
            self::Holiday => 'Holiday',
            self::Absent => 'Absent',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }

    public function isOverride(): bool
    {
        return in_array($this, [self::Leave, self::Holiday], true);
    }
}
