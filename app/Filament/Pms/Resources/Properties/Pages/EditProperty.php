<?php

namespace App\Filament\Pms\Resources\Properties\Pages;

use App\Filament\Pms\Resources\Properties\PropertyResource;
use App\Models\Party;
use App\Models\Property;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProperty extends EditRecord
{
    protected static string $resource = PropertyResource::class;

    /**
     * @var list<array{party_id: int, ownership_percentage: float}>
     */
    private array $owners = [];

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * Turn the saved pivot rows back into the repeater's array shape.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Property $property */
        $property = $this->getRecord();

        $data['owners'] = $property->owners->map(fn (Party $owner): array => [
            'party_id' => $owner->getKey(),
            'ownership_percentage' => (float) $owner->getAttribute('pivot')->getAttribute('ownership_percentage'),
        ])->all();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->owners = $data['owners'] ?? [];
        unset($data['owners']);

        return $data;
    }

    protected function afterSave(): void
    {
        /** @var Property $property */
        $property = $this->getRecord();

        $property->owners()->sync($this->pivotRows());
    }

    /**
     * @return array<int, array{ownership_percentage: float}>
     */
    private function pivotRows(): array
    {
        $rows = [];

        foreach ($this->owners as $owner) {
            $rows[(int) $owner['party_id']] = ['ownership_percentage' => (float) $owner['ownership_percentage']];
        }

        return $rows;
    }
}
