<?php

namespace App\Filament\Resources\MatterRequests\Pages;

use App\Filament\Resources\MatterRequests\MatterRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListMatterRequests extends ListRecords
{
    protected static string $resource = MatterRequestResource::class;
}
