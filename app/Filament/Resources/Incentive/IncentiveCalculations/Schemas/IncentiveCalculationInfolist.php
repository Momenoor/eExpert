<?php

namespace App\Filament\Resources\Incentive\IncentiveCalculations\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class IncentiveCalculationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Calculation Details'))->schema([
                    TextEntry::make('name')->label(__('Name'))->columnSpanFull(),
                    TextEntry::make('status')->label(__('Status'))->badge()
                        ->color(fn ($state) => $state === 'finalized' ? 'success' : 'warning')
                        ->formatStateUsing(fn ($state) => $state === 'finalized' ? __('Finalized') : __('Draft')),
                    TextEntry::make('period_start')->label(__('Period Start'))->date(),
                    TextEntry::make('period_end')->label(__('Period End'))->date(),
                    TextEntry::make('finalized_at')->label(__('Finalized At'))->dateTime()->placeholder(__('Not finalized yet')),
                    TextEntry::make('creator.name')->label(__('Created By')),
                    TextEntry::make('notes')->label(__('Notes'))->placeholder('—')->columnSpanFull(),
                ])->columns(3),
            ]);
    }
}
