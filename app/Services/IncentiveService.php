<?php

namespace App\Services;

use App\Enums\FeeType;
use App\Models\IncentiveExtraRule;
use App\Models\IncentiveLine;
use App\Models\Matter;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IncentiveService
{
    /**
     * Get matters that qualify for a calculation period.
     */
    public function getQualifyingMatters(Carbon $start, Carbon $end, array $filters = []): Collection
    {
        if (empty($filters['assistant_ids'])) {
            return collect();
        }
        // Get already imported matter/fee IDs from ANY finalized or existing calculation
        $importedFeeIds = IncentiveLine::pluck('fee_id')->filter()->toArray();

        // Condition A: final_report_date trigger
        $reportDateQuery = Matter::query()
            ->whereHas('type', function ($q) {
                $q->where('incentive_trigger_type', 'final_report_date');
            })
            ->whereBetween('final_report_at', [$start->startOfDay(), $end->endOfDay()]);

        // Condition B: fees_registered_date trigger — triggered by the fee's
        // own REGISTRATION date (fees.date), regardless of whether/when it
        // was actually collected. The incentive is based on the registered
        // fee amount, not the amount collected so far.
        $feeRegisteredQuery = Matter::query()
            ->whereHas('type', function ($q) {
                $q->where('incentive_trigger_type', 'fees_registered_date');
            })
            ->whereHas('fees', function ($q) use ($start, $end) {
                $q->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                    ->where(fn ($q2) => $q2->whereNull('type')->orWhere('type', '!=', FeeType::VAT));
            });

        // Condition C: allow_current_status_import — the matter is still
        // "current" (not yet finally reported), but has a non-VAT fee
        // REGISTERED within the period (fees.date, not collection/allocation
        // date) that hasn't had an incentive calculated on it yet. Scoped
        // per FEE (not per matter) so a matter already imported for an
        // earlier period's fee can still be reimported later once a new,
        // not-yet-incentivized fee comes in.
        $currentStatusQuery = Matter::query()
            ->whereHas('type', function ($q) {
                $q->where('allow_current_status_import', true);
            })
            ->whereNull('final_report_at')
            ->whereHas('fees', function ($q) use ($start, $end, $importedFeeIds) {
                $q->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                    ->where(fn ($q2) => $q2->whereNull('type')->orWhere('type', '!=', FeeType::VAT));
                if (! empty($importedFeeIds)) {
                    $q->whereNotIn('id', $importedFeeIds);
                }
            });

        if (! empty($filters['expert_ids'])) {
            $reportDateQuery->whereHas('expertsOnly', function ($q) use ($filters) {
                $q->whereIn('party_id', $filters['expert_ids']);
            });
            $feeRegisteredQuery->whereHas('expertsOnly', function ($q) use ($filters) {
                $q->whereIn('party_id', $filters['expert_ids']);
            });
            $currentStatusQuery->whereHas('expertsOnly', function ($q) use ($filters) {
                $q->whereIn('party_id', $filters['expert_ids']);
            });
        }

        if (! empty($filters['assistant_ids'])) {
            $reportDateQuery->whereHas('assistantsOnly', function ($q) use ($filters) {
                $q->whereIn('party_id', $filters['assistant_ids']);
            });
            $feeRegisteredQuery->whereHas('assistantsOnly', function ($q) use ($filters) {
                $q->whereIn('party_id', $filters['assistant_ids']);
            });
            $currentStatusQuery->whereHas('assistantsOnly', function ($q) use ($filters) {
                $q->whereIn('party_id', $filters['assistant_ids']);
            });
        }

        $matters = $reportDateQuery->get()
            ->concat($feeRegisteredQuery->get())
            ->concat($currentStatusQuery->get())
            ->unique('id');

        // Filter out matters where ALL eligible fees are already imported
        return $matters->values()->filter(function (Matter $matter) use ($importedFeeIds) {
            $eligibleFees = $matter->fees()
                ->where(fn ($q) => $q->whereNull('type')->orWhere('type', '!=', FeeType::VAT))
                ->pluck('id')
                ->toArray();

            if (empty($eligibleFees)) {
                // A finished matter (final_report_date trigger) with NO fees
                // at all still qualifies — it must still be importable so it
                // counts toward the monthly achievement quota, even though
                // it contributes nothing monetarily. Fee-driven trigger
                // types have nothing to import without a fee.
                return $matter->type?->incentive_trigger_type === 'final_report_date'
                    && $matter->final_report_at !== null
                    && ! $matter->incentiveLines()->whereNull('fee_id')->exists();
            }

            // If at least one eligible fee is NOT imported, show it.
            return ! empty(array_diff($eligibleFees, $importedFeeIds));
        });
    }

    /**
     * Calculate incentive data for a collection of matters within a period.
     */
    public function calculateMattersData(Collection $matters, Carbon $start, Carbon $end): Collection
    {
        $calculatedData = collect();

        // Get already imported fee IDs to skip them in calculations
        $importedFeeIds = IncentiveLine::pluck('fee_id')->filter()->toArray();

        // We need to group by assistant to calculate the additional_percentage bonus
        $assistantMatterCounts = [];

        foreach ($matters as $matter) {
            $assistants = $matter->assistantsOnly;
            foreach ($assistants as $assistant) {
                $assistantId = $assistant->party_id;
                $assistantMatterCounts[$assistantId] = ($assistantMatterCounts[$assistantId] ?? 0) + 1;
            }
        }

        foreach ($matters as $matter) {
            // Get a primary fee ID to satisfy DB constraint if needed
            // We MUST ensure this primary fee hasn't been imported yet
            $primaryFee = $matter->fees()
                ->where(fn ($q) => $q->whereNull('type')->orWhere('type', '!=', FeeType::VAT))
                ->whereNotIn('id', $importedFeeIds)
                ->first();

            if (! $primaryFee) {
                continue; // Should have been filtered by getQualifyingMatters, but safety first
            }

            // Fees without VAT
            $feesAmount = $matter->fees()
                ->where(fn ($q) => $q->whereNull('type')->orWhere('type', '!=', FeeType::VAT))
                ->sum('amount');

            // Court penalties - Sum the absolute values to deduct later
            $courtPenalties = abs($matter->fees()
                ->where('type', FeeType::COURT_PENALITY)
                ->sum('amount'));

            // Office share - Sum the absolute values to deduct
            $officeShare = abs($matter->fees()
                ->where('type', FeeType::OFFICE_SHARE)
                ->sum('amount'));

            // External commission based on matter parties commission_percentage
            $externalCommissionPct = $matter->matterParties()->sum('commission_percentage');

            // Collected fees during this specific period
            $collectedFeesAmount = $matter->allocations()
                ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                ->sum('amount');

            // Deduction amount: penalties + commission on collected fees
            $commissionDeduction = ($collectedFeesAmount * $externalCommissionPct) / 100;

            // Base amount for incentive = collected - (penalties + commission + office_share)
            // Note: usually penalties and office share are deducted once, but here we apply it to the period's collected amount.
            // If the user wants to deduct penalties from the final incentive, it might be different.
            // The prompt says "deduct if there a court pelanity or there is and external commission".
            // And now "exclude office share also from fees".
            $netCollectedAmount = max(0, $collectedFeesAmount - $courtPenalties - $officeShare - $commissionDeduction);

            // Standard baseline incentive percentage
            $basePercentage = $this->calculateBasePercentage($matter);

            // Calculate additional percentage based on assistant's matter count
            // Note: In a real scenario, this might be more complex if multiple assistants are on one matter.
            // For now, we'll take the max bonus among assistants on this matter or average?
            // The requirement says "assigned to the employee/team".
            $additionalPercentage = 0;
            $assistants = $matter->assistantsOnly;
            if ($assistants->isNotEmpty()) {
                $maxBonus = 0;
                foreach ($assistants as $assistant) {
                    $count = $assistantMatterCounts[$assistant->party_id] ?? 0;
                    $bonus = IncentiveExtraRule::getPercentageForCount($count);
                    if ($bonus > $maxBonus) {
                        $maxBonus = $bonus;
                    }
                }
                $additionalPercentage = $maxBonus;
            }

            $totalPercentage = $basePercentage + $additionalPercentage;

            // Total incentive = net_collected_amount * total_percentage / 100
            $totalIncentive = ($netCollectedAmount * $totalPercentage) / 100;

            $calculatedData->push([
                'matter_id' => $matter->id,
                'fee_id' => $primaryFee?->id, // Use the primary fee ID found above
                'reference' => $matter->reference,
                'court_name' => $matter->court?->name,
                'assistant_names' => $matter->assistantsOnly->map(fn ($a) => $a->party?->name)->filter()->implode(', '),
                'expert_name' => $matter->expertsOnly->first()?->party?->name,
                'distributed_at' => $matter->distributed_at,
                'final_report_at' => $matter->final_report_at,
                'fees_amount' => $feesAmount,
                'collected_fees_amount' => $collectedFeesAmount,
                'net_collected_amount' => $netCollectedAmount,
                'court_penalties' => $courtPenalties,
                'external_commission_pct' => $externalCommissionPct,
                'calculation_percent' => $basePercentage,
                'additional_percentage' => $additionalPercentage,
                'total_incentive' => $totalIncentive,
            ]);
        }

        return $calculatedData;
    }

    public function importSelectedMatters(Model $calculation, array $matterIds): void
    {
        if (! $calculation->isDraft()) {
            throw new \Exception('Cannot import matters into a finalized calculation.');
        }

        DB::transaction(function () use ($calculation, $matterIds) {
            foreach ($matterIds as $matterId) {
                $matter = Matter::with('type')->find($matterId);
                if (! $matter) {
                    continue;
                }

                $feesQuery = $matter->fees()->where(fn ($q) => $q->whereNull('type')->orWhere('type', '!=', FeeType::VAT));

                // Still-ongoing matters (allow_current_status_import, not yet
                // finally reported) import fee-by-fee, scoped to fees
                // REGISTERED (fees.date, not collection/allocation date)
                // within this calculation's period — one line per qualifying
                // fee. A fee registered in a later period is left for that
                // period's own calculation to pick up.
                $isCurrentStatusMatter = ($matter->type?->allow_current_status_import ?? false)
                    && ! $matter->final_report_at;

                if ($isCurrentStatusMatter) {
                    $feesQuery->whereBetween('date', [
                        Carbon::parse($calculation->period_start)->toDateString(),
                        Carbon::parse($calculation->period_end)->toDateString(),
                    ]);
                }

                $fees = $feesQuery->get();

                if ($fees->isEmpty()) {
                    // A finished matter (final_report_date trigger) with no
                    // fees at all is still imported — as a single fee-less
                    // line — purely so it counts toward the monthly
                    // achievement quota. Fee-driven trigger types have
                    // nothing to import without a fee.
                    $isFinishedWithNoFee = $matter->type?->incentive_trigger_type === 'final_report_date'
                        && $matter->final_report_at !== null;

                    if ($isFinishedWithNoFee && ! $matter->incentiveLines()->whereNull('fee_id')->exists()) {
                        IncentiveLine::create([
                            'incentive_calculation_id' => $calculation->id,
                            'matter_id' => $matterId,
                            'fee_id' => null,
                            'fee_amount_excl_vat' => 0,
                            'base_percentage' => 0,
                            'effective_percentage' => 0,
                            'base_amount' => 0,
                            'net_amount' => 0,
                        ]);
                    } else {
                        Log::warning("Skipping matter {$matterId}: no valid non-VAT fee found.");
                    }

                    continue;
                }

                foreach ($fees as $fee) {
                    $exists = IncentiveLine::where('fee_id', $fee->id)->exists();
                    if ($exists) {
                        Log::info("Skipping duplicate incentive line for fee_id {$fee->id}.");

                        continue;
                    }

                    IncentiveLine::create([
                        'incentive_calculation_id' => $calculation->id,
                        'matter_id' => $matterId,
                        'fee_id' => $fee->id,
                        'fee_amount_excl_vat' => 0,
                        'base_percentage' => 0,
                        'effective_percentage' => 0,
                        'base_amount' => 0,
                        'net_amount' => 0,
                    ]);
                }
            }
        });
    }

    public function calculateBasePercentage(Matter $matter): float
    {
        $config = $matter->type?->incentiveConfig;
        if (! $config) {
            return 0;
        }

        $percentage = 0;
        $difficulty = $matter->difficulty ?? 'medium';

        if ($config->calculation_type === 'fixed') {
            $percentage = (float) $config->fixed_percentage;
        } else {
            // For tiered/committee, we calculate completion days
            $receivedAt = $matter->received_at;
            $initialReportAt = $matter->initial_report_at;

            if ($receivedAt instanceof Carbon && $initialReportAt instanceof Carbon) {
                // Use common logic from IncentiveCalculatorService
                $calculator = app(IncentiveCalculatorService::class);
                $days = $calculator->workingDaysBetween($receivedAt, $initialReportAt);

                $tier = $config->tiers()
                    ->where('difficulty', $difficulty)
                    ->where('days_from', '<=', $days)
                    ->where(function ($q) use ($days) {
                        $q->where('days_to', '>=', $days)
                            ->orWhereNull('days_to');
                    })
                    ->first();

                $percentage = (float) ($tier?->percentage ?? 0);

                if ($config->calculation_type === 'committee') {
                    $committeeAdj = ($matter->commissioning === 'individual') ? 2.0 : -2.0;
                    $percentage = max(0.0, $percentage + $committeeAdj);
                }
            }
        }

        // Custom Field (MatterMeta) Percentage Adjustment
        $calculator = app(IncentiveCalculatorService::class);
        $totalAdjustment = $calculator->calculateMetaAdjustment($matter);

        return $percentage + $totalAdjustment;
    }
}
