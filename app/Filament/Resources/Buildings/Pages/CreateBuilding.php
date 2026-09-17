<?php

namespace App\Filament\Resources\Buildings\Pages;

use App\Filament\Resources\Buildings\BuildingResource;
use App\Models\Building;
use Filament\Resources\Pages\CreateRecord;

class CreateBuilding extends CreateRecord
{
    protected static string $resource = BuildingResource::class;

    /**
     * @var list<array{party_id: int, ownership_percentage: float}>
     */
    private array $owners = [];

    /**
     * `owners` isn't a column on `buildings` — it's synced to the
     * `owner_building` pivot in afterCreate() instead, once the record
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
        /** @var Building $building */
        $building = $this->getRecord();

        $building->owners()->sync($this->pivotRows());
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
