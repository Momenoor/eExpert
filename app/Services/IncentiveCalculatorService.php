<?php

namespace App\Services;

use App\Enums\MatterCommissiong;
use App\Enums\MatterDifficulty;
use App\Models\IncentiveAssistantExtra;
use App\Models\IncentiveAssistantLine;
use App\Models\IncentiveExtraRule;
use App\Models\IncentiveLine;
use App\Models\IncentiveLineDeduction;
use App\Models\IncentiveMetaAdjustment;
use App\Models\MatterParty;
use App\Models\MatterTypeIncentiveTier;
use App\Models\PartyLeave;
use App\Models\Setting;
use Carbon\Carbon;
use Carbon\Constants\UnitValue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;

class IncentiveCalculatorService
{
    /**
     * Defaults used when a setting hasn't been configured yet — these match
     * the PDF's original values. All are editable at runtime via the
     * Incentive Settings admin page (App\Models\Setting).
     */
    private const int DEFAULT_MINIMUM_MATTERS_PER_MONTH = 3;

    private const float DEFAULT_BELOW_MINIMUM_PENALTY_PCT = 2.0;

    private const float DEFAULT_COMMITTEE_FIXED_PERCENTAGE = 8.0;

    private const float DEFAULT_OFFICE_WORK_ADJUSTMENT = 2.0;

    public function __construct(private readonly IncentiveService $incentiveService) {}

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

            // Pick up any fee registered on a matter already in this
            // calculation since the last run (e.g. a late-arriving fee) so
            // recalculation stays current without a manual force-import.
            $this->incentiveService->syncNewFeesForCalculation($calculation);

            // Rates/thresholds and deduction toggles — configurable via the
            // Incentive Settings admin page, read fresh on every run so a
            // change takes effect the next time this period is calculated.
            $minimumMattersPerMonth = (int) Setting::get('incentive_minimum_matters_per_month', self::DEFAULT_MINIMUM_MATTERS_PER_MONTH);
            $belowMinimumPenaltyPct = (float) Setting::get('incentive_below_minimum_penalty_pct', self::DEFAULT_BELOW_MINIMUM_PENALTY_PCT);
            $committeeFixedPercentage = (float) Setting::get('incentive_committee_fixed_percentage', self::DEFAULT_COMMITTEE_FIXED_PERCENTAGE);
            $officeWorkAdjustment = (float) Setting::get('incentive_office_work_adjustment', self::DEFAULT_OFFICE_WORK_ADJUSTMENT);
            $enableBelowMinimumPenalty = (bool) Setting::get('incentive_enable_below_minimum_penalty', true);

            // Preserve manually entered fixed deductions across recalculation.
            $existingFixedDeductions = IncentiveAssistantExtra::where('incentive_calculation_id', $calculation->id)
                ->get(['party_id', 'fixed_deduction', 'fixed_deduction_reason'])
                ->keyBy('party_id');

            // Preserve manually entered per-assistant percentage overrides across
            // recalculation — keyed by matter+party since assistant lines get
            // deleted and recreated on every run. A manual override applies to
            // one specific assistant on a matter only, never to co-assistants
            // sharing the same matter.
            $existingAssistantOverrides = IncentiveAssistantLine::whereHas(
                'incentiveLine',
                fn ($q) => $q->where('incentive_calculation_id', $calculation->id)
            )
                ->whereNotNull('percentage_override')
                ->with('incentiveLine:id,matter_id')
                ->get(['party_id', 'percentage_override', 'incentive_line_id'])
                ->keyBy(fn ($al) => $al->incentiveLine->matter_id.'-'.$al->party_id);

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
                $rawDifficulty = $matter->difficulty;
                $difficulty = $rawDifficulty instanceof MatterDifficulty
                    ? $rawDifficulty
                    : (is_string($rawDifficulty) ? (MatterDifficulty::tryFrom($rawDifficulty) ?? MatterDifficulty::MEDIUM) : MatterDifficulty::MEDIUM);
                $difficultyValue = $difficulty->value;
                $completionDays = null;

                // ── Determine base percentage ──────────────────────────────────
                $rawCommissioning = $matter->commissioning;
                $commissioning = $rawCommissioning instanceof MatterCommissiong
                    ? $rawCommissioning
                    : MatterCommissiong::tryFrom((string) $rawCommissioning);
                $committeeAdj = 0.0;

                if ($commissioning === MatterCommissiong::COMMITTEE) {
                    // Committee-commissioned matters always get the flat
                    // committee rate, regardless of the matter's type's own
                    // calculation_type.
                    $basePercentage = $committeeFixedPercentage;
                    $completionDays = $this->getCompletionDays($matter);
                } else {
                    $basePercentage = match ($config->calculation_type) {

                        'fixed' => (float) $config->fixed_percentage,

                        'tiered', 'committee' => $this->tieredBasePercentage($difficulty, $matter, $completionDays),

                        default => 0.0,
                    };
                }

                if ($matter->is_office_work ?? false) {
                    $committeeAdj += $officeWorkAdjustment;
                }

                // ── Apply IncentiveMetaAdjustment additively ───────────────────
                $basePercentage += $this->calculateMetaAdjustment($matter);

                $effectivePercentage = max(0.0, $basePercentage + $committeeAdj);
                $baseAmount = round($feeAmountExclVat * $effectivePercentage / 100, 2);

                // ── Deductions ─────────────────────────────────────────────────
                // Deduction percentages are percentage points of the FEE (the same
                // scale as the incentive percentage itself), not a percentage of the
                // incentive amount — e.g. a -2% review deduction on a 9% incentive
                // leaves 7%, not 9% × 0.98. If deductions exceed the granted
                // percentage, the incentive for that matter is simply zero.
                [$totalDeductionPct, $deductions] = $this->calculateDeductions($matter, $difficulty, $commissioning);

                $hasCourtPenalty = collect($deductions)->contains(fn ($d) => $d['type'] === 'court_penalty');
                $netPercentage = max(0.0, $effectivePercentage - $totalDeductionPct);
                $netAmount = $hasCourtPenalty
                    ? 0.0
                    : round($feeAmountExclVat * $netPercentage / 100, 2);

                $line->update([
                    'completion_days' => $completionDays,
                    'difficulty' => $difficultyValue,
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
            $assistantTotals = []; // party_id => cumulative share (quota-eligible matters only — the bonus/penalty basis)
            $allAssistantPartyIds = collect(); // every assistant with any line at all (incl. excluded matters)
            $quotaMatterIds = collect();

            foreach ($linesByMatter as $matterId => $matterLines) {
                $config = $matterLines->last()['config'];
                $matter = $matterLines->last()['matter'];
                // The monthly achievement bonus / minimum-count penalty is a
                // day-based speed incentive for tiered work at a normal
                // individual pace, so it never applies to: fixed-percentage
                // matters (calculation_type = 'fixed' — e.g. liquidation,
                // insolvency, consultancy) or committee-commissioned matters
                // (flat committee rate) — regardless of whether the type is
                // also explicitly flagged exclude_from_incentive_count.
                $countsTowardQuota = $config->calculation_type !== 'fixed'
                    && $matter->commissioning !== MatterCommissiong::COMMITTEE
                    && ! ($matter->type?->exclude_from_incentive_count ?? false);

                $assistants = MatterParty::where('matter_id', $matterId)
                    ->where('role', 'expert')
                    ->where('type', 'assistant')
                    ->get();

                if ($assistants->isEmpty()) {
                    continue;
                }

                if ($countsTowardQuota) {
                    $quotaMatterIds->push($matterId);
                }

                $assistantCount = $assistants->count();
                $assistantRate = (float) $config->assistant_rate;

                // Sum across ALL fee lines for this matter
                $totalNetAmount = $matterLines->sum(fn ($l) => $l['line']->net_amount);
                $totalForAssistants = round($totalNetAmount * $assistantRate / 100, 2);

                // commission_percentage is a RELATIVE WEIGHT for splitting the
                // assistant pool among co-assistants on the same matter, not
                // an absolute fraction of it — e.g. two assistants both set
                // to 10 split the pool 50/50 (10:10), not 10% each with 80%
                // left unattributed. Whatever the pool ends up being (after
                // deductions/adjustments reduce the matter's percentage) is
                // always fully distributed in proportion to these weights.
                $hasCustomPercentages = $assistants->contains(fn ($mp) => ! empty($mp->commission_percentage));
                $totalCommissionWeight = $assistants->sum(fn ($mp) => (float) ($mp->commission_percentage ?? 0));
                $firstLine = $matterLines->first()['line'];

                foreach ($assistants as $mp) {
                    $override = $existingAssistantOverrides->get($matterId.'-'.$mp->party_id)?->percentage_override;

                    if ($override !== null) {
                        // A manual override is this specific assistant's final
                        // effective percentage of the fee for this matter — it
                        // does not affect any other assistant sharing the same
                        // matter. Deductions still apply on top, same as the
                        // automatic calculation; a court penalty still zeroes it.
                        $shareAmount = ($matter->has_court_penalty ?? false)
                            ? 0.0
                            : round($matterLines->sum(function ($l) use ($override) {
                                $netPct = max(0.0, (float) $override - (float) $l['line']->total_deduction_pct);

                                return $l['line']->fee_amount_excl_vat * $netPct / 100;
                            }), 2);
                    } else {
                        $shareAmount = ($hasCustomPercentages && $totalCommissionWeight > 0)
                            ? round($totalForAssistants * ((float) ($mp->commission_percentage ?? 0) / $totalCommissionWeight), 2)
                            : round($totalForAssistants / $assistantCount, 2);
                    }

                    IncentiveAssistantLine::create([
                        'incentive_line_id' => $firstLine->id,
                        'party_id' => $mp->party_id,
                        'percentage_override' => $override,
                        'share_amount' => $shareAmount,
                        'extra_percentage' => 0,
                        'extra_amount' => 0,
                        'minimum_penalty_pct' => 0,
                        'minimum_penalty_amount' => 0,
                        'total_amount' => $shareAmount,
                    ]);

                    $allAssistantPartyIds->push($mp->party_id);

                    if ($countsTowardQuota) {
                        $assistantTotals[$mp->party_id] = ($assistantTotals[$mp->party_id] ?? 0.0) + $shareAmount;
                    }
                }
            }

            $allAssistantPartyIds = $allAssistantPartyIds->unique()->values();

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

            foreach ($allAssistantPartyIds as $partyId) {
                $totalExtraAmount = 0.0;
                $totalPenaltyAmount = 0.0;
                $totalCompletedCount = 0;
                $totalProratedMinimum = 0;
                $preserved = $existingFixedDeductions->get($partyId);

                // We need to know which matters belong to which month to calculate monthly extra/penalty
                $assistantMatters = MatterParty::with(['matter', 'matter.type'])
                    ->where('party_id', $partyId)
                    ->where('role', 'expert')
                    ->where('type', 'assistant')
                    ->whereIn('matter_id', $quotaMatterIds->all())
                    ->get();

                // No tiered/committee matters this period — the achievement bonus and
                // minimum-count penalty simply don't apply (e.g. an assistant who only
                // worked fixed-rate committee/liquidation matters this calculation).
                if ($assistantMatters->isEmpty()) {
                    IncentiveAssistantExtra::create([
                        'incentive_calculation_id' => $calculation->id,
                        'party_id' => $partyId,
                        'completed_matter_count' => 0,
                        'meets_minimum' => true,
                        'minimum_penalty_pct' => 0,
                        'extra_percentage' => 0,
                        'extra_amount' => 0,
                        'penalty_amount' => 0,
                        'fixed_deduction' => $preserved?->fixed_deduction ?? 0,
                        'fixed_deduction_reason' => $preserved?->fixed_deduction_reason,
                    ]);

                    continue;
                }

                foreach ($months as $month) {
                    $monthMatters = $assistantMatters->filter(function ($mp) use ($month, $calculation) {
                        $triggerDate = $this->quotaTriggerDate($mp->matter, $calculation->id);
                        if (! $triggerDate) {
                            return false;
                        }

                        return $triggerDate->between($month['start'], $month['end'])
                            && ! ($mp->matter->type?->exclude_from_incentive_count ?? false);
                    });

                    $monthCount = $monthMatters->count();
                    $totalCompletedCount += $monthCount;

                    // Mid-month leave prorates the monthly minimum and the bonus-tier
                    // boundaries by the fraction of that month's working days the
                    // assistant was actually available (not on leave) — e.g. an
                    // assistant available for half the month's working days only
                    // needs half the usual minimum matters.
                    $availabilityRatio = $this->monthlyAvailabilityRatio($partyId, $month);
                    $proratedMinimum = (int) round($minimumMattersPerMonth * $availabilityRatio);
                    $totalProratedMinimum += $proratedMinimum;

                    $monthMeetsMinimum = $monthCount >= $proratedMinimum;

                    // Look up extra % for this month's count, prorating the rule's
                    // own bracket boundaries by the same availability ratio.
                    $matchedRule = $extraRules->first(function ($r) use ($monthCount, $availabilityRatio) {
                        $proratedMin = (int) round($r->min_count * $availabilityRatio);
                        $proratedMax = $r->max_count === null ? null : (int) round($r->max_count * $availabilityRatio);

                        return $monthCount >= $proratedMin && ($proratedMax === null || $monthCount <= $proratedMax);
                    });
                    $monthExtraPct = $monthMeetsMinimum ? (float) ($matchedRule?->extra_percentage ?? 0) : 0.0;

                    // PDF: a flat penalty applies to every matter in a shortfall
                    // month — it is NOT multiplied by how many matters short of the
                    // (prorated) minimum the assistant is. Skipped entirely when
                    // the below-minimum-penalty toggle is off.
                    $monthPenaltyPct = ($enableBelowMinimumPenalty && ! $monthMeetsMinimum) ? $belowMinimumPenaltyPct : 0.0;

                    if ($monthExtraPct <= 0.0 && $monthPenaltyPct <= 0.0) {
                        continue;
                    }

                    // Apply this month's flat percentage directly to each of the
                    // assistant's own matters completed that month (e.g. >6 matters
                    // in the month → +3% on every one of that month's matters) —
                    // not pooled and redistributed by overall weight across the
                    // whole calculation period.
                    $monthMatterIds = $monthMatters->pluck('matter_id')->all();
                    $monthAssistantLines = IncentiveAssistantLine::with('incentiveLine')
                        ->whereHas('line', function ($q) use ($calculation, $monthMatterIds) {
                            $q->where('incentive_calculation_id', $calculation->id)
                                ->whereIn('matter_id', $monthMatterIds);
                        })->where('party_id', $partyId)->get();

                    foreach ($monthAssistantLines as $al) {
                        // The penalty is a percentage of the case's FEE amount, not
                        // of the assistant's computed incentive share — e.g. 2% of a
                        // 10,000 AED fee (200 AED), not 2% of a 900 AED share (18 AED).
                        $feeAmount = (float) ($al->incentiveLine?->fee_amount_excl_vat ?? 0);
                        $lineExtra = round($al->share_amount * $monthExtraPct / 100, 2);
                        $linePenalty = round($feeAmount * $monthPenaltyPct / 100, 2);

                        $al->update([
                            'extra_percentage' => $monthExtraPct,
                            'extra_amount' => $lineExtra,
                            'minimum_penalty_pct' => $monthPenaltyPct,
                            'minimum_penalty_amount' => $linePenalty,
                            'total_amount' => max(0.0, $al->share_amount + $lineExtra - $linePenalty),
                        ]);

                        $totalExtraAmount += $lineExtra;
                        $totalPenaltyAmount += $linePenalty;
                    }
                }

                $totalShare = $assistantTotals[$partyId];

                // Summary record: totals are the exact sum of the per-line amounts
                // applied above; the percentage shown here is a blended average
                // across the whole calculation for reporting purposes only.
                IncentiveAssistantExtra::create([
                    'incentive_calculation_id' => $calculation->id,
                    'party_id' => $partyId,
                    'completed_matter_count' => $totalCompletedCount,
                    'meets_minimum' => $totalCompletedCount >= $totalProratedMinimum, // Optional interpretation
                    'minimum_penalty_pct' => $totalShare > 0 ? round($totalPenaltyAmount / $totalShare * 100, 2) : 0,
                    'extra_percentage' => $totalShare > 0 ? round($totalExtraAmount / $totalShare * 100, 2) : 0,
                    'extra_amount' => $totalExtraAmount,
                    'penalty_amount' => $totalPenaltyAmount,
                    'fixed_deduction' => $preserved?->fixed_deduction ?? 0,
                    'fixed_deduction_reason' => $preserved?->fixed_deduction_reason,
                ]);
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
        // Per-matter fee/base totals across ALL of a matter's incentive
        // lines — a matter with more than one fee gets one IncentiveLine per
        // fee, but only ONE IncentiveAssistantLine per assistant (attached
        // to the matter's FIRST line), so reading fee_amount_excl_vat /
        // base_amount off that single incentiveLine below would only ever
        // reflect the first fee while share/total amounts already sum every
        // fee.
        $matterFeeTotals = IncentiveLine::where('incentive_calculation_id', $calculation->id)
            ->selectRaw('matter_id, SUM(fee_amount_excl_vat) as total_fee_amount, SUM(base_amount) as total_base_amount')
            ->groupBy('matter_id')
            ->get()
            ->keyBy('matter_id');

        return IncentiveAssistantLine::with('party', 'incentiveLine.matter.court', 'incentiveLine.matter.type', 'incentiveLine.deductions')
            ->whereHas('incentiveLine', fn ($q) => $q->where('incentive_calculation_id', $calculation->id))
            ->get()
            ->groupBy('party_id')
            ->map(function (Collection $lines, int|string $partyId) use ($calculation, $matterFeeTotals) {
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
                    'fixed_deduction_reason' => $extra?->fixed_deduction_reason,
                    'total' => max(0.0, $lines->sum('total_amount') - ($extra?->fixed_deduction ?? 0)),
                    'matters' => $lines->groupBy('incentiveLine.matter_id')
                        ->map(function (Collection $matterLines) use ($matterFeeTotals) {
                            $incentiveLine = $matterLines->first()->incentiveLine;
                            $matter = $incentiveLine->matter;
                            $feeTotals = $matterFeeTotals->get($matter->id);
                            $extraPct = (float) $matterLines->first()->extra_percentage;
                            $penaltyPct = (float) $matterLines->first()->minimum_penalty_pct;

                            return [
                                'matter_id' => $matter->id,
                                'matter_reference' => $matter->reference,
                                // The matter's own difficulty is the source of truth —
                                // incentiveLine->difficulty is a denormalized copy that
                                // predates the current MatterDifficulty enum values.
                                'difficulty' => $matter->difficulty,
                                'commissioning' => $matter->commissioning,
                                'court_name' => $matter->court?->name,
                                'type_name' => $matter->type?->name,
                                'completion_days' => $incentiveLine->completion_days,
                                'fee_amount_excl_vat' => $feeTotals?->total_fee_amount ?? $incentiveLine->fee_amount_excl_vat,
                                'base_percentage' => $incentiveLine->base_percentage,
                                'committee_adjustment' => $incentiveLine->committee_adjustment,
                                'percentage' => $incentiveLine->effective_percentage,
                                'percentage_override' => $matterLines->first()->percentage_override,
                                'base_amount' => $feeTotals?->total_base_amount ?? $incentiveLine->base_amount,
                                'total_deduction_pct' => $incentiveLine->total_deduction_pct,
                                'deductions' => $incentiveLine->deductions,
                                'share_amount' => $matterLines->sum('share_amount'),
                                'extra_amount' => $matterLines->sum('extra_amount'),
                                'extra_reason' => $this->describeExtraReason($extraPct),
                                'penalty_amount' => $matterLines->sum('minimum_penalty_amount'),
                                'penalty_reason' => $this->describePenaltyReason($penaltyPct),
                                'total_amount' => $matterLines->sum('total_amount'),
                            ];
                        })
                        ->values(),
                ];
            })
            ->values();
    }

    /**
     * Human-readable reason for an applied achievement bonus percentage,
     * looked up from the matching IncentiveExtraRule bracket.
     */
    public function describeExtraReason(float $extraPct): ?string
    {
        if ($extraPct <= 0.0) {
            return null;
        }

        $rule = IncentiveExtraRule::where('extra_percentage', $extraPct)->first();

        if (! $rule) {
            return __('Achievement bonus for that month').': +'.$extraPct.'%';
        }

        $range = $rule->max_count === null
            ? __(':min or more matters that month', ['min' => $rule->min_count])
            : __(':min–:max matters that month', ['min' => $rule->min_count, 'max' => $rule->max_count]);

        return $range.' → +'.$extraPct.'%';
    }

    /**
     * Human-readable reason for the below-minimum monthly penalty — a flat
     * rate applied to every matter of that month regardless of how many
     * matters short of the minimum the assistant is.
     */
    public function describePenaltyReason(float $penaltyPct): ?HtmlString
    {
        if ($penaltyPct <= 0.0) {
            return null;
        }

        $line1 = e(__('Below the monthly minimum of :min matters', [
            'min' => (int) Setting::get('incentive_minimum_matters_per_month', self::DEFAULT_MINIMUM_MATTERS_PER_MONTH),
        ]));

        $line2 = e(__('flat :pct% of the case fee', [
            'pct' => $penaltyPct,
        ]));

        return new HtmlString("{$line1}<br>{$line2}");
    }

    /**
     * The custom incentive-period month label for a date: the cycle runs
     * from the 26th of a month through the 25th of the next, and is
     * labeled by the LATER calendar month (e.g. 26 Mar – 25 Apr → "April").
     */
    public function monthLabelForDate(mixed $date): ?string
    {
        if (! $date) {
            return null;
        }

        $dt = Carbon::parse($date);
        $cycleMonth = $dt->day >= 26 ? $dt->copy()->addMonthNoOverflow() : $dt->copy();

        return $cycleMonth->locale(app()->getLocale())->translatedFormat('F');
    }

    /**
     * Custom incentive-period month label for a matter, using the same
     * trigger-date resolution as the monthly achievement bonus/penalty
     * calculation (quotaTriggerDate) — kept as one shared source of truth so
     * the displayed "month" always matches which bucket a matter was
     * actually counted/penalized/bonused in.
     */
    public function matterMonthLabel(Model $matter, int $calculationId): ?string
    {
        return $this->monthLabelForDate($this->quotaTriggerDate($matter, $calculationId));
    }

    /**
     * The date a matter is attributed to for the monthly achievement quota —
     * completion-based (when the work was actually finished/collected), not
     * assignment-based, so "monthly achievement" reflects when the case was
     * finished: final_report_memo_date → final_report_at → fees
     * date → the fee's own date → the matter's created_at as a last resort.
     */
    public function quotaTriggerDate(Model $matter, int $calculationId): ?Carbon
    {
        $triggerDate = $matter->final_report_memo_date ?? $matter->final_report_at;

        if (! $triggerDate) {
            $line = IncentiveLine::where('incentive_calculation_id', $calculationId)
                ->where('matter_id', $matter->id)
                ->with('fee')
                ->first();

            $triggerDate = $line?->fee?->date;
        }

        $triggerDate ??= $matter->created_at;

        return $triggerDate ? Carbon::parse($triggerDate) : null;
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
                    && (is_null($adjustment->field_value) || $this->metaValuesMatch($adjustment->field_value, $meta->field_value))
            );

            if ($match) {
                $total += (float) $adjustment->percentage_adjustment;
            }
        }

        return $total;
    }

    /**
     * Compares a configured adjustment's field_value against a matter's
     * stored meta value. Toggle/checkbox fields store '1'/'0', while an
     * admin configuring a rule may naturally type 'true'/'false' — so
     * boolean-like tokens are normalized before falling back to an exact
     * string match for everything else (e.g. select option values).
     */
    private function metaValuesMatch(?string $configured, ?string $actual): bool
    {
        if ($configured === $actual) {
            return true;
        }

        $normalize = fn (?string $v): string => match (strtolower((string) $v)) {
            'true', '1' => 'true',
            'false', '0' => 'false',
            default => (string) $v,
        };

        return $normalize($configured) === $normalize($actual);
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
    private function calculateDeductions(Model $matter, MatterDifficulty $difficulty, ?MatterCommissiong $commissioning = null): array
    {
        $deductions = [];
        $totalPct = 0.0;

        // Each deduction rule can be switched off for the current period via
        // the Incentive Settings admin page — all default enabled, matching
        // the previously-hardcoded behavior.
        $enableFirstReviewDeduction = (bool) Setting::get('incentive_enable_first_review_deduction', true);
        $enableSubsequentReviewDeduction = (bool) Setting::get('incentive_enable_subsequent_review_deduction', true);
        $enableLateReportDeduction = (bool) Setting::get('incentive_enable_late_report_deduction', true);
        $enableCourtPenaltyExclusion = (bool) Setting::get('incentive_enable_court_penalty_exclusion', true);

        // ── Initial report: review deductions ────────────────────────────────
        $reviewCount = (int) ($matter->review_count ?? 0);
        $hasSubstantiveChanges = (bool) ($matter->has_substantive_changes ?? false);

        if ($enableFirstReviewDeduction && $hasSubstantiveChanges && $reviewCount >= 1) {
            $deductions[] = [
                'type' => 'review_first',
                'percentage' => 2.0,
                'notes' => __('Substantive changes in first review (−2%)'),
            ];
            $totalPct += 2.0;
        }

        if ($enableSubsequentReviewDeduction && $reviewCount >= 2) {
            $deductions[] = [
                'type' => 'review_subsequent',
                'percentage' => 1.0,
                'notes' => __('Second/subsequent review (−1%)'),
            ];
            $totalPct += 1.0;
        }

        // ── Final report: late submission deductions ──────────────────────────
        // Committee-commissioned matters are on the flat committee rate, not
        // the standard tiered completion timeline, so the completion-duration
        // / late-final-report deduction never applies to them.
        if ($enableLateReportDeduction && $commissioning !== MatterCommissiong::COMMITTEE && $matter->final_report_memo_date && $matter->final_report_at) {
            $finalDays = $this->workingDaysBetween(
                Carbon::parse($matter->final_report_memo_date),
                Carbon::parse($matter->final_report_at)
            );

            // 'hard' = exceptional difficulty (PDF: >5 working days = −0.5%, >10 = −1%)
            // 'easy'/'medium' = simple/normal (PDF: >2 days = −0.5%, >4 days = −1%)
            [$latePct, $lateNote] = match (true) {
                $difficulty === MatterDifficulty::HARD && $finalDays > 10 => [
                    1.0,
                    __('Final report :finalDays days late :condition', [
                        'finalDays' => $finalDays,
                        'condition' => '('.__('Hard').', >10 '.__('days').')',
                    ]),
                ],
                $difficulty === MatterDifficulty::HARD && $finalDays > 5 => [
                    0.5,
                    __('Final report :finalDays days late :condition', [
                        'finalDays' => $finalDays,
                        'condition' => '('.__('Hard').', >1 '.__('week').')',
                    ]),
                ],
                $difficulty !== MatterDifficulty::HARD && $finalDays > 4 => [
                    1.0,
                    __('Final report :finalDays days late :condition', [
                        'finalDays' => $finalDays,
                        'condition' => '(>4 '.__('days').')',
                    ]),
                ],
                $difficulty !== MatterDifficulty::HARD && $finalDays > 2 => [
                    0.5,
                    __('Final report :finalDays days late :condition', [
                        'finalDays' => $finalDays,
                        'condition' => '(>2 '.__('days').')',
                    ]),
                ],
                default => [0.0, ''],
            };

            if ($latePct > 0.0) {
                $deductions[] = ['type' => 'late_final_report', 'percentage' => $latePct, 'notes' => $lateNote];
                $totalPct += $latePct;
            }
        }

        // ── Court penalty: full exclusion ─────────────────────────────────────
        if ($enableCourtPenaltyExclusion && ($matter->has_court_penalty ?? false)) {
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
     * Working days from matters.distributed_at → matters.initial_report_at,
     * excluding the weekend and the matter's first assistant's leave days.
     * DB column is distributed_at (not received_date as was in the old code).
     * UAE weekend: Saturday and Sunday.
     */
    private function getCompletionDays(Model $matter): ?int
    {
        // DB column: matters.distributed_at
        if (! $matter->distributed_at || ! $matter->initial_report_at) {
            return null;
        }

        return $this->workingDaysBetween(
            Carbon::parse($matter->distributed_at),
            Carbon::parse($matter->initial_report_at),
            $this->firstAssistantLeaveRanges($matter)
        );
    }

    /**
     * Leave ranges for a matter's first-assigned assistant — used to exclude
     * their vacation days from the completion-days calculation. A matter's
     * completion days are shared by every assistant on it, so only the
     * first assistant's leave is used (not a per-assistant value).
     */
    private function firstAssistantLeaveRanges(Model $matter): Collection
    {
        $firstAssistant = MatterParty::where('matter_id', $matter->id)
            ->where('role', 'expert')
            ->where('type', 'assistant')
            ->orderBy('id')
            ->first();

        if (! $firstAssistant) {
            return collect();
        }

        return PartyLeave::where('party_id', $firstAssistant->party_id)->get();
    }

    /**
     * Count working days between two dates (exclusive of the end date —
     * duration style), excluding the weekend and, if given, any day that
     * falls inside one of the provided leave ranges.
     * UAE weekend: Saturday and Sunday.
     */
    public function workingDaysBetween(Carbon $from, Carbon $to, ?Collection $leaveRanges = null): int
    {
        $days = 0;
        $current = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();

        while ($current->lt($end)) {
            if (! $this->isWeekend($current) && ! $this->isOnLeave($current, $leaveRanges)) {
                $days++;
            }
            $current->addDay();
        }

        return $days;
    }

    /**
     * Count working days within a period, inclusive of both endpoints —
     * used to measure the total working days available in a month bucket
     * for proration, as opposed to workingDaysBetween's duration semantics.
     */
    private function countWorkingDaysInPeriod(Carbon $start, Carbon $end): int
    {
        $days = 0;
        $current = $start->copy()->startOfDay();
        $endInclusive = $end->copy()->startOfDay();

        while ($current->lte($endInclusive)) {
            if (! $this->isWeekend($current)) {
                $days++;
            }
            $current->addDay();
        }

        return $days;
    }

    /**
     * Fraction (0..1) of a month bucket's working days an assistant was
     * actually available — i.e. not on leave. Used to prorate the monthly
     * minimum and bonus-tier boundaries for a partial month.
     *
     * @param  array{start: Carbon, end: Carbon}  $month
     */
    private function monthlyAvailabilityRatio(int $partyId, array $month): float
    {
        $totalWorkingDays = $this->countWorkingDaysInPeriod($month['start'], $month['end']);

        if ($totalWorkingDays <= 0) {
            return 1.0;
        }

        $leaveRanges = PartyLeave::where('party_id', $partyId)->get();
        $leaveWorkingDays = 0;
        $current = $month['start']->copy()->startOfDay();
        $endInclusive = $month['end']->copy()->startOfDay();

        while ($current->lte($endInclusive)) {
            if (! $this->isWeekend($current) && $this->isOnLeave($current, $leaveRanges)) {
                $leaveWorkingDays++;
            }
            $current->addDay();
        }

        return max(0.0, min(1.0, 1.0 - ($leaveWorkingDays / $totalWorkingDays)));
    }

    /**
     * UAE weekend: Saturday and Sunday.
     */
    private function isWeekend(Carbon $date): bool
    {
        return in_array($date->dayOfWeek, [UnitValue::SATURDAY, UnitValue::SUNDAY], true);
    }

    private function isOnLeave(Carbon $date, ?Collection $leaveRanges): bool
    {
        if (! $leaveRanges || $leaveRanges->isEmpty()) {
            return false;
        }

        return $leaveRanges->contains(
            fn (PartyLeave $leave) => $date->between($leave->start_date, $leave->end_date)
        );
    }
}
