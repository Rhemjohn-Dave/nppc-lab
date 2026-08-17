<?php

namespace App\Enums;

enum JobOrderStatus: string
{
    case DraftSubmitted = 'draft_submitted';
    case Priced = 'priced';
    case InAnalysis = 'in_analysis';
    case PendingReview = 'pending_review';
    case ReadyForPickup = 'ready_for_pickup';

    public function label(): string
    {
        return match ($this) {
            self::DraftSubmitted => 'Draft submitted',
            self::Priced => 'Priced',
            self::InAnalysis => 'In analysis',
            self::PendingReview => 'Pending review',
            self::ReadyForPickup => 'Ready for pickup',
        };
    }
}
