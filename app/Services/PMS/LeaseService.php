<?php

namespace App\Services\PMS;

use App\Enums\PMS\AttestationFeePayer;
use App\Enums\PMS\AttestationStatus;
use App\Enums\PMS\LeaseDisputeStatus;
use App\Enums\PMS\LeasePartyRole;
use App\Enums\PMS\LeaseStatus;
use App\Enums\PMS\UnitStatus;
use App\Models\Lease;
use App\Models\LeaseParty;
use App\Models\Quotation;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Drafting a lease — from an accepted quotation, or from raw inputs — and
 * attaching its tenants and units. Kept out of the Filament layer entirely,
 * the same way `QuotationService`/`LeaveRequestService` keep the actual
 * decision logic out of their own Filament actions.
 */
class LeaseService
{
    /**
     * @param  array{
     *     tenants?: list<array{party_id: int, role?: string}>,
     *     units?: list<int>,
     *     start_date?: string,
     *     end_date?: string,
     *     grace_period_days?: int,
     * }  $overrides
     */
    public function createFromQuotation(Quotation $quotation, array $overrides = []): Lease
    {
        if (! $quotation->isAccepted()) {
            throw new RuntimeException('Only an accepted quotation can become a lease.');
        }

        $tenants = $overrides['tenants'] ?? [[
            'party_id' => $quotation->getAttribute('party_id'),
            'role' => LeasePartyRole::PRIMARY_TENANT->value,
        ]];
        $unitIds = $overrides['units'] ?? $quotation->units()->pluck('units.id')->all();

        return $this->draft([
            'quotation_id' => $quotation->getKey(),
            'start_date' => $overrides['start_date'] ?? now()->toDateString(),
            'end_date' => $overrides['end_date'] ?? now()->addYear()->toDateString(),
            'grace_period_days' => $overrides['grace_period_days'] ?? 0,
            'total_base_rent' => (float) $quotation->getAttribute('base_rent'),
            'security_deposit_amount' => (float) $quotation->getAttribute('security_deposit'),
        ], $tenants, $unitIds);
    }

    /**
     * @param  list<array{party_id: int, role?: string}>  $tenants
     * @param  list<int>  $unitIds
     */
    public function createFromRawInputs(array $data, array $tenants, array $unitIds): Lease
    {
        return $this->draft($data, $tenants, $unitIds);
    }

    /**
     * @param  array{
     *     quotation_id?: int|null,
     *     start_date: string,
     *     end_date: string,
     *     grace_period_days?: int,
     *     total_base_rent: float|string,
     *     security_deposit_amount?: float|string,
     * }  $data
     * @param  list<array{party_id: int, role?: string}>  $tenants
     * @param  list<int>  $unitIds
     */
    private function draft(array $data, array $tenants, array $unitIds): Lease
    {
        if ($tenants === []) {
            throw new RuntimeException('A lease must have at least one tenant.');
        }

        if ($unitIds === []) {
            throw new RuntimeException('A lease must cover at least one unit.');
        }

        return DB::transaction(function () use ($data, $tenants, $unitIds): Lease {
            $lease = Lease::create([
                'quotation_id' => $data['quotation_id'] ?? null,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'grace_period_days' => $data['grace_period_days'] ?? 0,
                'total_base_rent' => $data['total_base_rent'],
                'security_deposit_amount' => $data['security_deposit_amount'] ?? 0,
                // Attestation happens after the office has the paperwork —
                // never assumed as part of drafting. Set explicitly (rather
                // than left to the column default) so the in-memory model
                // returned here already reflects it, with no extra refresh.
                'status' => LeaseStatus::PENDING_ATTESTATION,
                'attestation_status' => AttestationStatus::UNREGISTERED,
                'attestation_fee_payer' => AttestationFeePayer::TENANT,
                'dispute_status' => LeaseDisputeStatus::NONE,
            ]);

            foreach ($tenants as $tenant) {
                LeaseParty::create([
                    'lease_id' => $lease->getKey(),
                    'party_id' => $tenant['party_id'],
                    'role' => $tenant['role'] ?? LeasePartyRole::PRIMARY_TENANT->value,
                    'parent_id' => $tenant['parent_id'] ?? null,
                ]);
            }

            $lease->units()->sync($unitIds);

            // A unit under a fresh lease is no longer available to offer
            // elsewhere — the "Vacant Units" figure the dashboard shows
            // would otherwise keep counting it long after it was let.
            Unit::whereIn('id', $unitIds)->update(['status' => UnitStatus::OCCUPIED->value]);

            return $lease->load(['leaseParties.party', 'units']);
        });
    }

    public function attest(Lease $lease, array $data): Lease
    {
        $lease->forceFill([
            'attestation_system' => $data['attestation_system'],
            'attestation_serial_number' => $data['attestation_serial_number'],
            'title_deed_number' => $data['title_deed_number'] ?? null,
            'attestation_status' => AttestationStatus::REGISTERED->value,
            'status' => LeaseStatus::ACTIVE,
        ])->save();

        return $lease;
    }

    public function terminate(Lease $lease): Lease
    {
        if (in_array($lease->getAttribute('status'), [LeaseStatus::TERMINATED, LeaseStatus::EXPIRED], true)) {
            throw new RuntimeException('This lease has already ended.');
        }

        return DB::transaction(function () use ($lease): Lease {
            $lease->forceFill(['status' => LeaseStatus::TERMINATED])->save();

            // Released back onto the market — a unit only stays OCCUPIED
            // because of the lease that just ended.
            Unit::whereIn('id', $lease->units()->pluck('units.id'))
                ->update(['status' => UnitStatus::VACANT->value]);

            return $lease;
        });
    }
}
