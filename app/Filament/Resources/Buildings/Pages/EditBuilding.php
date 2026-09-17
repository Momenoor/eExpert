<?php

namespace App\Filament\Resources\Buildings\Pages;

use App\Filament\Resources\Buildings\BuildingResource;
use App\Models\Building;
use App\Models\Party;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBuilding extends EditRecord
{
    protected static string $resource = BuildingResource::class;

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
        /** @var Building $building */
        $building = $this->getRecord();

        $data['owners'] = $building->owners->map(fn (Party $owner): array => [
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
