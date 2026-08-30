<?php

namespace App\Http\Controllers;

use App\Models\IncentiveCalculation;
use App\Models\User;
use App\Services\IncentiveCalculatorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class IncentiveCalculationPrintController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(IncentiveCalculation $calculation)
    {

        abort_unless(auth()->user()->can('Print:IncentiveCalculation'), 403);


        $lines = $calculation->lines()
            ->with(['matter', 'fee', 'deductions', 'assistantLines.party'])
            ->get();

        $assistantSummary = app(IncentiveCalculatorService::class)
            ->getAssistantSummary($calculation);

        return view('filament.pages.incentive.calculation-print', compact('calculation', 'lines', 'assistantSummary'));
    }
}
