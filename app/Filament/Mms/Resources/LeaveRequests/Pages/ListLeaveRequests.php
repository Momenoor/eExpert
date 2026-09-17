<?php

namespace App\Filament\Mms\Resources\LeaveRequests\Pages;

use App\Filament\Mms\Concerns\RefreshesPayrollData;
use App\Filament\Mms\Resources\LeaveRequests\LeaveRequestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLeaveRequests extends ListRecords
{
    use RefreshesPayrollData;

    protected static string $resource = LeaveRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label(__('New Leave Request')),
        ];
    }
}
