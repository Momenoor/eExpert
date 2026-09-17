<?php

namespace App\Filament\Mms\Resources\BulkMailCampaigns\Pages;

use App\Filament\Mms\Resources\BulkMailCampaigns\BulkMailCampaignResource;
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
