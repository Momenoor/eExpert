<?php

namespace App\Enums\PMS;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Named `ContractDisputeStatus`, not `DisputeStatus`, to avoid confusion with
 * the unrelated matter-received-date `RequestStatus::DISPUTED` value already
 * used elsewhere in this app for a different kind of dispute entirely.
 */
enum ContractDisputeStatus: string implements HasColor, HasLabel
{
    case NONE = 'none';
    case FILED_WITH_RDSC = 'filed_with_rdsc';
    case RESOLVED = 'resolved';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::NONE => __('None'),
            self::FILED_WITH_RDSC => __('Filed with RDSC'),
            self::RESOLVED => __('Resolved'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::NONE => 'gray',
            self::FILED_WITH_RDSC => 'danger',
            self::RESOLVED => 'success',
        };
    }
}
