<?php

namespace App\Filament\Admin\Resources\ExpertiseFieldResource\Pages;

use App\Filament\Admin\Resources\ExpertiseFieldResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditExpertiseField extends EditRecord
{
    protected static string $resource = ExpertiseFieldResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
