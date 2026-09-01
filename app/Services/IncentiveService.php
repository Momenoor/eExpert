<?php

namespace App\Services;

use App\Enums\FeeType;
use App\Models\Fee;
use App\Models\IncentiveLine;
use App\Models\Matter;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
                    ->where(fn ($q2) => $q2->whereNull('type')->orWhereNotIn('type', FeeType::excludedFromIncentiveValues()));
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
                    ->where(fn ($q2) => $q2->whereNull('type')->orWhereNotIn('type', FeeType::excludedFromIncentiveValues()));
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
                ->where(fn ($q) => $q->whereNull('type')->orWhereNotIn('type', FeeType::excludedFromIncentiveValues()))
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

        foreach ($matters as $matter) {
            // Get a primary fee ID to satisfy DB constraint if needed
            // We MUST ensure this primary fee hasn't been imported yet
            $primaryFee = $matter->fees()
                ->where(fn ($q) => $q->whereNull('type')->orWhereNotIn('type', FeeType::excludedFromIncentiveValues()))
                ->whereNotIn('id', $importedFeeIds)
                ->first();

            if (! $primaryFee) {
                continue; // Should have been filtered by getQualifyingMatters, but safety first
            }

            // Revenue fees only (VAT and deduction-type fees excluded)
            $feesAmount = $matter->fees()
                ->where(fn ($q) => $q->whereNull('type')->orWhereNotIn('type', FeeType::excludedFromIncentiveValues()))
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

            // No percentage/incentive estimate is produced here on purpose.
            //
            // This method only feeds the "Import Qualifying Matters" preview,
            // which displays reference, court, assistants, fees, collected and
            // net basis — nothing else. It used to also call a second,
            // divergent base-percentage implementation that measured from
            // received_at; migration 2026_03_27_092937 renamed the original
            // column to distributed_at and created a NEW, always-null
            // received_at, so that path returned 0% for every matter and the
            // preview's figures never matched what IncentiveCalculatorService
            // actually produces on import. IncentiveCalculatorService is the
            // single source of truth for percentages.
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

                $this->importMatterFees($calculation, $matter, $this->eligibleFeesQuery($matter, $calculation));
            }
        });
    }

    /**
     * Force-import one specific matter into a calculation, bypassing every
     * normal qualification check (period, trigger date, current-status
     * allocation-period scoping) — every eligible non-VAT fee not already
     * attached to ANY existing incentive line (in this or any other
     * calculation, draft or finalized) is imported as its own line. For
     * manual corrections/exceptions the automatic import doesn't cover.
     *
     * @return int Number of fee lines actually imported (0 if every eligible
     *             fee on this matter was already imported elsewhere).
     */
    public function forceImportMatter(Model $calculation, int $matterId): int
    {
        if (! $calculation->isDraft()) {
            throw new \Exception('Cannot import matters into a finalized calculation.');
        }

        $matter = Matter::with('type')->find($matterId);
        if (! $matter) {
            throw new \Exception("Matter #{$matterId} not found.");
        }

        return DB::transaction(function () use ($calculation, $matter) {
            $feesQuery = $matter->fees()->where(fn ($q) => $q->whereNull('type')->orWhereNotIn('type', FeeType::excludedFromIncentiveValues()));

            return $this->importMatterFees($calculation, $matter, $feesQuery);
        });
    }

    /**
     * Import any newly eligible fees for matters that already have at least
     * one line in this calculation — so a fee registered on a matter AFTER
     * it was first imported (e.g. a late-arriving payment) is picked up on
     * the next recalculation instead of requiring a manual "Add Specific
     * Matter" force-import. Uses eligibleFeesQuery() so the period scoping
     * matches each matter's own type exactly as the initial import would
     * have applied it: still-ongoing (current-status) matters only pick up
     * fees dated within this calculation's own period, while finished
     * (final_report_date-triggered) matters pick up any new eligible fee
     * regardless of date, same as on first import. Matters with no lines yet
     * in this calculation are untouched; only fees not already attached to
     * ANY existing incentive line (this or another calculation) are
     * imported.
     *
     * @return int Number of new fee lines imported.
     */
    public function syncNewFeesForCalculation(Model $calculation): int
    {
        $matterIds = IncentiveLine::where('incentive_calculation_id', $calculation->id)
            ->whereNotNull('matter_id')
            ->distinct()
            ->pluck('matter_id');

        $imported = 0;

        foreach ($matterIds as $matterId) {
            $matter = Matter::with('type')->find($matterId);
            if (! $matter) {
                continue;
            }

            $imported += $this->importMatterFees($calculation, $matter, $this->eligibleFeesQuery($matter, $calculation));
        }

        return $imported;
    }

    /**
     * Non-VAT fees query for a matter, scoped per the matter's OWN type:
     * still-ongoing matters (allow_current_status_import, not yet finally
     * reported) are imported fee-by-fee, scoped to fees REGISTERED
     * (fees.date, not collection/allocation date) within this calculation's
     * period — a fee registered in a later period is left for that period's
     * own calculation to pick up. Finished matters (final_report_date
     * trigger) have no such scoping: every eligible fee on the matter
     * belongs to it regardless of date. Shared by importSelectedMatters()
     * and syncNewFeesForCalculation() so both apply identical rules.
     *
     * @return HasMany<Fee, Matter>
     */
    private function eligibleFeesQuery(Matter $matter, Model $calculation): HasMany
    {
        $feesQuery = $matter->fees()->where(fn ($q) => $q->whereNull('type')->orWhereNotIn('type', FeeType::excludedFromIncentiveValues()));

        $isCurrentStatusMatter = ($matter->type?->allow_current_status_import ?? false)
            && ! $matter->final_report_at;

        if ($isCurrentStatusMatter) {
            $feesQuery->whereBetween('date', [
                Carbon::parse($calculation->period_start)->toDateString(),
                Carbon::parse($calculation->period_end)->toDateString(),
            ]);
        }

        return $feesQuery;
    }

    /**
     * Shared per-matter fee import: creates one IncentiveLine per fee
     * matched by $feesQuery that isn't already attached to an incentive
     * line elsewhere, falling back to a single fee-less line (counted
     * toward the monthly quota only) when a finished matter has no
     * eligible fees at all.
     *
     * @return int Number of lines created.
     */
    private function importMatterFees(Model $calculation, Matter $matter, $feesQuery): int
    {
        $fees = $feesQuery->get();
        $imported = 0;

        if ($fees->isEmpty()) {
            // A finished matter (final_report_date trigger) with no fees at
            // all is still imported — as a single fee-less line — purely so
            // it counts toward the monthly achievement quota. Fee-driven
            // trigger types have nothing to import without a fee.
            $isFinishedWithNoFee = $matter->type?->incentive_trigger_type === 'final_report_date'
                && $matter->final_report_at !== null;

            if ($isFinishedWithNoFee && ! $matter->incentiveLines()->whereNull('fee_id')->exists()) {
                IncentiveLine::create([
                    'incentive_calculation_id' => $calculation->id,
                    'matter_id' => $matter->id,
                    'fee_id' => null,
                    'fee_amount_excl_vat' => 0,
                    'base_percentage' => 0,
                    'effective_percentage' => 0,
                    'base_amount' => 0,
                    'net_amount' => 0,
                ]);
                $imported++;
            } else {
                Log::warning("Skipping matter {$matter->id}: no valid non-VAT fee found.");
            }

            return $imported;
        }

        foreach ($fees as $fee) {
            if (IncentiveLine::where('fee_id', $fee->id)->exists()) {
                Log::info("Skipping duplicate incentive line for fee_id {$fee->id}.");

                continue;
            }

            IncentiveLine::create([
                'incentive_calculation_id' => $calculation->id,
                'matter_id' => $matter->id,
                'fee_id' => $fee->id,
                'fee_amount_excl_vat' => 0,
                'base_percentage' => 0,
                'effective_percentage' => 0,
                'base_amount' => 0,
                'net_amount' => 0,
            ]);
            $imported++;
        }

        return $imported;
    }
}
