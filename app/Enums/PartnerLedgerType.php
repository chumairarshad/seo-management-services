<?php

namespace App\Enums;

enum PartnerLedgerType: string
{
    case CapitalIn = 'capital_in';
    case ProfitCredit = 'profit_credit';
    case Withdrawal = 'withdrawal';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::CapitalIn => 'Capital in',
            self::ProfitCredit => 'Profit credit',
            self::Withdrawal => 'Withdrawal',
            self::Adjustment => 'Adjustment',
        };
    }

    /**
     * Sign convention for display helpers (amount always stored signed).
     */
    public function typicalSign(): int
    {
        return match ($this) {
            self::CapitalIn, self::ProfitCredit => 1,
            self::Withdrawal => -1,
            self::Adjustment => 1,
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
