<?php

namespace App\Filament\Resources\Incentive\IncentiveMetaAdjustments\Pages;

use App\Filament\Resources\Incentive\IncentiveMetaAdjustments\IncentiveMetaAdjustmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListIncentiveMetaAdjustments extends ListRecords
{
    protected static string $resource = IncentiveMetaAdjustmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
