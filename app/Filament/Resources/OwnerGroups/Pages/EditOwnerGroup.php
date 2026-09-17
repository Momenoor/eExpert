<?php

namespace App\Filament\Resources\OwnerGroups\Pages;

use App\Filament\Resources\OwnerGroups\OwnerGroupResource;
use App\Models\OwnerGroup;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditOwnerGroup extends EditRecord
{
    protected static string $resource = OwnerGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * `name` comes straight from the group; `phone`/`email` come from the
     * linked Party, which exists only to hold them.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var OwnerGroup $group */
        $group = $this->getRecord();
        $party = $group->party;

        $data['phone'] = $party->phone ?? [];
        $data['email'] = $party->email ?? [];

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var OwnerGroup $record */
        $record->party->update([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? [],
            'email' => $data['email'] ?? [],
        ]);

        $record->update([
            'name' => $data['name'],
            'trn' => $data['trn'] ?? null,
            'bank_name' => $data['bank_name'] ?? null,
            'bank_account_no' => $data['bank_account_no'] ?? null,
            'iban' => $data['iban'] ?? null,
        ]);

        return $record;
    }
}
