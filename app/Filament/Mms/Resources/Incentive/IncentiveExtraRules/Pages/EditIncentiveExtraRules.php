<?php

namespace App\Filament\Mms\Resources\Incentive\IncentiveExtraRules\Pages;

use App\Filament\Mms\Resources\Incentive\IncentiveExtraRules\IncentiveExtraRulesResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditIncentiveExtraRules extends EditRecord
{
    protected static string $resource = IncentiveExtraRulesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
