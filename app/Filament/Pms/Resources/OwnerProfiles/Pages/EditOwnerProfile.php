<?php

namespace App\Filament\Pms\Resources\OwnerProfiles\Pages;

use App\Filament\Pms\Resources\OwnerProfiles\OwnerProfileResource;
use App\Models\OwnerProfile;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditOwnerProfile extends EditRecord
{
    protected static string $resource = OwnerProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var OwnerProfile $profile */
        $profile = $this->getRecord();
        $party = $profile->party;

        $data['name'] = $party->name;
        $data['phone'] = $party->phone ?? [];
        $data['email'] = $party->email ?? [];
        $data['owner_group_id'] = $profile->owner_group_id;

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var OwnerProfile $record */
        $record->party->update([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? [],
            'email' => $data['email'] ?? [],
        ]);

        $record->update([
            'owner_group_id' => $data['owner_group_id'] ?? null,
            'identification_number' => $data['identification_number'] ?? null,
            'trn' => $data['trn'] ?? null,
            'bank_name' => $data['bank_name'] ?? null,
            'bank_account_no' => $data['bank_account_no'] ?? null,
            'iban' => $data['iban'] ?? null,
        ]);

        return $record;
    }
}
