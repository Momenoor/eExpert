<?php

namespace App\Filament\Resources\OwnerGroups\Pages;

use App\Filament\Resources\OwnerGroups\OwnerGroupResource;
use App\Models\OwnerGroup;
use App\Models\Party;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateOwnerGroup extends CreateRecord
{
    protected static string $resource = OwnerGroupResource::class;

    /**
     * `phone`/`email` belong to a new `Party` (tagged 'owner_group', its name
     * mirroring the group's for lookups) created purely to supply the
     * estate's own contact details — `name` itself, and the banking fields,
     * are the group's own. One transaction so a failure partway through
     * never leaves an orphaned Party with no group.
     */
    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data): OwnerGroup {
            $party = Party::create([
                'name' => $data['name'],
                'phone' => $data['phone'] ?? [],
                'email' => $data['email'] ?? [],
                'role' => ['role' => ['owner_group'], 'type' => []],
            ]);

            return OwnerGroup::create([
                'party_id' => $party->getKey(),
                'name' => $data['name'],
                'trn' => $data['trn'] ?? null,
                'bank_name' => $data['bank_name'] ?? null,
                'bank_account_no' => $data['bank_account_no'] ?? null,
                'iban' => $data['iban'] ?? null,
            ]);
        });
    }
}
