<?php

namespace App\Enums;

enum GeneratedDocumentStatus: string
{
    case Preview = 'preview';
    case Final = 'final';

    public function label(): string
    {
        return match ($this) {
            self::Preview => 'PREVIEW',
            self::Final => 'FINAL',
        };
    }
}
