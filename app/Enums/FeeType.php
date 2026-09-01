<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum FeeType: string implements HasColor, HasLabel
{
    case EXPERT_FEE = 'expert fee';

    case MARKETING = 'marketing';
    case VAT = 'VAT';
    case COURT_PENALITY = 'court penality';

    case OFFICE_SHARE = 'office share';
    case OTHER = 'other';

    case UNCOLLECTED = 'uncollected';

    case DISCOUNT = 'discount';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::EXPERT_FEE => __('Expert Fee'),
            self::MARKETING => __('Marketing'),
            self::VAT => __('VAT'),
            self::COURT_PENALITY => __('Court Penality'),
            self::OFFICE_SHARE => __('Office Share'),
            self::UNCOLLECTED => __('Uncollected'),
            self::DISCOUNT => __('Discount'),
            self::OTHER => __('Other'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::EXPERT_FEE => 'success',
            self::MARKETING => 'warning',
            self::VAT => 'info',
            self::COURT_PENALITY, self::DISCOUNT, self::OFFICE_SHARE, self::UNCOLLECTED => 'danger',
            self::OTHER => 'gray',
        };
    }

    public function isNegative(): bool
    {
        return match ($this) {
            self::COURT_PENALITY, self::OFFICE_SHARE, self::MARKETING, self::UNCOLLECTED, self::DISCOUNT => true,
            default => false,
        };
    }

    /**
     * Deduction-type fees (court penalty, office share, marketing, uncollected,
     * discount) reduce a matter's net revenue but must never become their own
     * incentive line — they're netted against the matter's revenue fee(s) instead.
     *
     * @return list<string>
     */
    public static function deductionTypeValues(): array
    {
        return collect(self::cases())->filter(fn (self $c) => $c->isNegative())->map(fn (self $c) => $c->value)->all();
    }

    /**
     * Fee types that should never be picked as an incentive-eligible fee:
     * VAT (a pass-through tax, not office revenue) plus every deduction type.
     *
     * @return list<string>
     */
    public static function excludedFromIncentiveValues(): array
    {
        return [...self::deductionTypeValues(), self::VAT->value];
    }
}
