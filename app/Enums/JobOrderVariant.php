<?php

namespace App\Enums;

enum JobOrderVariant: string
{
    case General = 'general';
    case Aqua = 'aqua';

    public function label(): string
    {
        return match ($this) {
            self::General => 'General (Non-Aqua)',
            self::Aqua => 'Aqua',
        };
    }
}
