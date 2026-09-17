<?php

namespace App\Filament\Mms\Support;

use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;

/**
 * Shared topbar render-hook content for the MMS/PMS switcher, registered
 * identically in both MmsPanelProvider and PmsPanelProvider so it appears
 * consistently across both panels rather than duplicating this logic in
 * each provider.
 */
class SystemSwitcher
{
    public static function render(): View
    {
        $user = auth()->user();

        if (! $user?->can('Access:MultipleSystems')) {
            return view('blank');
        }

        return view('filament.system-switcher', [
            'currentSystem' => Filament::getCurrentPanel()?->getId() === 'pms' ? 'pms' : 'mms',
            'mmsUrl' => url(Filament::getPanel('mms')->getPath()),
            'pmsUrl' => url(Filament::getPanel('pms')->getPath()),
        ]);
    }
}
