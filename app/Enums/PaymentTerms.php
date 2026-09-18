<?php

namespace App\Enums;

enum PaymentTerms: string
{
    case Days15 = '15_days';
    case Days30 = '30_days';

    public function label(): string
    {
        return match ($this) {
            self::Days15 => '15 days',
            self::Days30 => '30 days',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
