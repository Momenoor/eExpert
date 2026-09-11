<?php

namespace App\Http\Controllers;

use App\Filament\Pages\Payroll\EndOfServiceGratuityClosingVoucher;
use App\Models\Setting;
use App\Services\EndOfServiceGratuityClosingVoucherService;
use Illuminate\Contracts\View\View;

class EndOfServiceGratuityClosingVoucherPrintController extends Controller
{
    /**
     * The printable annual EOSG closing voucher.
     */
    public function __invoke(int $year): View
    {
        // Reuses the page's own Shield-generated gate rather than re-deriving
        // the permission string — the same access rule either way.
        abort_unless(EndOfServiceGratuityClosingVoucher::canAccess(), 403);

        $voucher = app(EndOfServiceGratuityClosingVoucherService::class)->forYear($year);

        // Nothing to print until someone presses Generate on the page — there
        // is no live figure to fall back to any more.
        abort_if($voucher === null, 404);

        return view('filament.payroll.journal-voucher-print', [
            'voucher' => $voucher,
            'companyName' => Setting::get('company_name') ?: config('app.name'),
        ]);
    }
}
