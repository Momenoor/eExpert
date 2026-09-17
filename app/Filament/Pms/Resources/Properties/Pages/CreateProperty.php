<?php

namespace App\Filament\Pms\Resources\Properties\Pages;

use App\Filament\Pms\Resources\Properties\PropertyResource;
use App\Models\Property;
use Filament\Resources\Pages\CreateRecord;

class CreateProperty extends CreateRecord
{
    protected static string $resource = PropertyResource::class;

    /**
     * @var list<array{party_id: int, ownership_percentage: float}>
     */
    private array $owners = [];

    /**
     * `owners` isn't a column on `properties` — it's synced to the
     * `owner_property` pivot in afterCreate() instead, once the record
     * (and therefore its id) actually exists.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->owners = $data['owners'] ?? [];
        unset($data['owners']);

        return $data;
    }

    protected function afterCreate(): void
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
