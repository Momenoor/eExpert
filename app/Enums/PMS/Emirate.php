<?php

namespace App\Enums\PMS;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Which emirate a property sits in — drives which government tenancy
 * contract format (Ejari/Tawtheeq/Sharjawi/…) and print layout applies.
 */
enum Emirate: string implements HasColor, HasLabel
{
    case DUBAI = 'dubai';
    case SHARJAH = 'sharjah';
    case ABU_DHABI = 'abu_dhabi';
    case AJMAN = 'ajman';
    case RAS_AL_KHAIMAH = 'ras_al_khaimah';
    case FUJAIRAH = 'fujairah';
    case UMM_AL_QUWAIN = 'umm_al_quwain';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::DUBAI => __('Dubai'),
            self::SHARJAH => __('Sharjah'),
            self::ABU_DHABI => __('Abu Dhabi'),
            self::AJMAN => __('Ajman'),
            self::RAS_AL_KHAIMAH => __('Ras Al Khaimah'),
            self::FUJAIRAH => __('Fujairah'),
            self::UMM_AL_QUWAIN => __('Umm Al Quwain'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::DUBAI => 'info',
            self::SHARJAH => 'success',
            self::ABU_DHABI => 'warning',
            default => 'gray',
        };
    }
}
