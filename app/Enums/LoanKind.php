<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Advances recovered from salary.
 *
 * Both settle against the employee the same way; they differ only in which
 * asset account the journal voucher clears.
 */
enum LoanKind: string implements HasColor, HasLabel
{
    case LOAN = 'loan';
    case PETTY_CASH = 'petty_cash';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::LOAN => __('Staff Loan'),
            self::PETTY_CASH => __('Petty Cash'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::LOAN => 'warning',
            self::PETTY_CASH => 'info',
        };
    }

    public function glAccount(): string
    {
        return match ($this) {
            self::LOAN => 'Staff Loans Receivable',
            self::PETTY_CASH => 'Petty Cash Advances',
        };
    }
}
