<?php

namespace App\Enums;

enum JobOrderAnalysisStatus: string
{
    case Pending = 'pending';
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Returned = 'returned';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Assigned => 'Assigned',
            self::InProgress => 'In progress',
            self::Completed => 'Completed',
            self::Returned => 'Returned',
        };
    }
}
