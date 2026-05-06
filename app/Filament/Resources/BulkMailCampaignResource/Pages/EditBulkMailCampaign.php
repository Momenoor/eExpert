<?php

namespace App\Filament\Resources\BulkMailCampaignResource\Pages;

use App\Filament\Resources\BulkMailCampaignResource\BulkMailCampaignResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBulkMailCampaign extends EditRecord
{
    protected static string $resource = BulkMailCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }
}
