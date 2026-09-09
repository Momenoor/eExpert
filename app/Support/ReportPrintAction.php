<?php

namespace App\Support;

use Filament\Actions\Action;

/**
 * A plain browser print of the current report — no per-report print route or
 * blade view to maintain. The action's own server round-trip is a no-op; the
 * actual printing is triggered client-side by the x-on:click alongside it.
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
            ->action(fn () => null)
            ->extraAttributes(['x-on:click' => 'window.print()']);
    }
}
