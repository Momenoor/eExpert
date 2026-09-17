<?php

namespace App\Services;

use App\Enums\PMS\AttestationFeePayer;
use App\Enums\PMS\AttestationStatus;
use App\Enums\PMS\ContractDisputeStatus;
use App\Enums\PMS\ContractPartyRole;
use App\Enums\PMS\ContractStatus;
use App\Enums\PMS\UnitStatus;
use App\Models\Contract;
use App\Models\ContractParty;
use App\Models\Quotation;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Drafting a contract — from an accepted quotation, or from raw inputs — and
 * attaching its tenants and units. Kept out of the Filament layer entirely,
 * the same way `QuotationService`/`LeaveRequestService` keep the actual
 * decision logic out of their own Filament actions.
 */
class ContractService
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
    public function createFromQuotation(Quotation $quotation, array $overrides = []): Contract
    {
        if (! $quotation->isAccepted()) {
            throw new RuntimeException('Only an accepted quotation can become a contract.');
        }

        $tenants = $overrides['tenants'] ?? [[
            'party_id' => $quotation->getAttribute('party_id'),
            'role' => ContractPartyRole::PRIMARY_TENANT->value,
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
    public function createFromRawInputs(array $data, array $tenants, array $unitIds): Contract
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
    private function draft(array $data, array $tenants, array $unitIds): Contract
    {
        if ($tenants === []) {
            throw new RuntimeException('A contract must have at least one tenant.');
        }

        if ($unitIds === []) {
            throw new RuntimeException('A contract must cover at least one unit.');
        }

        return DB::transaction(function () use ($data, $tenants, $unitIds): Contract {
            $contract = Contract::create([
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
                'status' => ContractStatus::PENDING_ATTESTATION,
                'attestation_status' => AttestationStatus::UNREGISTERED,
                'attestation_fee_payer' => AttestationFeePayer::TENANT,
                'dispute_status' => ContractDisputeStatus::NONE,
            ]);

            foreach ($tenants as $tenant) {
                ContractParty::create([
                    'contract_id' => $contract->getKey(),
                    'party_id' => $tenant['party_id'],
                    'role' => $tenant['role'] ?? ContractPartyRole::PRIMARY_TENANT->value,
                    'parent_id' => $tenant['parent_id'] ?? null,
                ]);
            }

            $contract->units()->sync($unitIds);

            // A unit under a fresh contract is no longer available to offer
            // elsewhere — the "Vacant Units" figure the dashboard shows
            // would otherwise keep counting it long after it was let.
            Unit::whereIn('id', $unitIds)->update(['status' => UnitStatus::OCCUPIED->value]);

            return $contract->load(['contractParties.party', 'units']);
        });
    }

    public function attest(Contract $contract, array $data): Contract
    {
        $contract->forceFill([
            'attestation_system' => $data['attestation_system'],
            'attestation_serial_number' => $data['attestation_serial_number'],
            'title_deed_number' => $data['title_deed_number'] ?? null,
            'attestation_status' => AttestationStatus::REGISTERED->value,
            'status' => ContractStatus::ACTIVE,
        ])->save();

        return $contract;
    }

    public function terminate(Contract $contract): Contract
    {
        if (in_array($contract->getAttribute('status'), [ContractStatus::TERMINATED, ContractStatus::EXPIRED], true)) {
            throw new RuntimeException('This contract has already ended.');
        }

        return DB::transaction(function () use ($contract): Contract {
            $contract->forceFill(['status' => ContractStatus::TERMINATED])->save();

            // Released back onto the market — a unit only stays OCCUPIED
            // because of the contract that just ended.
            Unit::whereIn('id', $contract->units()->pluck('units.id'))
                ->update(['status' => UnitStatus::VACANT->value]);

            return $contract;
        });
    }
}
