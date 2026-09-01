<?php

namespace App\Enums;

enum RevenueSource: string
{
    case Adsense = 'adsense';
    case OtherNetwork = 'other_network';
    case Affiliate = 'affiliate';
    case Sponsored = 'sponsored';
    case Sale = 'sale';

    public function label(): string
    {
        return match ($this) {
            self::Adsense => 'AdSense',
            self::OtherNetwork => 'Other network',
            self::Affiliate => 'Affiliate',
            self::Sponsored => 'Sponsored',
            self::Sale => 'Sale',
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
