<?php

namespace App\Filament\Resources\BulkMailCampaignResource\Pages;

use App\Filament\Resources\BulkMailCampaignResource\BulkMailCampaignResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateBulkMailCampaign extends CreateRecord
{
    protected static string $resource = BulkMailCampaignResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::id();
        return $data;
    }
}
