<?php

namespace App\Enums;

enum AnalysisCategory: string
{
    case Microbiological = 'microbiological';
    case PhysicoChemical = 'physico_chemical';
    case TraceHeavyMetals = 'trace_heavy_metals';
    case Lime = 'lime';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Microbiological => 'Microbiological',
            self::PhysicoChemical => 'Physico-Chemical',
            self::TraceHeavyMetals => 'Trace / Heavy Metals',
            self::Lime => 'Lime',
            self::Other => 'Other',
        };
    }
}
