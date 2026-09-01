<?php

namespace App\Filament\Resources\MatterRequests\Pages;

use App\Filament\Actions\Request\ApproveRequestAction;
use App\Filament\Actions\Request\RejectRequestAction;
use App\Filament\Resources\MatterRequests\MatterRequestResource;
use App\Services\WhatsAppService;
use Filament\Actions\Action;
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
