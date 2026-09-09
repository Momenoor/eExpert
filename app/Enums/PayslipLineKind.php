<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Which side of the journal voucher a payslip line lands on.
 *
 * EMPLOYER_COST is neither paid to nor withheld from the employee — it is the
 * gratuity provision, which debits an expense and credits a liability without
 * ever touching net pay.
 */
enum PayslipLineKind: string implements HasLabel
{
    case EARNING = 'earning';
    case DEDUCTION = 'deduction';
    case EMPLOYER_COST = 'employer_cost';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::EARNING => __('Earning'),
            self::DEDUCTION => __('Deduction'),
            self::EMPLOYER_COST => __('Employer Cost'),
        };
    }
}
