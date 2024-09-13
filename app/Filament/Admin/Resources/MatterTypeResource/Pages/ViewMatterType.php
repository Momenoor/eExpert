<?php

namespace App\Filament\Admin\Resources\MatterTypeResource\Pages;

use App\Filament\Admin\Resources\MatterTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewMatterType extends ViewRecord
{
    protected static string $resource = MatterTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
