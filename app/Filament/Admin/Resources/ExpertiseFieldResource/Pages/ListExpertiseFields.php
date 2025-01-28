<?php

namespace App\Filament\Admin\Resources\ExpertiseFieldResource\Pages;

use App\Filament\Admin\Resources\ExpertiseFieldResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListExpertiseFields extends ListRecords
{
    protected static string $resource = ExpertiseFieldResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
