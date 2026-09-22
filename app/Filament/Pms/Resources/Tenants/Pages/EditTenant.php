<?php

namespace App\Filament\Pms\Resources\Tenants\Pages;

use App\Filament\Pms\Resources\Tenants\TenantResource;
use App\Models\Tenant;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditTenant extends EditRecord
{
    protected static string $resource = TenantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->before(function (Tenant $record, DeleteAction $action): void {
                    if ($record->hasLeaseHistory()) {
                        Notification::make()
                            ->danger()
                            ->title(__('Could not continue'))
                            ->body(__('This tenant is linked to a lease and cannot be deleted.'))
                            ->send();

                        $action->halt();
                    }
                }),
        ];
    }

    /**
     * Pull the Party's own fields into the form the profile's fields
     * already fill — from the user's side these are one record.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Tenant $profile */
        $profile = $this->getRecord();
        $party = $profile->party;

        $data['name'] = $party->name;
        $data['phone'] = $party->phone ?? [];
        $data['email'] = $party->email ?? [];

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Tenant $record */
        $record->party->update([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? [],
            'email' => $data['email'] ?? [],
        ]);

        $record->update([
            'tenant_type' => $data['tenant_type'],
            'identification_type' => $data['identification_type'],
            'identification_number' => $data['identification_number'],
            'trn' => $data['trn'] ?? null,
            'emergency_contact_name' => $data['emergency_contact_name'] ?? null,
            'emergency_contact_phone' => $data['emergency_contact_phone'] ?? null,
        ]);

        return $record;
    }
}
