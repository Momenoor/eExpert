<?php

namespace App\Filament\Pms\Resources\Tenants\Pages;

use App\Filament\Pms\Resources\Tenants\TenantResource;
use App\Models\Party;
use App\Models\Tenant;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    /**
     * `name`/`phone`/`email` belong to a new `Party` (tagged 'tenant'), not
     * to `Tenant` itself — created together in one transaction so a
     * failure partway through never leaves an orphaned Party with no profile.
     */
    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data): Tenant {
            $party = Party::create([
                'name' => $data['name'],
                'phone' => $data['phone'] ?? [],
                'email' => $data['email'] ?? [],
                'role' => ['role' => ['tenant'], 'type' => []],
            ]);

            return Tenant::create([
                'party_id' => $party->getKey(),
                'tenant_type' => $data['tenant_type'],
                'identification_type' => $data['identification_type'],
                'identification_number' => $data['identification_number'],
                'trn' => $data['trn'] ?? null,
                'emergency_contact_name' => $data['emergency_contact_name'] ?? null,
                'emergency_contact_phone' => $data['emergency_contact_phone'] ?? null,
            ]);
        });
    }
}
