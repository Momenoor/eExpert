<?php

namespace App\Filament\Admin\Resources\BrokerResource\Pages;

use App\Filament\Admin\Resources\BrokerResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBrokers extends ListRecords
{
    protected static string $resource = BrokerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
