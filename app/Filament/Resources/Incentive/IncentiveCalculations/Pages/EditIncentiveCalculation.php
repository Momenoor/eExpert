<?php

namespace App\Filament\Resources\Incentive\IncentiveCalculations\Pages;

use App\Filament\Resources\Incentive\IncentiveCalculations\IncentiveCalculationResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditIncentiveCalculation extends EditRecord
{
    protected static string $resource = IncentiveCalculationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
