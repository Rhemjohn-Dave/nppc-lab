<?php

namespace App\Enums;

enum PaymentMode: string
{
    case Cash = 'cash';
    case BillingPartial = 'billing_partial';
    case Check = 'check';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::BillingPartial => 'Billing/Partial',
            self::Check => 'Check',
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
