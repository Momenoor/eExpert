<?php

namespace App\Http\Controllers;

use App\Models\PayrollRun;
use App\Services\SalaryAuthorizationFormService;
use Illuminate\Contracts\View\View;

class SalaryAuthorizationFormPrintController extends Controller
{
    /**
     * The printable bank salary-upload form for one payroll run.
     */
    public function __invoke(PayrollRun $run): View
    {
        // Carries every employee's IBAN — the same sensitivity as the journal
        // voucher's payroll totals, gated by the same ability.
        abort_unless(auth()->user()?->can('viewJournalVoucher', $run), 403);

        return view('filament.payroll.salary-authorization-form-print', [
            'form' => app(SalaryAuthorizationFormService::class)->forRun($run),
        ]);
    }
}
