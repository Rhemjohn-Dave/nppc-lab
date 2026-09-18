<?php

namespace App\Enums;

enum CatalogScope: string
{
    case Aqua = 'aqua';
    case NonAqua = 'non_aqua';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::Aqua => 'Aquaculture',
            self::NonAqua => 'Non-aquaculture',
            self::Both => 'Aqua and non-aqua',
        };
    }

    public function visibleForAqua(bool $isAqua): bool
    {
        return match ($this) {
            self::Both => true,
            self::Aqua => $isAqua,
            self::NonAqua => ! $isAqua,
        };
    }
}
