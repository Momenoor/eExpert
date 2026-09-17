<?php

namespace App\Filament\Pms\Resources\Leases\Pages;

use App\Filament\Pms\Resources\Leases\LeaseResource;
use App\Models\Lease;
use App\Services\PMS\LeaseService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateLease extends CreateRecord
{
    protected static string $resource = LeaseResource::class;

    /**
     * Routed through the service — tenants and units are attached as
     * `lease_party`/`lease_unit` rows there, never as raw form data
     * saved straight onto the `leases` table.
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(LeaseService::class)->createFromRawInputs([
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'grace_period_days' => $data['grace_period_days'] ?? 0,
            'total_base_rent' => $data['total_base_rent'],
            'security_deposit_amount' => $data['security_deposit_amount'] ?? 0,
        ], $data['tenants'], $data['units']);
    }

    protected function getRedirectUrl(): string
    {
        /** @var Lease $lease */
        $lease = $this->getRecord();

        return static::getResource()::getUrl('view', ['record' => $lease]);
    }
}
