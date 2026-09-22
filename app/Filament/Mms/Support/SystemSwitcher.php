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

        $mmsPanel = Filament::getPanel('mms', isStrict: false);
        $pmsPanel = Filament::getPanel('pms', isStrict: false);

        // The installer's Modules step can leave either panel unregistered
        // (see `bootstrap/providers.php`) — nothing to switch to when one
        // of them doesn't exist, so the switcher just doesn't render
        // rather than fatal on `getPath()` against a null panel.
        if (! $mmsPanel || ! $pmsPanel) {
            return view('blank');
        }

        return view('filament.system-switcher', [
            'currentSystem' => Filament::getCurrentPanel()?->getId() === 'pms' ? 'pms' : 'mms',
            'mmsUrl' => url($mmsPanel->getPath()),
            'pmsUrl' => url($pmsPanel->getPath()),
        ]);
    }
}
