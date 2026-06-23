<?php

namespace App\Services;

use App\Enums\FeeType;
use App\Models\IncentiveExtraRule;
use App\Models\IncentiveLine;
use App\Models\IncentiveMetaAdjustment;
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
        // Get already imported matter/fee IDs from ANY finalized or existing calculation
        $importedFeeIds = IncentiveLine::pluck('fee_id')->filter()->toArray();

        // Condition A: final_report_date trigger
        $reportDateQuery = Matter::query()
            ->whereHas('type', function ($q) {
                $q->where('incentive_trigger_type', 'final_report_date');
            })
            ->whereBetween('final_report_at', [$start->startOfDay(), $end->endOfDay()]);

        // Condition B: fees_collected_date trigger
        $feeCollectedQuery = Matter::query()
            ->whereHas('type', function ($q) {
                $q->where('incentive_trigger_type', 'fees_collected_date');
            })
            ->whereHas('fees', function ($q) use ($start, $end) {
                $q->whereBetween('date', [$start->toDateString(), $end->toDateString()]);
            });

        // Condition C: allow_current_status_import
        $currentStatusQuery = Matter::query()
            ->whereHas('type', function ($q) {
                $q->where('allow_current_status_import', true);
            })
            // Matter status is current (not final reported)
            // Assuming 'status' is an attribute that can be queried or we use the underlying logic
            // From getQualifyingMatters Condition A, final_report_at determines if it's reported.
            ->whereNull('final_report_at')
            ->whereHas('fees', function ($q) use ($start, $end) {
                $q->whereBetween('date', [$start->toDateString(), $end->toDateString()]);
            })
            // Matter has no existing IncentiveLine record for any of its eligible fees
            ->whereDoesntHave('incentiveLines');

        if (! empty($filters['expert_ids'])) {
            $reportDateQuery->whereHas('expertsOnly', function ($q) use ($filters) {
                $q->whereIn('party_id', $filters['expert_ids']);
            });
            $feeCollectedQuery->whereHas('expertsOnly', function ($q) use ($filters) {
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
            $feeCollectedQuery->whereHas('assistantsOnly', function ($q) use ($filters) {
                $q->whereIn('party_id', $filters['assistant_ids']);
            });
            $currentStatusQuery->whereHas('assistantsOnly', function ($q) use ($filters) {
                $q->whereIn('party_id', $filters['assistant_ids']);
            });
        }

        $matters = $reportDateQuery->get()
            ->concat($feeCollectedQuery->get())
            ->concat($currentStatusQuery->get())
            ->unique('id');

        // Filter out matters where ALL eligible fees are already imported
        return $matters->values()->filter(function (Matter $matter) use ($importedFeeIds) {
            $eligibleFees = $matter->fees()
                ->where('type', '!=', FeeType::VAT)
                ->where('type', '!=', FeeType::COURT_PENALITY)
                ->where('type', '!=', FeeType::OFFICE_SHARE)
                ->pluck('id')
                ->toArray();

            if (empty($eligibleFees)) {
                return false;
            }

            // Check if there are allocations for these eligible fees within the period
            // This is important because a matter might have multiple fees, but only some were paid in this period.
            // If ALL fees that have allocations in this period are already imported, we should hide the matter.
            // But wait, the trigger might be report date, not allocations.

            // Let's stick to: If at least one eligible fee is NOT imported, show it.
            // To be more precise, we should only consider fees that actually contribute to the incentive.
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
                ->where('type', '!=', FeeType::VAT)
                ->whereNotIn('id', $importedFeeIds)
                ->first();

            if (! $primaryFee) {
                continue; // Should have been filtered by getQualifyingMatters, but safety first
            }

            // Fees without VAT
            $feesAmount = $matter->fees()
                ->where('type', '!=', FeeType::VAT)
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
                $matter = Matter::find($matterId);
                if (! $matter) {
                    continue;
                }

                $feeId = $matter->fees()->where('type', '!=', FeeType::VAT)->first()?->id;

                if (empty($feeId)) {
                    Log::warning("Skipping incentive line for matter {$matterId} because no valid fee_id was found.");

                    continue;
                }

                // Double check for duplicates before inserting
                $exists = IncentiveLine::where('fee_id', $feeId)->exists();
                if ($exists) {
                    Log::info("Skipping duplicate incentive line for fee_id {$feeId}.");

                    continue;
                }

                IncentiveLine::create([
                    'incentive_calculation_id' => $calculation->id,
                    'matter_id' => $matterId,
                    'fee_id' => $feeId,
                    'fee_amount_excl_vat' => 0,
                    'base_percentage' => 0,
                    'effective_percentage' => 0,
                    'base_amount' => 0,
                    'net_amount' => 0,
                ]);
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

        if ($config->calculation_type === 'fixed') {
            $percentage = (float) $config->fixed_percentage;
        } elseif ($matter->distributed_at instanceof Carbon && $matter->initial_report_at instanceof Carbon) {
            // For tiered/committee, we calculate completion days
            // Simplified working days calculation (should ideally exclude weekends/holidays)
            $days = $matter->distributed_at->diffInDaysFiltered(function (Carbon $date) {
                return ! $date->isWeekend();
            }, $matter->initial_report_at);

            $tier = $config->tiers()
                ->where('difficulty', $matter->difficulty)
                ->where('days_from', '<=', $days)
                ->where(function ($q) use ($days) {
                    $q->where('days_to', '>=', $days)
                        ->orWhereNull('days_to');
                })
                ->first();

            $percentage = (float) ($tier?->percentage ?? 0);
        }

        // Custom Field (MatterMeta) Percentage Adjustment
        $adjustments = IncentiveMetaAdjustment::all();
        $matterMetas = $matter->metas;
        $totalAdjustment = 0;

        foreach ($adjustments as $adjustment) {
            $match = $matterMetas->first(function ($meta) use ($adjustment) {
                return $meta->field_name === $adjustment->field_name &&
                    (is_null($adjustment->field_value) || $meta->field_value === $adjustment->field_value);
            });

            if ($match) {
                $totalAdjustment += $adjustment->percentage_adjustment;
            }
        }

        return $percentage + $totalAdjustment;
    }
}
