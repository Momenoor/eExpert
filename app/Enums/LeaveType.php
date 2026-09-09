<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * The leave types a request period can carry.
 *
 * Each case owns its own pay factor, so payroll multiplies days by a number and
 * never branches on the type itself. Adding a type later — compassionate leave,
 * study leave — means adding a case and its factor here, and nothing in the
 * payroll engine changes.
 */
enum LeaveType: string implements HasColor, HasLabel
{
    case ANNUAL = 'annual';
    case CASUAL = 'casual';

    /**
     * Article 31 pays sick leave in three bands per year of service:
     * 15 days full, the next 30 at half, the following 45 unpaid.
     */
    case SICK_FULL = 'sick_full';
    case SICK_HALF = 'sick_half';
    case SICK_UNPAID = 'sick_unpaid';

    case UNPAID = 'unpaid';
    case ABSENT = 'absent';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::ANNUAL => __('Annual Leave'),
            self::CASUAL => __('Casual Leave'),
            self::SICK_FULL => __('Sick Leave (Full Pay)'),
            self::SICK_HALF => __('Sick Leave (Half Pay)'),
            self::SICK_UNPAID => __('Sick Leave (Unpaid)'),
            self::UNPAID => __('Unpaid Leave'),
            self::ABSENT => __('Unauthorised Absence'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::ANNUAL => 'success',
            self::CASUAL => 'info',
            self::SICK_FULL, self::SICK_HALF => 'warning',
            self::SICK_UNPAID, self::UNPAID, self::ABSENT => 'danger',
        };
    }

    /**
     * The share of a normal day's pay this type earns.
     */
    public function payFactor(): float
    {
        return match ($this) {
            self::ANNUAL, self::CASUAL, self::SICK_FULL => 1.0,
            self::SICK_HALF => 0.5,
            self::SICK_UNPAID, self::UNPAID, self::ABSENT => 0.0,
        };
    }

    /**
     * Whether a day of this type counts toward end-of-service length of service.
     *
     * Article 51 excludes unpaid leave from the service period, so a month with
     * unpaid days accrues proportionally less gratuity.
     */
    public function accruesService(): bool
    {
        return $this->payFactor() > 0.0;
    }

    /**
     * Types that draw down the annual balance.
     *
     * @return array<int, self>
     */
    public static function deductingFromAnnualBalance(): array
    {
        return [self::ANNUAL];
    }
}
