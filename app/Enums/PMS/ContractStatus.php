<?php

namespace App\Enums\PMS;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ContractStatus: string implements HasColor, HasLabel
{
    case QUOTATION = 'quotation';
    case DRAFT = 'draft';
    case PENDING_ATTESTATION = 'pending_attestation';
    case ACTIVE = 'active';
    case RENEWED = 'renewed';
    case TERMINATED = 'terminated';
    case EXPIRED = 'expired';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::QUOTATION => __('Quotation'),
            self::DRAFT => __('Draft'),
            self::PENDING_ATTESTATION => __('Pending Attestation'),
            self::ACTIVE => __('Active'),
            self::RENEWED => __('Renewed'),
            self::TERMINATED => __('Terminated'),
            self::EXPIRED => __('Expired'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::QUOTATION, self::DRAFT => 'gray',
            self::PENDING_ATTESTATION => 'warning',
            self::ACTIVE, self::RENEWED => 'success',
            self::TERMINATED, self::EXPIRED => 'danger',
        };
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::QUOTATION, self::DRAFT], true);
    }
}
