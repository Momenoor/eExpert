<?php

namespace App\Enums\PMS;

use Filament\Support\Contracts\HasLabel;

/**
 * What a `LeasePrintTemplate` is a layout for. `LEASE_CONTRACT` templates
 * are scoped by `contract_format` (one per government form); the other two
 * are scoped by `owner_group_id` instead — each estate's own letterhead.
 */
enum PrintDocumentType: string implements HasLabel
{
    case LEASE_CONTRACT = 'lease_contract';
    case TAX_INVOICE = 'tax_invoice';
    case RECEIVABLE_RECEIPT = 'receivable_receipt';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::LEASE_CONTRACT => __('Lease Contract'),
            self::TAX_INVOICE => __('Tax Invoice'),
            self::RECEIVABLE_RECEIPT => __('Receivable Receipt'),
        };
    }

    public function isOwnerGroupScoped(): bool
    {
        return $this !== self::LEASE_CONTRACT;
    }
}
