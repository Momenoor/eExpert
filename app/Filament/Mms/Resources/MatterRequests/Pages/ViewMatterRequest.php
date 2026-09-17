<?php

namespace App\Filament\Mms\Resources\MatterRequests\Pages;

use App\Filament\Mms\Actions\Request\ApproveRequestAction;
use App\Filament\Mms\Actions\Request\RejectRequestAction;
use App\Filament\Mms\Resources\MatterRequests\MatterRequestResource;
use Filament\Resources\Pages\ViewRecord;

class ViewMatterRequest extends ViewRecord
{
    protected static string $resource = MatterRequestResource::class;

    public function getHeaderActions(): array
    {
        return [
            ApproveRequestAction::make(),
            RejectRequestAction::make(),
            //            Action::make('sendWhatsApp')
            //                ->label('Send WhatsApp')
            //                ->icon('heroicon-o-phone')
            //                ->color('success')
            //                ->action(
            //                    fn($record) => WhatsAppService::notifyNewRequest(auth()->user(), $record)
            //                ),
        ];
    }
}
