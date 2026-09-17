<?php

namespace App\Filament\Mms\Resources\CalendarEvents\Pages;

use App\Filament\Mms\Resources\CalendarEvents\CalendarEventResource;
use Filament\Resources\Pages\ManageRecords;

class ListCalendarEvents extends ManageRecords
{
    protected static string $resource = CalendarEventResource::class;

    protected function getHeaderActions(): array
    {
        return [

        ];
    }
}
