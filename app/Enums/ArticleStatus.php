<?php

namespace App\Enums;

enum ArticleStatus: string
{
    case Brief = 'brief';
    case Assigned = 'assigned';
    case DraftSubmitted = 'draft_submitted';
    case Approved = 'approved';
    case RevisionRequested = 'revision_requested';

    public function label(): string
    {
        return match ($this) {
            self::Brief => 'Brief',
            self::Assigned => 'Assigned',
            self::DraftSubmitted => 'Draft submitted',
            self::Approved => 'Approved',
            self::RevisionRequested => 'Revision requested',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
