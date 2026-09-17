<?php

namespace App\Filament\Mms\Resources\Incentive\IncentiveExtraRules\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class IncentiveExtraRulesForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Extra Percentage Rule'))
                    ->description(__('Applies to tiered matters only. Per PDF: 5=1.5%, 6=2%, >6=3%. Minimum 6 matters required — below minimum deducts 2% per missing matter.'))
                    ->schema([
                        TextInput::make('min_count')
                            ->label(__('Minimum Completed Matters'))
                            ->numeric()->required()->minValue(1)
                            ->helperText(__('e.g. 5 means this rule applies when assistant completes at least 5 matters')),
                        TextInput::make('max_count')
                            ->label(__('Maximum Completed Matters'))
                            ->numeric()
                            ->placeholder(__('Leave empty for no upper limit'))
                            ->helperText(__('e.g. 5 for exact match (5 only), empty for 7 and above')),
                        TextInput::make('extra_percentage')
                            ->label(__('Extra Percentage (%)'))
                            ->suffix('%')->numeric()->required()->minValue(0)
                            ->helperText(__('e.g. 1.5 for +1.5%')),
                    ])->columns(3),
            ]);
    }
}
