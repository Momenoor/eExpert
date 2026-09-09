<?php

namespace App\Http\Controllers;

use App\Models\PayrollRun;
use App\Models\Setting;
use App\Services\PayrollJournalVoucherService;
use Illuminate\Contracts\View\View;

class PayrollJournalVoucherPrintController extends Controller
{
    /**
     * The printable journal voucher for one payroll run.
     */
    public function __invoke(PayrollRun $run): View
    {
        // The whole office's salary bill in one sheet. `auth` alone is not
        // enough — the incentive print route carried exactly that and every
        // signed-in user could read the payroll from it.
        abort_unless(auth()->user()?->can('viewJournalVoucher', $run), 403);

        return view('filament.payroll.journal-voucher-print', [
            'voucher' => app(PayrollJournalVoucherService::class)->forRun($run),
            'companyName' => Setting::get('company_name') ?: config('app.name'),
        ]);
    }
}
