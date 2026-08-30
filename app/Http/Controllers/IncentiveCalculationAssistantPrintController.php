<?php

namespace App\Http\Controllers;

use App\Models\IncentiveCalculation;
use App\Models\Party;
use App\Services\IncentiveCalculatorService;
use Illuminate\Support\Facades\Gate;

class IncentiveCalculationAssistantPrintController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(IncentiveCalculation $calculation, Party $party)
    {
        Gate::authorize('print', $calculation);

        $assistantSummary = app(IncentiveCalculatorService::class)
            ->getAssistantSummary($calculation)
            ->firstWhere('party.id', $party->id);

        abort_if(! $assistantSummary, 404);

        return view('filament.pages.incentive.calculation-print-assistant', compact('calculation', 'assistantSummary'));
    }
}
