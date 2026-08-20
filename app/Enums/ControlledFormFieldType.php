<?php

namespace App\Enums;

enum ControlledFormFieldType: string
{
    case Text = 'text';
    case Number = 'number';
    case Date = 'date';
    case Currency = 'currency';
    case Multiline = 'multiline';
    case Checkbox = 'checkbox';
    case Radio = 'radio';
    case Signature = 'signature';
    case Table = 'table';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Text',
            self::Number => 'Number',
            self::Date => 'Date',
            self::Currency => 'Currency',
            self::Multiline => 'Multiline Text',
            self::Checkbox => 'Checkbox',
            self::Radio => 'Radio / Selection',
            self::Signature => 'Signature',
            self::Table => 'Table / Repeating Rows',
        };
    }

    public function defaultWidth(): float
    {
        return match ($this) {
            self::Checkbox => 3.5,
            self::Radio => 3.5,
            self::Signature => 40.0,
            self::Table => 80.0,
            self::Multiline => 80.0,
            default => 50.0,
        };
    }

    public function defaultHeight(): float
    {
        return match ($this) {
            self::Checkbox, self::Radio => 3.5,
            self::Multiline => 16.0,
            self::Signature => 14.0,
            self::Table => 20.0,
            default => 5.0,
        };
    }
}
