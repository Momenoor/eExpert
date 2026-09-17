<?php

namespace App\Filament\Resources\Contracts\Pages;

use App\Filament\Resources\Contracts\ContractResource;
use App\Models\Contract;
use App\Services\ContractService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateContract extends CreateRecord
{
    protected static string $resource = ContractResource::class;

    /**
     * Routed through the service — tenants and units are attached as
     * `contract_party`/`contract_unit` rows there, never as raw form data
     * saved straight onto the `contracts` table.
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(ContractService::class)->createFromRawInputs([
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'grace_period_days' => $data['grace_period_days'] ?? 0,
            'total_base_rent' => $data['total_base_rent'],
            'security_deposit_amount' => $data['security_deposit_amount'] ?? 0,
        ], $data['tenants'], $data['units']);
    }

    protected function getRedirectUrl(): string
    {
        /** @var Contract $contract */
        $contract = $this->getRecord();

        return static::getResource()::getUrl('view', ['record' => $contract]);
    }
}
