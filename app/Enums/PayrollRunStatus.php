<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * The approval ladder a monthly run climbs.
 *
 * Figures are only editable in DRAFT. Everything from HR_REVIEW onward is a
 * record of who signed off on what, so recalculation must send the run back to
 * DRAFT rather than quietly changing numbers someone already approved.
 */
enum PayrollRunStatus: string implements HasColor, HasLabel
{
    case DRAFT = 'draft';
    case HR_REVIEW = 'hr_review';
    case FINANCE_APPROVAL = 'finance_approval';
    case APPROVED = 'approved';
    case DISBURSED = 'disbursed';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::DRAFT => __('Draft'),
            self::HR_REVIEW => __('HR Review'),
            self::FINANCE_APPROVAL => __('Finance Approval'),
            self::APPROVED => __('Approved'),
            self::DISBURSED => __('Disbursed'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::HR_REVIEW => 'warning',
            self::FINANCE_APPROVAL => 'info',
            self::APPROVED, self::DISBURSED => 'success',
        };
    }

    public function isEditable(): bool
    {
        return $this === self::DRAFT;
    }

    /**
     * The next rung up, or null at the top.
     */
    public function next(): ?self
    {
        return match ($this) {
            self::DRAFT => self::HR_REVIEW,
            self::HR_REVIEW => self::FINANCE_APPROVAL,
            self::FINANCE_APPROVAL => self::APPROVED,
            self::APPROVED => self::DISBURSED,
            self::DISBURSED => null,
        };
    }
}
