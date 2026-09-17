<?php

namespace App\Filament\Pms\Resources\Leases\Pages;

use App\Filament\Pms\Resources\Leases\LeaseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLeases extends ListRecords
{
    protected static string $resource = LeaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label(__('New Lease')),
        ];
    }
}
