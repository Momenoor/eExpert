<?php

namespace App\Services\PMS;

use App\Enums\PMS\AttestationFeePayer;
use App\Enums\PMS\AttestationStatus;
use App\Enums\PMS\ContractCategory;
use App\Enums\PMS\ContractType;
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
     * Optional lease-detail fields carried straight through from `$data`
     * into `Lease::create()` under the same key — government/attestation
     * paperwork fields that have no default worth computing.
     */
    private const PASSTHROUGH_FIELDS = [
        'government_contract_number',
        'issue_date',
        'contract_type',
        'multiple_rent_amount',
        'payment_method',
        'number_of_payments',
        'allow_multiple_licenses',
        'number_of_occupants',
        'condition_template_id',
        'poa_authority_number',
        'poa_identification_number',
        'poa_unified_number',
        'poa_name',
    ];

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
            'end_date' => $overrides['end_date'] ?? Lease::fullYearEnd($overrides['start_date'] ?? now())->toDateString(),
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
     * Renews an active (or expired, not yet re-let) lease: a new lease
     * record covering the same tenants and units, marked `RENEWAL`, linked
     * back to the original — which itself moves to `RENEWED` so it stops
     * appearing as the unit's current occupancy record.
     *
     * @param  array{start_date?: string, end_date?: string, total_base_rent?: float|string}  $overrides
     */
    public function renew(Lease $lease, array $overrides = []): Lease
    {
        if (! in_array($lease->getAttribute('status'), [LeaseStatus::ACTIVE, LeaseStatus::EXPIRED], true)) {
            throw new RuntimeException('Only an active or expired lease can be renewed.');
        }

        $tenants = $lease->leaseParties->map(fn (LeaseParty $leaseParty): array => [
            'party_id' => $leaseParty->getAttribute('party_id'),
            'role' => $leaseParty->getAttribute('role')->value,
        ])->all();
        $unitIds = $lease->units()->pluck('units.id')->all();

        return DB::transaction(function () use ($lease, $overrides, $tenants, $unitIds): Lease {
            $renewal = $this->draft([
                'renewed_from_lease_id' => $lease->getKey(),
                'start_date' => $overrides['start_date'] ?? $lease->getAttribute('end_date')->addDay()->toDateString(),
                'end_date' => $overrides['end_date'] ?? $lease->getAttribute('end_date')->addYear()->toDateString(),
                'total_base_rent' => $overrides['total_base_rent'] ?? $lease->getAttribute('total_base_rent'),
                'security_deposit_amount' => $lease->getAttribute('security_deposit_amount'),
            ], $tenants, $unitIds, ContractCategory::RENEWAL);

            $lease->forceFill(['status' => LeaseStatus::RENEWED])->save();

            return $renewal;
        });
    }

    /**
     * @param  array{
     *     quotation_id?: int|null,
     *     renewed_from_lease_id?: int|null,
     *     start_date: string,
     *     end_date: string,
     *     grace_period_days?: int,
     *     total_base_rent: float|string,
     *     security_deposit_amount?: float|string,
     * }  $data
     * @param  list<array{party_id: int, role?: string}>  $tenants
     * @param  list<int>  $unitIds
     */
    private function draft(array $data, array $tenants, array $unitIds, ContractCategory $category = ContractCategory::NEW): Lease
    {
        if ($tenants === []) {
            throw new RuntimeException('A lease must have at least one tenant.');
        }

        if ($unitIds === []) {
            throw new RuntimeException('A lease must cover at least one unit.');
        }

        $this->assertContractTypeAllowed($data['contract_type'] ?? null, $unitIds);

        return DB::transaction(function () use ($data, $tenants, $unitIds, $category): Lease {
            $lease = Lease::create(array_merge(
                array_intersect_key($data, array_flip(self::PASSTHROUGH_FIELDS)),
                [
                    'quotation_id' => $data['quotation_id'] ?? null,
                    'renewed_from_lease_id' => $data['renewed_from_lease_id'] ?? null,
                    'start_date' => $data['start_date'],
                    'end_date' => $data['end_date'],
                    'contract_category' => $category,
                    'annual_rent' => Lease::annualRentFor($data['start_date'], $data['end_date'], $data['total_base_rent']),
                    'grace_period_days' => $data['grace_period_days'] ?? 0,
                    'total_base_rent' => $data['total_base_rent'],
                    'security_deposit_amount' => $data['security_deposit_amount'] ?? 0,
                    // A freshly drafted lease is still correctable — it only
                    // moves to Pending Attestation once the office explicitly
                    // submits it via `submitForAttestation()`.
                    'status' => LeaseStatus::DRAFT,
                    'attestation_status' => AttestationStatus::UNREGISTERED,
                    'attestation_fee_payer' => AttestationFeePayer::TENANT,
                    'dispute_status' => LeaseDisputeStatus::NONE,
                ],
            ));

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

    /**
     * The office is done correcting the draft and hands it off for
     * attestation — the point past which `Lease::isEditable()` stops
     * allowing changes.
     */
    public function submitForAttestation(Lease $lease): Lease
    {
        if ($lease->getAttribute('status') !== LeaseStatus::DRAFT) {
            throw new RuntimeException('Only a draft lease can be submitted for attestation.');
        }

        $lease->forceFill(['status' => LeaseStatus::PENDING_ATTESTATION])->save();

        return $lease;
    }

    /**
     * Corrects a draft lease's own fields and its tenants/units — the
     * `EditLease` page's only entry point, since `LeaseResource::canEdit()`
     * already refuses anything past `DRAFT`.
     *
     * @param  list<array{party_id: int, role?: string}>  $tenants
     * @param  list<int>  $unitIds
     */
    public function updateDraft(Lease $lease, array $data, array $tenants, array $unitIds): Lease
    {
        if ($lease->getAttribute('status') !== LeaseStatus::DRAFT) {
            throw new RuntimeException('Only a draft lease can be edited.');
        }

        if ($tenants === []) {
            throw new RuntimeException('A lease must have at least one tenant.');
        }

        if ($unitIds === []) {
            throw new RuntimeException('A lease must cover at least one unit.');
        }

        if (array_key_exists('contract_type', $data)) {
            $this->assertContractTypeAllowed($data['contract_type'], $unitIds);
        }

        return DB::transaction(function () use ($lease, $data, $tenants, $unitIds): Lease {
            $lease->update(array_intersect_key($data, array_flip([
                ...self::PASSTHROUGH_FIELDS,
                'start_date', 'end_date', 'grace_period_days',
                'total_base_rent', 'security_deposit_amount',
            ])));

            $previousUnitIds = $lease->units()->pluck('units.id')->all();
            $lease->units()->sync($unitIds);

            // Units dropped from the lease go back on the market; newly
            // added ones stop being offered — the same bookkeeping `draft()`
            // does when the lease is first created.
            Unit::whereIn('id', array_diff($previousUnitIds, $unitIds))->update(['status' => UnitStatus::VACANT->value]);
            Unit::whereIn('id', array_diff($unitIds, $previousUnitIds))->update(['status' => UnitStatus::OCCUPIED->value]);

            $lease->leaseParties()->delete();

            foreach ($tenants as $tenant) {
                LeaseParty::create([
                    'lease_id' => $lease->getKey(),
                    'party_id' => $tenant['party_id'],
                    'role' => $tenant['role'] ?? LeasePartyRole::PRIMARY_TENANT->value,
                    'parent_id' => $tenant['parent_id'] ?? null,
                ]);
            }

            // Annual rent follows from the (possibly changed) dates and rent
            // — never typed in.
            $lease->refresh();
            $lease->update([
                'annual_rent' => Lease::annualRentFor($lease->getAttribute('start_date'), $lease->getAttribute('end_date'), $lease->getAttribute('total_base_rent')),
            ]);

            return $lease->fresh(['leaseParties.party', 'units']);
        });
    }

    /**
     * The contract type is picked on the lease, but only from the types that
     * fit the classification of the units it covers.
     *
     * @param  list<int>  $unitIds
     */
    private function assertContractTypeAllowed(mixed $contractType, array $unitIds): void
    {
        if (blank($contractType)) {
            return;
        }

        $value = $contractType instanceof ContractType ? $contractType->value : (string) $contractType;
        $allowed = array_map(fn (ContractType $type): string => $type->value, Lease::allowedContractTypes($unitIds));

        if (! in_array($value, $allowed, true)) {
            throw new RuntimeException('That contract type does not fit the selected units.');
        }
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
