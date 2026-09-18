<?php

namespace App\Enums;

enum JobOrderStatus: string
{
    case DraftSubmitted = 'draft_submitted';
    /** @deprecated Prefer PendingJoApproval after costing; kept for legacy rows */
    case Priced = 'priced';
    case PendingJoApproval = 'pending_jo_approval';
    case JoApproved = 'jo_approved';
    case InAnalysis = 'in_analysis';
    case PendingReview = 'pending_review';
    case ReadyForPickup = 'ready_for_pickup';

    public function label(): string
    {
        return match ($this) {
            self::DraftSubmitted => 'Draft submitted',
            self::Priced => 'Priced',
            self::PendingJoApproval => 'Pending JO approval',
            self::JoApproved => 'JO approved',
            self::InAnalysis => 'In analysis',
            self::PendingReview => 'Pending review',
            self::ReadyForPickup => 'Ready for pickup',
        };
    }

    public function canPrintRfa(): bool
    {
        return in_array($this, [
            self::JoApproved,
            self::InAnalysis,
            self::PendingReview,
            self::ReadyForPickup,
        ], true);
    }

    public function canReceiveSamples(): bool
    {
        return $this === self::JoApproved;
    }
}
