<?php

namespace App\Filament\Admin\Resources\RoleResource\Pages;

use App\Filament\Admin\Resources\RoleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Spatie\Permission\Models\Permission;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Get all selected permission IDs from the form data
        $selectedPermissions = array_merge(
            ...array_values(array_filter($data, 'is_array'))
        );

        // Sync the selected permissions with the role
        $this->record->syncPermissions(Permission::whereIn('id', $selectedPermissions)->get());

        return $data;
    }
}
