<?php

namespace App\Http\Controllers;

use App\Models\Quotation;
use App\Services\QuotationService;
use Illuminate\Contracts\View\View;

class QuotationPrintController extends Controller
{
    public function __invoke(Quotation $quotation): View
    {
        abort_unless(auth()->user()?->can('view', $quotation), 403);

        return view('filament.pms.quotation-print', [
            'quotation' => $quotation->load(['party', 'units']),
            'paymentSchedule' => app(QuotationService::class)->paymentSchedule($quotation),
        ]);
    }
}
