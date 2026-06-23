<?php

namespace App\Filament\Resources\Incentive\MatterTypeIncentiveConfigs\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MatterTypeIncentiveConfigInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Configuration'))->schema([
                    TextEntry::make('matterTypes.name')->label(__('Matter Type'))->listWithLineBreaks()->limitList(20),
                    TextEntry::make('name')->label(__('Name')),
                    TextEntry::make('calculation_type')->label(__('Type'))->badge()
                        ->color(fn ($state) => match ($state) {
                            'fixed' => 'success', 'tiered' => 'info', 'committee' => 'warning', default => 'gray',
                        }),
                    TextEntry::make('assistant_rate')->label(__('Assistant Rate'))->suffix('%'),
                    TextEntry::make('fixed_percentage')->label(__('Fixed %'))->suffix('%')->placeholder('—'),
                ])->columns(3),

                Section::make(__('Tiers'))
                    ->visible(fn ($record) => in_array($record->calculation_type, ['tiered', 'committee']))
                    ->schema([
                        RepeatableEntry::make('tiers')->label('')->schema([
                            TextEntry::make('difficulty')->label(__('Difficulty'))->badge(),
                            TextEntry::make('days_from')->label(__('From Day')),
                            TextEntry::make('days_to')->label(__('To Day'))->placeholder(__('No limit')),
                            TextEntry::make('percentage')->label(__('Incentive %'))->suffix('%'),
                        ])->columns(4),
                    ]),
            ]);
    }
}
