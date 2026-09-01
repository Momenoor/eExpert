<?php

namespace App\Http\Controllers;

use App\Models\IncentiveCalculation;
use App\Services\IncentiveCalculatorService;
use Illuminate\Http\Request;

class IncentiveCalculationPrintController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(IncentiveCalculation $calculation)
    {
        // The whole office's payroll. The route carried nothing but `auth`, so
        // every signed-in user could read it; the policy ability existed and was
        // simply never consulted. An assistant wanting their own figures has
        // My Incentive and the per-assistant print.
        abort_unless(auth()->user()?->can('print', $calculation), 403);

        $lines = $calculation->lines()
            ->with(['matter', 'fee', 'deductions', 'assistantLines.party'])
            ->get();

        $assistantSummary = app(IncentiveCalculatorService::class)
            ->getAssistantSummary($calculation);

        return view('filament.pages.incentive.calculation-print', compact('calculation', 'lines', 'assistantSummary'));
    }
}
