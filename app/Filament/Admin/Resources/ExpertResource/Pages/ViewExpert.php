<?php

namespace App\Filament\Admin\Resources\ExpertResource\Pages;

use App\Filament\Admin\Resources\ExpertResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewExpert extends ViewRecord
{
    protected static string $resource = ExpertResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
