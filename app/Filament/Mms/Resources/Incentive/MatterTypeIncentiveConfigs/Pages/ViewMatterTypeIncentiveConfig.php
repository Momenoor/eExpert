<?php

namespace App\Filament\Mms\Resources\Incentive\MatterTypeIncentiveConfigs\Pages;

use App\Filament\Mms\Resources\Incentive\MatterTypeIncentiveConfigs\MatterTypeIncentiveConfigResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewMatterTypeIncentiveConfig extends ViewRecord
{
    protected static string $resource = MatterTypeIncentiveConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
