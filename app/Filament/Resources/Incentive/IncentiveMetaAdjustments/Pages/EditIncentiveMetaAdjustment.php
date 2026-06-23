<?php

namespace App\Filament\Resources\Incentive\IncentiveMetaAdjustments\Pages;

use App\Filament\Resources\Incentive\IncentiveMetaAdjustments\IncentiveMetaAdjustmentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditIncentiveMetaAdjustment extends EditRecord
{
    protected static string $resource = IncentiveMetaAdjustmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
