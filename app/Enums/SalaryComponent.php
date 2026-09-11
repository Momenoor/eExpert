<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * The parts a monthly salary is built from.
 *
 * BASIC is singled out because UAE law keys off it and nothing else: the
 * gratuity in Article 51 and the unpaid-day rate are both basic ÷ 30. Housing
 * and transport inflate gross pay but must never reach those formulas.
 */
enum SalaryComponent: string implements HasLabel
{
    case BASIC = 'basic';
    case HOUSING = 'housing';
    case TRANSPORT = 'transport';
    case UTILITIES = 'utilities';
    case OTHER = 'other';

    case CAR = 'car';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::BASIC => __('Basic Salary'),
            self::HOUSING => __('Housing Allowance'),
            self::TRANSPORT => __('Transport Allowance'),
            self::UTILITIES => __('Utilities Allowance'),
            self::OTHER => __('Other Allowance'),
            self::CAR => __('Car Allowance'),
        };
    }

    /**
     * The expense account this component is debited to in the journal voucher.
     *
     * Basic and every allowance except Car post to the same Salaries Expense
     * line — the office wants one salary figure to reconcile against, not one
     * row per allowance type. Car Allowance is kept on its own row because it
     * is tracked separately in the office's own accounts.
     */
    public function glAccount(): string
    {
        return match ($this) {
            self::BASIC, self::HOUSING, self::TRANSPORT, self::UTILITIES, self::OTHER => 'Salaries Expense',
            self::CAR => 'Car Allowance Expense',
        };
    }

    public function isAllowance(): bool
    {
        return $this !== self::BASIC;
    }

    /**
     * @return array<int, self>
     */
    public static function allowances(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $component): bool => $component->isAllowance(),
        ));
    }
}
