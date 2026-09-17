<?php

namespace App\Filament\Mms\Resources\Courts\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class CourtInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name')->label(__('Name')),
                TextEntry::make('phone')->label(__('Phone')),
                TextEntry::make('email')->label(__('Email address')),
                TextEntry::make('address')->label(__('Address')),
                TextEntry::make('created_at')->label(__('Created'))->dateTime(),
                TextEntry::make('updated_at')->label(__('Updated'))->dateTime(),
            ]);
    }
}
