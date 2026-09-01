<?php

namespace App\Http\Controllers;

use App\Models\IncentiveCalculation;
use App\Models\Party;
use App\Services\IncentiveCalculatorService;

class IncentiveCalculationAssistantPrintController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(IncentiveCalculation $calculation, Party $party)
    {
        abort_unless(auth()->user()->can('Print:IncentiveCalculation'), 403);

        $assistantSummary = app(IncentiveCalculatorService::class)
            ->getAssistantSummary($calculation)
            ->firstWhere('party.id', $party->id);

        abort_if(! $assistantSummary, 404);

        return view('filament.pages.incentive.calculation-print-assistant', compact('calculation', 'assistantSummary'));
    }
}
