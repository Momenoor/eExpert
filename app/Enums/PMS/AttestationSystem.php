<?php

namespace App\Enums\PMS;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AttestationSystem: string implements HasColor, HasLabel
{
    case EJARI_DUBAI = 'ejari_dubai';
    case TAWTHEEQ_ABU_DHABI = 'tawtheeq_abu_dhabi';
    case SHARJAWAI_SHARJAH = 'sharjawai_sharjah';
    case TASDEEQ_AJMAN = 'tasdeeq_ajman';
    case MUNICIPALITY_OTHER = 'municipality_other';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::EJARI_DUBAI => __('Ejari (Dubai)'),
            self::TAWTHEEQ_ABU_DHABI => __('Tawtheeq (Abu Dhabi)'),
            self::SHARJAWAI_SHARJAH => __('Sharjawai (Sharjah)'),
            self::TASDEEQ_AJMAN => __('Tasdeeq (Ajman)'),
            self::MUNICIPALITY_OTHER => __('Other Municipality'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::EJARI_DUBAI => 'info',
            self::TAWTHEEQ_ABU_DHABI => 'success',
            self::SHARJAWAI_SHARJAH => 'warning',
            self::TASDEEQ_AJMAN => 'danger',
            self::MUNICIPALITY_OTHER => 'gray',
        };
    }
}
