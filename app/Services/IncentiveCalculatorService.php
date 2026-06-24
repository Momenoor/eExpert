<?php

namespace App\Services;

use App\Enums\MatterDifficulty;
use App\Models\IncentiveAssistantExtra;
use App\Models\IncentiveAssistantLine;
use App\Models\IncentiveExtraRule;
use App\Models\IncentiveLine;
use App\Models\IncentiveLineDeduction;
use App\Models\IncentiveMetaAdjustment;
use App\Models\MatterParty;
use App\Models\MatterTypeIncentiveTier;
use Carbon\Carbon;
use Carbon\Constants\UnitValue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IncentiveCalculatorService
{
    // ── PDF constants ──────────────────────────────────────────────────────────

    /** Minimum matters per 2-month period to qualify for extra % */
    private const int MINIMUM_MATTERS_PER_MONTH = 3;

    /** Penalty per matter SHORT of monthly minimum (−2% per shortfall) */
    private const float BELOW_MINIMUM_PENALTY_PCT = 2.0;

    /**
     * Committee adjustment by matter.commissioning value:
     *   'individual' → office appointment → +2%
     *   'committee'  → external committee → −2%
     */
    private const float COMMITTEE_OFFICE_ADJUSTMENT = +2.0;

    private const float COMMITTEE_EXTERNAL_ADJUSTMENT = -2.0;

    // ── Public API ─────────────────────────────────────────────────────────────

    /**
     * Run the full calculation for a draft IncentiveCalculation.
     * Safe to re-run on draft — clears and recalculates existing lines.
     *
     * @throws \Throwable
     */
    public function calculate(Model $calculation): void
    {
        if ($calculation->isFinalized()) {
            throw new \RuntimeException('Cannot recalculate a finalized calculation.');
        }

        DB::transaction(function () use ($calculation) {

            // Clear previous assistant extras
            IncentiveAssistantExtra::where('incentive_calculation_id', $calculation->id)->delete();

            $lines = IncentiveLine::with(['fee', 'matter.type.incentiveConfig', 'matter.metas'])
                ->where('incentive_calculation_id', $calculation->id)
                ->get();

            if ($lines->isEmpty()) {
                return;
            }

            // ── Phase 1: calculate each incentive line ─────────────────────────
            $processedLines = collect();

            foreach ($lines as $line) {
                $line->deductions()->delete();
                $line->assistantLines()->delete();

                $matter = $line->matter;
                $config = $matter->type?->incentiveConfig;

                if (! $config) {
                    Log::warning("No incentive config for matter {$matter->reference} — skipping line {$line->id}.");

                    continue;
                }

                $feeAmountExclVat = (float) ($line->fee?->amount ?? 0);

                // DB column: matters.difficulty = 'easy' | 'medium' | 'hard'
                $difficulty = $matter->difficulty ?? 'medium';
                $committeeAdj = 0.0;
                $completionDays = null;

                // ── Determine base percentage ──────────────────────────────────
                $basePercentage = match ($config->calculation_type) {

                    'fixed' => (float) $config->fixed_percentage,

                    'committee' => $this->committeeBasePercentage(
                        $matter,
                        $difficulty,
                        $committeeAdj,  // captured by ref
                        $completionDays // captured by ref
                    ),

                    'tiered' => $this->tieredBasePercentage($difficulty, $matter, $completionDays),

                    default => 0.0,
                };

                // ── Apply IncentiveMetaAdjustment additively ───────────────────
                $metaAdjustment = $this->calculateMetaAdjustment($matter);
                $basePercentage += $metaAdjustment;

                $effectivePercentage = max(0.0, $basePercentage + $committeeAdj);
                $baseAmount = round($feeAmountExclVat * $effectivePercentage / 100, 2);

                // ── Deductions ─────────────────────────────────────────────────
                [$totalDeductionPct, $deductions] = $this->calculateDeductions($matter, $difficulty);

                $hasCourtPenalty = collect($deductions)->contains(fn ($d) => $d['type'] === 'court_penalty');
                $netAmount = $hasCourtPenalty
                    ? 0.0
                    : max(0.0, round($baseAmount * (1 - $totalDeductionPct / 100), 2));

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

                foreach ($deductions as $d) {
                    IncentiveLineDeduction::create([
                        'incentive_line_id' => $line->id,
                        'type' => $d['type'],
                        'percentage' => $d['percentage'],
                        'notes' => $d['notes'] ?? null,
                    ]);
                }

                $processedLines->push([
                    'line' => $line,
                    'config' => $config,
                    'matter' => $matter,
                ]);
            }

            if ($processedLines->isEmpty()) {
                return;
            }

            // ── Phase 2: split net_amount among assistants per matter ──────────
            $linesByMatter = $processedLines->groupBy(fn ($l) => $l['matter']->id);
            $assistantTotals = []; // party_id => cumulative share (tiered only)
            $tieredMatterIds = collect();

            foreach ($linesByMatter as $matterId => $matterLines) {
                $config = $matterLines->last()['config'];
                $matter = $matterLines->last()['matter'];
                $isTiered = in_array($config->calculation_type, ['tiered', 'committee'], true);

                $assistants = MatterParty::where('matter_id', $matterId)
                    ->where('role', 'expert')
                    ->where('type', 'assistant')
                    ->get();

                if ($assistants->isEmpty()) {
                    continue;
                }

                if ($isTiered) {
                    $tieredMatterIds->push($matterId);
                }

                $assistantCount = $assistants->count();
                $assistantRate = (float) $config->assistant_rate;

                // Sum across ALL fee lines for this matter
                $totalNetAmount = $matterLines->sum(fn ($l) => $l['line']->net_amount);
                $totalForAssistants = round($totalNetAmount * $assistantRate / 100, 2);

                $hasCustomPercentages = $assistants->contains(fn ($mp) => ! empty($mp->commission_percentage));
                $firstLine = $matterLines->first()['line'];

                foreach ($assistants as $mp) {
                    $shareAmount = $hasCustomPercentages
                        ? round($totalForAssistants * ((float) ($mp->commission_percentage ?? 0) / 100), 2)
                        : round($totalForAssistants / $assistantCount, 2);

                    IncentiveAssistantLine::create([
                        'incentive_line_id' => $firstLine->id,
                        'party_id' => $mp->party_id,
                        'share_amount' => $shareAmount,
                        'extra_percentage' => 0,
                        'extra_amount' => 0,
                        'minimum_penalty_pct' => 0,
                        'minimum_penalty_amount' => 0,
                        'total_amount' => $shareAmount,
                    ]);

                    if ($isTiered) {
                        $assistantTotals[$mp->party_id] = ($assistantTotals[$mp->party_id] ?? 0.0) + $shareAmount;
                    }
                }
            }

            // ── Phase 3: extra % and minimum penalty per assistant (Calculated per Month) ────────────
            $extraRules = IncentiveExtraRule::orderBy('min_count')->get();

            $startDate = Carbon::parse($calculation->period_start);
            $endDate = Carbon::parse($calculation->period_end);

            // Generate monthly periods
            $months = [];
            $current = $startDate->copy()->startOfDay();
            while ($current->lt($endDate)) {
                $monthEnd = $current->copy()->addMonth()->subSecond();
                if ($monthEnd->gt($endDate)) {
                    $monthEnd = $endDate->copy()->endOfDay();
                }
                $months[] = [
                    'start' => $current->copy(),
                    'end' => $monthEnd->copy(),
                ];
                $current->addMonth();
            }

            foreach (array_keys($assistantTotals) as $partyId) {
                $totalExtraAmount = 0.0;
                $totalPenaltyAmount = 0.0;
                $totalCompletedCount = 0;

                // We need to know which matters belong to which month to calculate monthly extra/penalty
                $assistantMatters = MatterParty::with(['matter', 'matter.type'])
                    ->where('party_id', $partyId)
                    ->where('role', 'expert')
                    ->where('type', 'assistant')
                    ->whereIn('matter_id', $tieredMatterIds->all())
                    ->get();

                foreach ($months as $month) {
                    $monthMatters = $assistantMatters->filter(function ($mp) use ($month) {
                        $triggerDate = $mp->matter->received_at ?? $mp->matter->created_at;
                        if (! $triggerDate) {
                            return false;
                        }
                        $dt = Carbon::parse($triggerDate);

                        return $dt->between($month['start'], $month['end'])
                            && ! ($mp->matter->type?->exclude_from_incentive_count ?? false);
                    });

                    $monthCount = $monthMatters->count();
                    $totalCompletedCount += $monthCount;

                    $monthMeetsMinimum = $monthCount >= self::MINIMUM_MATTERS_PER_MONTH;

                    // Extra percentage applies to the share of matters in THAT month?
                    // The requirement says "add extra percentage". Usually, extra percentage applies to the total base incentive.
                    // If we calculate per month, we apply month's extra % to month's share?
                    // But shared amount is already aggregated in $assistantTotals.

                    // Let's calculate the share for this specific month.
                    $monthMatterIds = $monthMatters->pluck('matter_id')->all();
                    $monthShare = IncentiveAssistantLine::whereHas('line', function ($q) use ($calculation, $monthMatterIds) {
                        $q->where('incentive_calculation_id', $calculation->id)
                            ->whereIn('matter_id', $monthMatterIds);
                    })->where('party_id', $partyId)->sum('share_amount');

                    // Look up extra % for this month's count
                    $matchedRule = $extraRules->first(
                        fn ($r) => $monthCount >= $r->min_count
                            && ($r->max_count === null || $monthCount <= $r->max_count)
                    );
                    $monthExtraPct = $monthMeetsMinimum ? (float) ($matchedRule?->extra_percentage ?? 0) : 0.0;
                    $monthExtraAmount = round($monthShare * $monthExtraPct / 100, 2);

                    $monthPenaltyPct = 0.0;
                    $monthPenaltyAmount = 0.0;
                    if (! $monthMeetsMinimum) {
                        $shortfall = self::MINIMUM_MATTERS_PER_MONTH - $monthCount;
                        $monthPenaltyPct = $shortfall * self::BELOW_MINIMUM_PENALTY_PCT;
                        $monthPenaltyAmount = round($monthShare * $monthPenaltyPct / 100, 2);
                    }

                    $totalExtraAmount += $monthExtraAmount;
                    $totalPenaltyAmount += $monthPenaltyAmount;
                }

                $totalShare = $assistantTotals[$partyId];

                // For the summary record, we might store averaged or representative percentages,
                // but the amounts are exact totals from monthly calculations.
                IncentiveAssistantExtra::create([
                    'incentive_calculation_id' => $calculation->id,
                    'party_id' => $partyId,
                    'completed_matter_count' => $totalCompletedCount,
                    'meets_minimum' => $totalCompletedCount >= (count($months) * self::MINIMUM_MATTERS_PER_MONTH), // Optional interpretation
                    'minimum_penalty_pct' => $totalShare > 0 ? round($totalPenaltyAmount / $totalShare * 100, 2) : 0,
                    'extra_percentage' => $totalShare > 0 ? round($totalExtraAmount / $totalShare * 100, 2) : 0,
                    'extra_amount' => $totalExtraAmount,
                    'penalty_amount' => $totalPenaltyAmount,
                ]);

                if ($totalExtraAmount <= 0 && $totalPenaltyAmount <= 0) {
                    continue;
                }

                $assistantLines = IncentiveAssistantLine::whereHas(
                    'line',
                    fn ($q) => $q->where('incentive_calculation_id', $calculation->id)
                        ->whereIn('matter_id', $tieredMatterIds->all())
                )->where('party_id', $partyId)->get();

                if ($assistantLines->isEmpty()) {
                    continue;
                }

                // Apply total extra/penalty proportionally across all lines for this party
                $totalShareCheck = $assistantLines->sum('share_amount');
                foreach ($assistantLines as $al) {
                    $proportion = $totalShareCheck > 0 ? $al->share_amount / $totalShareCheck : 0.0;
                    $lineExtra = round($totalExtraAmount * $proportion, 2);
                    $linePenalty = round($totalPenaltyAmount * $proportion, 2);

                    $al->update([
                        'extra_percentage' => $totalShare > 0 ? round($totalExtraAmount / $totalShare * 100, 2) : 0,
                        'extra_amount' => $lineExtra,
                        'minimum_penalty_pct' => $totalShare > 0 ? round($totalPenaltyAmount / $totalShare * 100, 2) : 0,
                        'minimum_penalty_amount' => $linePenalty,
                        'total_amount' => max(0.0, $al->share_amount + $lineExtra - $linePenalty),
                    ]);
                }
            }
        });
    }

    /**
     * Finalize a draft calculation.
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
     * Return a per-assistant summary collection for display / export.
     */
    public function getAssistantSummary(Model $calculation): Collection
    {
        return IncentiveAssistantLine::with('party', 'incentiveLine.matter')
            ->whereHas('incentiveLine', fn ($q) => $q->where('incentive_calculation_id', $calculation->id))
            ->get()
            ->groupBy('party_id')
            ->map(function (Collection $lines, int|string $partyId) use ($calculation) {
                $extra = IncentiveAssistantExtra::where('incentive_calculation_id', $calculation->id)
                    ->where('party_id', $partyId)
                    ->first();

                return [
                    'party' => $lines->first()->party,
                    'matter_count' => $lines->pluck('incentiveLine.matter_id')->unique()->count(),
                    'completed_matter_count' => $extra?->completed_matter_count ?? 0,
                    'count_for_incentive' => $lines->pluck('incentiveLine.matter')
                        ->unique('id')
                        ->filter(fn ($m) => ! ($m->type?->exclude_from_incentive_count ?? false))
                        ->count(),
                    'meets_minimum' => $extra?->meets_minimum ?? true,
                    'share_total' => $lines->sum('share_amount'),
                    'extra_percentage' => $extra?->extra_percentage ?? 0,
                    'extra_amount' => $extra?->extra_amount ?? 0,
                    'minimum_penalty_pct' => $extra?->minimum_penalty_pct ?? 0,
                    'penalty_amount' => $extra?->penalty_amount ?? 0,
                    'fixed_deduction' => $extra?->fixed_deduction ?? 0,
                    'total' => max(0.0, $lines->sum('total_amount') - ($extra?->fixed_deduction ?? 0)),
                ];
            })
            ->values();
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Base % for tiered matters (calculation_type = 'tiered').
     * Looks up the DB-stored tier table via the hardcoded TIERS const,
     * keyed on DB difficulty values: 'easy' | 'medium' | 'hard'.
     */
    private function tieredBasePercentage(MatterDifficulty $difficulty, Model $matter, ?int &$completionDays): float
    {
        $completionDays = $this->getCompletionDays($matter);

        if ($completionDays === null) {
            Log::warning("Matter {$matter->reference}: missing distributed_at or initial_report_at — base % = 0.");

            return 0.0;
        }

        $config = $matter->type?->incentiveConfig;
        if (! $config) {
            return 0.0;
        }

        return $this->lookupTier($config->id, $difficulty, $completionDays);
    }

    /**
     * Base % for committee matters (calculation_type = 'committee').
     *
     * Committee adjustment is determined by matters.commissioning:
     *   'individual' = office appointment → +2%
     *   'committee'  = external committee → −2%
     *
     * This is a DB-level field on the matter, not on the config
     * (matter_type_incentive_configs has no committee_source column).
     */
    private function committeeBasePercentage(
        Model $matter,
        MatterDifficulty $difficulty,
        float &$committeeAdj,
        ?int &$completionDays
    ): float {
        // matters.commissioning: 'individual' → office, 'committee' → external
        $committeeAdj = ($matter->commissioning === 'individual')
            ? self::COMMITTEE_OFFICE_ADJUSTMENT
            : self::COMMITTEE_EXTERNAL_ADJUSTMENT;

        $completionDays = $this->getCompletionDays($matter);

        if ($completionDays === null) {
            return 0.0;
        }

        $config = $matter->type?->incentiveConfig;
        if (! $config) {
            return 0.0;
        }

        return $this->lookupTier($config->id, $difficulty, $completionDays);
    }

    /**
     * Look up a tiered percentage from the database.
     * Returns 0.0 if difficulty is unknown or days exceed all tiers.
     */
    private function lookupTier(int $configId, MatterDifficulty $difficulty, int $days): float
    {
        $tier = MatterTypeIncentiveTier::where('config_id', $configId)
            ->where('difficulty', $difficulty->value)
            ->where('days_from', '<=', $days)
            ->where(function ($q) use ($days) {
                $q->where('days_to', '>=', $days)
                    ->orWhereNull('days_to');
            })
            ->first();

        return (float) ($tier?->percentage ?? 0.0);
    }

    /**
     * Sum all IncentiveMetaAdjustment percentage adjustments that match
     * the matter's metas. Applied additively on top of the base percentage.
     *
     * matter_metas columns: matter_id, field_name, field_value
     * incentive_meta_adjustments columns: field_name, field_value (nullable), percentage_adjustment
     */
    public function calculateMetaAdjustment(Model $matter): float
    {
        $adjustments = IncentiveMetaAdjustment::all();
        $matterMetas = $matter->metas;
        $total = 0.0;

        foreach ($adjustments as $adjustment) {
            $match = $matterMetas->first(
                fn ($meta) => $meta->field_name === $adjustment->field_name
                    && (is_null($adjustment->field_value) || $meta->field_value === $adjustment->field_value)
            );

            if ($match) {
                $total += (float) $adjustment->percentage_adjustment;
            }
        }

        return $total;
    }

    /**
     * Calculate deductions for a matter per the PDF rules.
     * Returns [total_deduction_pct, deductions_array].
     *
     * Difficulty DB values: 'easy' | 'medium' | 'hard'
     * 'hard' maps to the PDF's "exceptional" tier for final report lateness.
     *
     * DB columns used:
     *   matters.review_count              (int)
     *   matters.has_substantive_changes   (bool/tinyint)
     *   matters.has_court_penalty         (bool/tinyint)
     *   matters.final_report_memo_date    (date)
     *   matters.final_report_at           (date)
     */
    private function calculateDeductions(Model $matter, MatterDifficulty $difficulty): array
    {
        $deductions = [];
        $totalPct = 0.0;

        // ── Initial report: review deductions ────────────────────────────────
        $reviewCount = (int) ($matter->review_count ?? 0);
        $hasSubstantiveChanges = (bool) ($matter->has_substantive_changes ?? false);

        if ($hasSubstantiveChanges && $reviewCount >= 1) {
            $deductions[] = [
                'type' => 'review_first',
                'percentage' => 2.0,
                'notes' => __('Substantive changes in first review (−2%)'),
            ];
            $totalPct += 2.0;
        }

        if ($reviewCount >= 2) {
            $deductions[] = [
                'type' => 'review_subsequent',
                'percentage' => 1.0,
                'notes' => __('Second/subsequent review (−1%)'),
            ];
            $totalPct += 1.0;
        }

        // ── Final report: late submission deductions ──────────────────────────
        if ($matter->final_report_memo_date && $matter->final_report_at) {
            $finalDays = $this->workingDaysBetween(
                Carbon::parse($matter->final_report_memo_date),
                Carbon::parse($matter->final_report_at)
            );

            // 'hard' = exceptional difficulty (PDF: >5 working days = −0.5%, >10 = −1%)
            // 'easy'/'medium' = simple/normal (PDF: >2 days = −0.5%, >4 days = −1%)
            [$latePct, $lateNote] = match (true) {
                $difficulty === MatterDifficulty::HARD && $finalDays > 10 => [1.0, "Final report {$finalDays} days late (hard, >10 days)"],
                $difficulty === MatterDifficulty::HARD && $finalDays > 5 => [0.5, "Final report {$finalDays} days late (hard, >1 week)"],
                $difficulty !== MatterDifficulty::HARD && $finalDays > 4 => [1.0, "Final report {$finalDays} days late (>4 days)"],
                $difficulty !== MatterDifficulty::HARD && $finalDays > 2 => [0.5, "Final report {$finalDays} days late (>2 days)"],
                default => [0.0, ''],
            };

            if ($latePct > 0.0) {
                $deductions[] = ['type' => 'late_final_report', 'percentage' => $latePct, 'notes' => $lateNote];
                $totalPct += $latePct;
            }
        }

        // ── Court penalty: full exclusion ─────────────────────────────────────
        if ($matter->has_court_penalty ?? false) {
            $deductions[] = [
                'type' => 'court_penalty',
                'percentage' => 100.0,
                'notes' => __('Office received court penalty — full exclusion'),
            ];
            $totalPct = 100.0;
        }

        return [$totalPct, $deductions];
    }

    /**
     * Working days from matters.distributed_at → matters.initial_report_at
     * DB column is distributed_at (not received_date as was in the old code).
     * UAE weekend: Friday (5) and Saturday (6).
     */
    private function getCompletionDays(Model $matter): ?int
    {
        // DB column: matters.distributed_at
        if (! $matter->distributed_at || ! $matter->initial_report_at) {
            return null;
        }

        return $this->workingDaysBetween(
            Carbon::parse($matter->distributed_at),
            Carbon::parse($matter->initial_report_at)
        );
    }

    /**
     * Count working days between two dates.
     * UAE weekend: Friday (5) and Saturday (6).
     */
    public function workingDaysBetween(Carbon $from, Carbon $to): int
    {
        $days = 0;
        $current = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();

        while ($current->lt($end)) {
            if (! in_array($current->dayOfWeek, [UnitValue::FRIDAY, UnitValue::SATURDAY], true)) {
                $days++;
            }
            $current->addDay();
        }

        return $days;
    }
}
