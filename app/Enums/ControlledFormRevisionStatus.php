<?php

namespace App\Enums;

enum ControlledFormRevisionStatus: string
{
    case Draft = 'draft';
    case ForReview = 'for_review';
    case ForApproval = 'for_approval';
    case Approved = 'approved';
    case Active = 'active';
    case Superseded = 'superseded';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'DRAFT',
            self::ForReview => 'FOR REVIEW',
            self::ForApproval => 'FOR APPROVAL',
            self::Approved => 'APPROVED',
            self::Active => 'ACTIVE',
            self::Superseded => 'SUPERSEDED',
            self::Archived => 'ARCHIVED',
        };
    }

    public function canGenerate(): bool
    {
        return $this === self::Active;
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::ForReview], true);
    }
}
