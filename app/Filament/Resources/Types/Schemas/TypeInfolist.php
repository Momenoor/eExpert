<?php

namespace App\Filament\Resources\Types\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class TypeInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make(__('General Information'))
                    ->icon(Heroicon::OutlinedInformationCircle)
                    ->columns(2)
                    ->components([
                        TextEntry::make('name')
                            ->label(__('Name'))
                            ->icon(Heroicon::OutlinedTag)
                            ->columnSpanFull(),

                        IconEntry::make('active')
                            ->label(__('Active'))
                            ->boolean(),

                        IconEntry::make('allow_current_status_import')
                            ->label(__('Allow Current Status Import'))
                            ->boolean(),

                        IconEntry::make('exclude_from_incentive_count')
                            ->label(__('Exclude from Incentive Count'))
                            ->boolean(),
                    ]),

                Section::make(__('Incentive Configuration'))
                    ->icon(Heroicon::OutlinedCalculator)
                    ->columns(2)
                    ->components([
                        TextEntry::make('incentive_trigger_type')
                            ->label(__('Incentive Trigger Type'))
                            ->badge()
                            ->columnSpanFull(),

                        TextEntry::make('matters_count')
                            ->counts('matters')
                            ->label(__('Total Matters'))
                            ->icon(Heroicon::OutlinedFolder)
                            ->numeric(),
                    ]),

                Section::make(__('Timestamps'))
                    ->icon(Heroicon::OutlinedClock)
                    ->columns(2)
                    ->components([
                        TextEntry::make('created_at')
                            ->label(__('Created'))
                            ->dateTime()
                            ->placeholder('-')
                            ->icon(Heroicon::OutlinedCalendar),

                        TextEntry::make('updated_at')
                            ->label(__('Updated'))
                            ->dateTime()
                            ->placeholder('-')
                            ->icon(Heroicon::OutlinedCalendar),
                    ]),
            ])->columns(3);
    }
}
