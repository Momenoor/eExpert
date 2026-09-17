<?php

namespace App\Filament\Mms\Resources\Incentive\MatterTypeIncentiveConfigs\Pages;

use App\Filament\Mms\Resources\Incentive\MatterTypeIncentiveConfigs\MatterTypeIncentiveConfigResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditMatterTypeIncentiveConfig extends EditRecord
{
    protected static string $resource = MatterTypeIncentiveConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
