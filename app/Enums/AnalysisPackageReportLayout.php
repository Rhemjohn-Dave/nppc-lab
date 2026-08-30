<?php

namespace App\Enums;

enum AnalysisPackageReportLayout: string
{
    case ControlledForm = 'controlled_form';
    case DynamicMatrix = 'dynamic_matrix';

    public function label(): string
    {
        return match ($this) {
            self::ControlledForm => 'Fixed slots (water / FO4–FO5)',
            self::DynamicMatrix => 'Dynamic test matrix (food / special)',
        };
    }
}
