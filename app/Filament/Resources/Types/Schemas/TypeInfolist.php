<?php

namespace App\Filament\Resources\Types\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class TypeInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name')
                    ->label(__('Name')),
                IconEntry::make('active')
                    ->label(__('Active'))
                    ->boolean(),
                IconEntry::make('allow_current_status_import')
                    ->label(__('Allow Current Status Import'))
                    ->boolean(),
                TextEntry::make('matters_count')
                    ->counts('matters')
                    ->label(__('Total Matters')),
                TextEntry::make('created_at')
                    ->label(__('Created'))
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->label(__('Updated'))
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
