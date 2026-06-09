<?php

namespace App\Filament\Resources\BulkMailCampaigns\Pages;

use App\Filament\Resources\BulkMailCampaigns\BulkMailCampaignResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBulkMailCampaigns extends ListRecords
{
    protected static string $resource = BulkMailCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
