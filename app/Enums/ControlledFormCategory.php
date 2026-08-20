<?php

namespace App\Enums;

enum ControlledFormCategory: string
{
    case JobOrder = 'job_order';
    case AnalysisResult = 'analysis_result';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::JobOrder => 'Job Order',
            self::AnalysisResult => 'Analysis Result',
            self::Other => 'Other',
        };
    }
}
