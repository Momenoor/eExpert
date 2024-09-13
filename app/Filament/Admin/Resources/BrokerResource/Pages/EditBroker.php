<?php

namespace App\Filament\Admin\Resources\BrokerResource\Pages;

use App\Filament\Admin\Resources\BrokerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBroker extends EditRecord
{
    protected static string $resource = BrokerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
