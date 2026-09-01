<?php

namespace App\Enums;

enum LinkType: string
{
    case GuestPost = 'guest_post';
    case NicheEdit = 'niche_edit';
    case Directory = 'directory';
    case Resource = 'resource';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::GuestPost => 'Guest post',
            self::NicheEdit => 'Niche edit',
            self::Directory => 'Directory',
            self::Resource => 'Resource page',
            self::Other => 'Other',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
