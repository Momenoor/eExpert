<?php

namespace App\Filament\Pms\Resources\Quotations\Pages;

use App\Filament\Pms\Resources\Quotations\QuotationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListQuotations extends ListRecords
{
    protected static string $resource = QuotationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label(__('New Quotation')),
        ];
    }
}
