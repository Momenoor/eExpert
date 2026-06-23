<?php

namespace App\Services;

use App\Models\IncentiveAssistantExtra;
use App\Models\IncentiveAssistantLine;
use App\Models\IncentiveCalculation;
use App\Models\IncentiveExtraRule;
use App\Models\IncentiveLine;
use App\Models\IncentiveLineDeduction;
use App\Models\MatterParty;
use Carbon\Constants\UnitValue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IncentiveCalculatorService
{
    // Minimum matters required per period to qualify for extra %
    const int MINIMUM_MATTERS = 6;

    // Penalty per matter below minimum
    const float BELOW_MINIMUM_PENALTY_PCT = 2.0;

    // Committee adjustments
    const float COMMITTEE_OFFICE_ADJUSTMENT = +2.0;

    const float COMMITTEE_EXTERNAL_ADJUSTMENT = -2.0;

    /**
     * Calculate working days between two dates (excluding Fri/Sat — UAE weekend)
     */
    public function workingDaysBetween(Carbon $from, Carbon $to): int
    {
        $days = 0;
        $current = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();

        while ($current->lt($end)) {
            // UAE weekend: Friday (5) and Saturday (6)
            if (!in_array($current->dayOfWeek, [UnitValue::FRIDAY, UnitValue::SATURDAY])) {
                $days++;
            }
            $current->addDay();
        }

        return $days;
    }

    /**
     * Run the full calculation for a draft IncentiveCalculation.
     * Safe to re-run on draft — clears and recalculates.
     *
     * @throws \Throwable
     */
    public function calculate(Model $calculation): void
    {
        if ($calculation->isFinalized()) {
            throw new \RuntimeException('Cannot recalculate a finalized calculation.');
        }

        DB::transaction(function () use ($calculation) {

            // ── Clear auxiliary data ──────────────────────────────────────────
            IncentiveAssistantExtra::where('incentive_calculation_id', $calculation->id)->delete();

            // ── Get existing lines to calculate ──────────────────────────────
            $lines = IncentiveLine::where('incentive_calculation_id', $calculation->id)->get();

            if ($lines->isEmpty()) {
                return;
            }

            // ── Process each line ──────────────────────────────────────────────
            foreach ($lines as $line) {
                // Clear old deductions and assistant lines for this line
                $line->deductions()->delete();
                $line->assistantLines()->delete();

                $fee = $line->fee;
                $matter = $line->matter;
                $config = $matter->type?->incentiveConfig;
                //Log::debug('Calculating incentive for matter ' . $matter->reference. ' - '.$config);
                if (!$config) {
                    continue;
                }

                $feeAmountExclVat = $fee?->amount ?? 0;
                $basePercentage = 0;
                $completionDays = null;
                $difficulty = $matter->difficulty?->value ?? 'normal'; // from matter enum
                $committeeAdj = 0;

                // ── Determine base % ──────────────────────────────────────────
                if ($config->calculation_type === 'fixed') {
                    // e.g. liquidation/insolvency/bankruptcy = 8%
                    $basePercentage = $config->fixed_percentage;

                } elseif ($config->calculation_type === 'committee') {
                    // Find tiered base from linked individual config or use 0
                    // Committee = individual tiered rate ± 2%
                    $committeeAdj = $config->committee_source === 'office'
                        ? self::COMMITTEE_OFFICE_ADJUSTMENT
                        : self::COMMITTEE_EXTERNAL_ADJUSTMENT;

                    // Get base from tiered calculation
                    $completionDays = $this->getCompletionDays($matter);
                    $basePercentage = $this->getTieredPercentage($config, $difficulty, $completionDays);

                    if ($basePercentage === null) {
                        $basePercentage = 0;
                    }

                } elseif ($config->calculation_type === 'tiered') {
                    // Working days from received_date to initial_report_at
                    $completionDays = $this->getCompletionDays($matter);
                    $basePercentage = $this->getTieredPercentage($config, $difficulty, $completionDays);

                    if ($basePercentage === null) {
                        $basePercentage = 0;
                    }
                }

                // Custom Field (MatterMeta) Percentage Adjustment (Logic from IncentiveService)
                $incentiveService = app(IncentiveService::class);
                $basePercentage = $incentiveService->calculateBasePercentage($matter);

                $effectivePercentage = max(0, $basePercentage + $committeeAdj);
                $baseAmount = round($feeAmountExclVat * $effectivePercentage / 100, 2);

                // ── Calculate deductions ──────────────────────────────────────
                [$totalDeductionPct, $deductions] = $this->calculateDeductions($matter, $difficulty);
                $netAmount = max(0, round($baseAmount * (1 - $totalDeductionPct / 100), 2));

                // Check court penalty — full exclusion
                $hasCourtPenalty = collect($deductions)->contains(fn($d) => $d['type'] === 'court_penalty');
                if ($hasCourtPenalty) {
                    $netAmount = 0;
                }

                $line->update([
                    'completion_days' => $completionDays,
                    'difficulty' => $difficulty,
                    'fee_amount_excl_vat' => $feeAmountExclVat,
                    'base_percentage' => $basePercentage,
                    'committee_adjustment' => $committeeAdj,
                    'effective_percentage' => $effectivePercentage,
                    'base_amount' => $baseAmount,
                    'review_deduction_pct' => collect($deductions)->whereIn('type', ['review_first', 'review_subsequent'])->sum('percentage'),
                    'final_report_deduction_pct' => collect($deductions)->where('type', 'late_final_report')->sum('percentage'),
                    'total_deduction_pct' => $totalDeductionPct,
                    'net_amount' => $netAmount,
                ]);

                // Save deduction audit trail
                foreach ($deductions as $d) {
                    IncentiveLineDeduction::create([
                        'incentive_line_id' => $line->id,
                        'type' => $d['type'],
                        'percentage' => $d['percentage'],
                        'notes' => $d['notes'] ?? null,
                    ]);
                }

                $lines->push([
                    'line' => $line,
                    'config' => $config,
                    'matter' => $matter,
                ]);
            }

            if ($lines->isEmpty()) {
                return;
            }

            // ── Split net_amount among assistants per matter ──────────────────
            $linesByMatter = $lines->groupBy(fn($l) => $l['matter']->id);
            $assistantTotals = []; // party_id => total share (tiered only, for extra calc)
            $tieredMatterIds = collect();

            foreach ($linesByMatter as $matterId => $matterLines) {

                $config = $matterLines->last()['config'];
                $matter = $matterLines->last()['matter'];
                Log::debug('Calculating incentive for matter ' . $matter->reference . ' - ' . $config);
                $isTiered = in_array($config->calculation_type, ['tiered', 'committee']);

                // Get assistant parties on this matter
                $assistants = MatterParty::where('matter_id', $matterId)
                    ->where('role', 'expert')
                    ->where('type', 'assistant')
                    ->get();

                if ($assistants->isEmpty()) {
                    continue;
                }

                $assistantCount = $assistants->count();
                $assistantRate = $config->assistant_rate; // % of net_amount for assistants

                if ($isTiered) {
                    $tieredMatterIds->push($matterId);
                }


                $line = $matterLines->first();
                $totalForAssistants = round($line->net_amount * $assistantRate / 100, 2);

                // Check if custom percentages are set
                $hasCustomPercentages = $assistants->contains(fn($mp) => !empty($mp->commission_percentage));

                foreach ($assistants as $mp) {
                    if ($hasCustomPercentages) {
                        $commission = (float)($mp->commission_percentage ?? 0);
                        $shareAmount = round($totalForAssistants * ($commission / 100), 2);
                    } else {
                        $shareAmount = round($totalForAssistants / $assistantCount, 2);
                    }

                    IncentiveAssistantLine::create([
                        'incentive_line_id' => $line->id,
                        'party_id' => $mp->party_id,
                        'share_amount' => $shareAmount,
                        'extra_percentage' => 0,
                        'extra_amount' => 0,
                        'minimum_penalty_pct' => 0,
                        'minimum_penalty_amount' => 0,
                        'total_amount' => $shareAmount,
                    ]);

                    if ($isTiered) {
                        $assistantTotals[$mp->party_id] = ($assistantTotals[$mp->party_id] ?? 0) + $shareAmount;
                    }
                }
            }


            // ── Apply extra % and minimum penalty per assistant ───────────────
            // Count completed tiered matters per assistant in this period
            foreach (array_keys($assistantTotals) as $partyId) {
                $completedCount = MatterParty::where('party_id', $partyId)
                    ->where('role', 'expert')
                    ->where('type', 'assistant')
                    ->whereIn('matter_id', $tieredMatterIds->all())
                    ->count();

                $totalShare = $assistantTotals[$partyId];

                // ── Extra % (only if meets minimum) ──────────────────────────
                // PDF: 5 = 1.5%, 6 = 2%, >6 = 3%
                $meetsMinimum = $completedCount >= self::MINIMUM_MATTERS;
                $extraPercentage = $meetsMinimum
                    ? IncentiveExtraRule::getPercentageForCount($completedCount)
                    : 0;
                $extraAmount = $extraPercentage > 0
                    ? round($totalShare * $extraPercentage / 100, 2)
                    : 0;

                // ── Minimum penalty (if below 6) ──────────────────────────────
                // PDF: -2% per matter below 6
                $minimumPenaltyPct = 0;
                $minimumPenaltyAmount = 0;
                if (!$meetsMinimum) {
                    $shortfall = self::MINIMUM_MATTERS - $completedCount;
                    $minimumPenaltyPct = $shortfall * self::BELOW_MINIMUM_PENALTY_PCT;
                    $minimumPenaltyAmount = round($totalShare * $minimumPenaltyPct / 100, 2);
                }

                // Record summary
                IncentiveAssistantExtra::create([
                    'incentive_calculation_id' => $calculation->id,
                    'party_id' => $partyId,
                    'completed_matter_count' => $completedCount,
                    'meets_minimum' => $meetsMinimum,
                    'minimum_penalty_pct' => $minimumPenaltyPct,
                    'extra_percentage' => $extraPercentage,
                    'extra_amount' => $extraAmount,
                    'penalty_amount' => $minimumPenaltyAmount,
                ]);

                if ($extraAmount <= 0 && $minimumPenaltyAmount <= 0) {
                    continue;
                }

                // Distribute extra/penalty proportionally across assistant lines
                $assistantLines = IncentiveAssistantLine::whereHas(
                    'line',
                    fn($q) => $q->where('incentive_calculation_id', $calculation->id)
                        ->whereIn('matter_id', $tieredMatterIds->all())
                )->where('party_id', $partyId)->get();

                if ($assistantLines->isEmpty()) {
                    continue;
                }

                $totalShareCheck = $assistantLines->sum('share_amount');

                foreach ($assistantLines as $al) {
                    $proportion = $totalShareCheck > 0 ? $al->share_amount / $totalShareCheck : 0;
                    $lineExtra = round($extraAmount * $proportion, 2);
                    $linePenalty = round($minimumPenaltyAmount * $proportion, 2);

                    $al->update([
                        'extra_percentage' => $extraPercentage,
                        'extra_amount' => $lineExtra,
                        'minimum_penalty_pct' => $minimumPenaltyPct,
                        'minimum_penalty_amount' => $linePenalty,
                        'total_amount' => max(0, $al->share_amount + $lineExtra - $linePenalty),
                    ]);
                }
            }
        });
    }

    /**
     * Get working days from received_date to initial_report_at
     * Using UAE working days (Mon–Thu + Sun, off: Fri + Sat)
     * OR standard Mon–Fri depending on your office
     */
    private function getCompletionDays($matter): ?int
    {
        if (!$matter->received_date || !$matter->initial_report_at) {
            return null;
        }

        return $this->workingDaysBetween(
            Carbon::parse($matter->received_date),
            Carbon::parse($matter->initial_report_at)
        );
    }

    /**
     * Get tiered % based on difficulty and days
     */
    private function getTieredPercentage(Model $config, string $difficulty, ?int $days): ?float
    {
        if ($days === null) {
            return null;
        }

        return $config->tiers
            ->where('difficulty', $difficulty)
            ->first(fn($tier) => $days >= $tier->days_from &&
                ($tier->days_to === null || $days <= $tier->days_to)
            )
            ?->percentage;
    }

    /**
     * Calculate deductions for a matter
     * Returns [total_deduction_pct, [deduction_array]]
     *
     * Deduction rules from PDF:
     * Initial report:
     *   - First review with substantive changes: -2%
     *   - Second/subsequent reviews: -1%
     * Final report:
     *   - Simple/Normal: >2 working days = -0.5%, >4 days = -1%
     *   - Exceptional: >1 week = -0.5%, >10 days = -1%
     * Court penalty: full exclusion (handled separately)
     */
    private function calculateDeductions($matter, string $difficulty): array
    {
        $deductions = [];
        $totalPct = 0;

        // ── Review deductions (stored on matter or related model) ─────────────
        // Assumes matter has: review_count, has_substantive_changes (bool)
        // You may store these on matter or as notes/requests
        $reviewCount = $matter->review_count ?? 0;
        $hasSubstantiveChanges = $matter->has_substantive_changes ?? false;

        if ($hasSubstantiveChanges && $reviewCount >= 1) {
            $deductions[] = ['type' => 'review_first', 'percentage' => 2.0,
                'notes' => 'Substantive changes in first review'];
            $totalPct += 2.0;
        }

        if ($reviewCount >= 2) {
            $deductions[] = ['type' => 'review_subsequent', 'percentage' => 1.0,
                'notes' => 'Second/subsequent review'];
            $totalPct += 1.0;
        }

        // ── Final report late deduction ───────────────────────────────────────
        // Days from final report memo date to final_report_at
        if ($matter->final_report_memo_date && $matter->final_report_at) {
            $finalDays = $this->workingDaysBetween(
                Carbon::parse($matter->final_report_memo_date),
                Carbon::parse($matter->final_report_at)
            );

            if ($difficulty === 'exceptional') {
                // Exceptional: >1 week = -0.5%, >10 days = -1%
                if ($finalDays > 10) {
                    $deductions[] = ['type' => 'late_final_report', 'percentage' => 1.0,
                        'notes' => "Final report {$finalDays} days late (exceptional, >10 days)"];
                    $totalPct += 1.0;
                } elseif ($finalDays > 5) {
                    $deductions[] = ['type' => 'late_final_report', 'percentage' => 0.5,
                        'notes' => "Final report {$finalDays} days late (exceptional, >1 week)"];
                    $totalPct += 0.5;
                }
            } else {
                // Simple/Normal: >2 days = -0.5%, >4 days = -1%
                if ($finalDays > 4) {
                    $deductions[] = ['type' => 'late_final_report', 'percentage' => 1.0,
                        'notes' => "Final report {$finalDays} days late (>4 days)"];
                    $totalPct += 1.0;
                } elseif ($finalDays > 2) {
                    $deductions[] = ['type' => 'late_final_report', 'percentage' => 0.5,
                        'notes' => "Final report {$finalDays} days late (>2 days)"];
                    $totalPct += 0.5;
                }
            }
        }

        // ── Court penalty — full exclusion ────────────────────────────────────
        if ($matter->has_court_penalty ?? false) {
            $deductions[] = ['type' => 'court_penalty', 'percentage' => 100.0,
                'notes' => 'Office received court penalty — full exclusion'];
            $totalPct = 100.0;
        }

        return [$totalPct, $deductions];
    }

    /**
     * Finalize a calculation
     */
    public function finalize(Model $calculation): void
    {
        if ($calculation->isFinalized()) {
            throw new \RuntimeException('Already finalized.');
        }
        $calculation->update([
            'status' => 'finalized',
            'finalized_at' => now(),
        ]);
    }

    /**
     * Summary per assistant
     */
    public function getAssistantSummary(Model $calculation): Collection
    {
        return IncentiveAssistantLine::with('party', 'incentiveLine.matter')
            ->whereHas('incentiveLine', fn($q) => $q->where('incentive_calculation_id', $calculation->id))
            ->get()
            ->groupBy('party_id')
            ->map(function ($lines, $partyId) use ($calculation) {
                $extra = IncentiveAssistantExtra::where('incentive_calculation_id', $calculation->id)
                    ->where('party_id', $partyId)
                    ->first();

                return [
                    'party' => $lines->first()->party,
                    'matter_count' => $lines->pluck('incentiveLine.matter_id')->unique()->count(),
                    'completed_matter_count' => $extra?->completed_matter_count ?? 0,
                    'meets_minimum' => $extra?->meets_minimum ?? true,
                    'share_total' => $lines->sum('share_amount'),
                    'extra_percentage' => $extra?->extra_percentage ?? 0,
                    'extra_amount' => $extra?->extra_amount ?? 0,
                    'minimum_penalty_pct' => $extra?->minimum_penalty_pct ?? 0,
                    'penalty_amount' => $extra?->penalty_amount ?? 0,
                    'fixed_deduction' => $extra?->fixed_deduction ?? 0,
                    'total' => max(0, $lines->sum('total_amount') - ($extra?->fixed_deduction ?? 0)),
                ];
            })
            ->values();
    }
}
