<?php

namespace App\Filament\Resources\LeaveRequests\Pages;

use App\Filament\Concerns\RefreshesPayrollData;
use App\Filament\Resources\LeaveRequests\LeaveRequestResource;
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
