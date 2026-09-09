<?php

namespace App\Filament\Resources\PartyLeaves\Pages;

use App\Filament\Concerns\RefreshesPayrollData;
use App\Filament\Resources\PartyLeaves\PartyLeaveResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPartyLeaves extends ListRecords
{
    use RefreshesPayrollData;

    protected static string $resource = PartyLeaveResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
