<?php

namespace App\Enums\PMS;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum InstallmentPaymentMethod: string implements HasColor, HasLabel
{
    case POST_DATED_CHEQUE = 'post_dated_cheque';
    case DIRECT_DEBIT_UAEDD = 'direct_debit_uaedd';
    case BANK_TRANSFER = 'bank_transfer';
    case CREDIT_CARD = 'credit_card';
    case CASH = 'cash';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::POST_DATED_CHEQUE => __('Post-Dated Cheque'),
            self::DIRECT_DEBIT_UAEDD => __('Direct Debit (UAEDD)'),
            self::BANK_TRANSFER => __('Bank Transfer'),
            self::CREDIT_CARD => __('Credit Card'),
            self::CASH => __('Cash'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::POST_DATED_CHEQUE => 'warning',
            self::DIRECT_DEBIT_UAEDD => 'info',
            self::BANK_TRANSFER => 'success',
            self::CREDIT_CARD => 'gray',
            self::CASH => 'success',
        };
    }
}
