<?php

namespace App\Support;

use Filament\Actions\Action;

/**
 * A plain browser print of the current report — no per-report print route or
 * blade view to maintain. The action executes client-side via Alpine click handler
 * (window.print()) with no server round-trip.
 * What gets hidden from the printed page (sidebar, topbar, filters, action
 * buttons) is handled once, panel-wide, by the @media print rules in
 * resources/css/filament/admin/theme.css.
 */
class ReportPrintAction
{
    public static function make(): Action
    {
        return Action::make('print')
            ->label(__('Print'))
            ->icon('heroicon-o-printer')
            ->color('gray')
            ->alpineClickHandler('window.print()');
    }
}
