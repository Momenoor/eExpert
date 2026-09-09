<?php

namespace App\Filament\Concerns;

use Livewire\Attributes\On;

/**
 * Keeps the payroll screens in step with each other.
 *
 * Livewire re-renders the component an action ran on, and nothing else. That is
 * fine when a table's own row action edits its own row, but every interesting
 * action in this module changes data a DIFFERENT component is displaying:
 *
 *   - building a loan schedule rewrites the instalments relation manager
 *   - generating payroll fills the payslips relation manager AND changes the
 *     totals in the run's summary infolist
 *   - a ladder step changes the status the header actions key off, so the wrong
 *     buttons stay on screen
 *   - approving leave writes rows that the leave ledger and vacation calendar read
 *
 * Without this the operator gets a success notification sitting over stale
 * figures, and the natural conclusion is that the action did not work — which is
 * how an empty payroll run came to be reported as "it's not importing employees".
 *
 * Any component using this trait re-renders when any other one dispatches
 * PayrollRefresh::EVENT.
 */
trait RefreshesPayrollData
{
    /**
     * Livewire re-renders a component after handling any event it listens for,
     * so this needs no body — being called is the whole point.
     */
    #[On(PayrollRefresh::EVENT)]
    public function refreshPayrollData(): void {}

    /**
     * Tell every other payroll screen that something changed.
     */
    public function dispatchPayrollDataUpdated(): void
    {
        $this->dispatch(PayrollRefresh::EVENT);
    }
}
