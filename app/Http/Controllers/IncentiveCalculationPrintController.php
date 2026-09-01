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


        $lines = $calculation->lines()
            ->with(['matter', 'fee', 'deductions', 'assistantLines.party'])
            ->get();

        $assistantSummary = app(IncentiveCalculatorService::class)
            ->getAssistantSummary($calculation);

        return view('filament.pages.incentive.calculation-print', compact('calculation', 'lines', 'assistantSummary'));
    }
}
