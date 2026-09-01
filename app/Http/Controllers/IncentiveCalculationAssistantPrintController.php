<?php

namespace App\Http\Controllers;

use App\Models\IncentiveCalculation;
use App\Models\Party;
use App\Services\IncentiveCalculatorService;

class IncentiveCalculationAssistantPrintController extends Controller
{
    /**
     * One assistant's incentive statement.
     *
     * The route previously carried nothing but `auth`, so any signed-in user
     * could put any party id in the URL and read that person's pay. Two ways in
     * now, and no others:
     *
     *  - whoever may print calculations (managers), for anybody; or
     *  - the assistant themselves, for their own party only.
     */
    public function __invoke(IncentiveCalculation $calculation, Party $party)
    {
        $user = auth()->user();
        $isOwnStatement = $user?->party?->id === $party->id;

        abort_unless(
            $isOwnStatement || $user?->can('print', $calculation),
            403
        );

        $assistantSummary = app(IncentiveCalculatorService::class)
            ->getAssistantSummary($calculation)
            ->firstWhere('party.id', $party->id);

        abort_if(! $assistantSummary, 404);

        return view('filament.pages.incentive.calculation-print-assistant', compact('calculation', 'assistantSummary'));
    }
}
