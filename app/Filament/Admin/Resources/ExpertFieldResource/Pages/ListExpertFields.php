<?php

namespace App\Filament\Admin\Resources\ExpertFieldResource\Pages;

use App\Filament\Admin\Resources\ExpertFieldResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListExpertFields extends ListRecords
{
    protected static string $resource = ExpertFieldResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
