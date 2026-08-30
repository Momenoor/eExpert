<?php

namespace App\Filament\Resources\PartyLeaves\Schemas;

use App\Models\Party;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PartyLeaveForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Leave Details'))
                    ->description(__('Days inside this range are excluded from completion-day and monthly-quota calculations for this person.'))
                    ->schema([
                        Select::make('party_id')
                            ->label(__('Assistant / Expert'))
                            ->options(fn () => Party::query()
                                ->whereJsonContains('role', ['role' => 'expert'])
                                ->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                        DatePicker::make('start_date')
                            ->label(__('Start Date'))
                            ->required(),
                        DatePicker::make('end_date')
                            ->label(__('End Date'))
                            ->required()
                            ->afterOrEqual('start_date'),
                        Textarea::make('reason')
                            ->label(__('Reason'))
                            ->rows(2)
                            ->columnSpanFull(),
                    ])->columns(3),
            ]);
    }
}
