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

        return view('filament.payroll.journal-voucher-print', [
            'voucher' => app(EndOfServiceGratuityClosingVoucherService::class)->forYear($year),
            'companyName' => Setting::get('company_name') ?: config('app.name'),
        ]);
    }
}
