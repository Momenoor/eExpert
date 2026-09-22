<?php

namespace App\Filament\Pms\Resources\OwnerProfiles\Pages;

use App\Filament\Pms\Resources\OwnerProfiles\OwnerProfileResource;
use App\Models\OwnerProfile;
use App\Models\Party;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateOwnerProfile extends CreateRecord
{
    protected static string $resource = OwnerProfileResource::class;

    /**
     * `name`/`phone`/`email` belong to a new `Party` (tagged 'owner'), not to
     * `OwnerProfile` itself — created together in one transaction so a
     * failure partway through never leaves an orphaned Party with no profile.
     */
    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data): OwnerProfile {
            $party = Party::create([
                'name' => $data['name'],
                'phone' => $data['phone'] ?? [],
                'email' => $data['email'] ?? [],
                'role' => ['role' => ['owner'], 'type' => []],
            ]);

            return OwnerProfile::create([
                'party_id' => $party->getKey(),
                'owner_group_id' => $data['owner_group_id'] ?? null,
                'is_primary' => $data['is_primary'] ?? false,
                'identification_number' => $data['identification_number'] ?? null,
                'unified_number' => $data['unified_number'] ?? null,
                'nationality' => $data['nationality'] ?? null,
                'trn' => $data['trn'] ?? null,
                'bank_name' => $data['bank_name'] ?? null,
                'bank_account_no' => $data['bank_account_no'] ?? null,
                'iban' => $data['iban'] ?? null,
            ]);
        });
    }
}
