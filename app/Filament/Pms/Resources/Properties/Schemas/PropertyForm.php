<?php

namespace App\Filament\Pms\Resources\Properties\Schemas;

use App\Enums\PMS\Emirate;
use App\Enums\PMS\PropertyType;
use App\Models\Party;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PropertyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Property'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('Property Name'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('address')
                            ->label(__('Address'))
                            ->maxLength(255),
                        TextInput::make('total_units')
                            ->label(__('Total Units'))
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('year_built')
                            ->label(__('Year Built'))
                            ->numeric()
                            ->minValue(1900)
                            ->maxValue((int) now()->format('Y')),
                        Select::make('emirate')
                            ->label(__('Emirate'))
                            ->options(Emirate::class)
                            ->helperText(__('Determines which government tenancy contract format applies (Ejari, Sharjawi, …).')),
                    ])->columns(3),

                Section::make(__('Government Property Details'))
                    ->description(__('Fields required by the emirate\'s own tenancy contract / attestation system.'))
                    ->schema([
                        TextInput::make('municipality')
                            ->label(__('Municipality'))
                            ->maxLength(255),
                        TextInput::make('suburb')
                            ->label(__('Suburb'))
                            ->maxLength(255),
                        TextInput::make('area')
                            ->label(__('Area'))
                            ->maxLength(255),
                        TextInput::make('title_deed_number')
                            ->label(__('Title Deed No.'))
                            ->maxLength(255),
                        DatePicker::make('title_deed_date')
                            ->label(__('Title Deed Date')),
                        TextInput::make('plot_number')
                            ->label(__('Government No.'))
                            ->maxLength(255),
                        Select::make('property_type')
                            ->label(__('Property Type'))
                            ->options(PropertyType::class),
                        TextInput::make('property_number')
                            ->label(__('Property No.'))
                            ->maxLength(255),
                    ])->columns(3),

                Section::make(__('Owners'))
                    ->description(__('Ownership percentages across all owners must add up to 100.'))
                    ->schema([
                        // Deliberately NOT ->relationship(): Filament's
                        // Repeater-to-pivot sync only ever attaches the
                        // related key, silently dropping the extra
                        // `ownership_percentage` pivot column. The page
                        // classes (Create/EditProperty) sync the pivot
                        // explicitly instead — a plain array field here,
                        // pre-filled from the existing pivot on edit.
                        Repeater::make('owners')
                            ->label(__('Owners'))
                            ->schema([
                                Select::make('party_id')
                                    ->label(__('Owner'))
                                    ->options(fn (): array => Party::withRole('owner')->orderBy('name')->pluck('name', 'id')->all())
                                    ->searchable()
                                    ->required()
                                    ->distinct()
                                    ->live(),
                                TextInput::make('ownership_percentage')
                                    ->label(__('Ownership %'))
                                    ->numeric()
                                    ->minValue(0.01)
                                    ->maxValue(100)
                                    ->step(0.01)
                                    ->required()
                                    ->live(onBlur: true),
                            ])
                            ->columns(2)
                            ->minItems(1)
                            ->live()
                            // Validated as one array field, not per-row: a
                            // per-item rule can't see its siblings, and a
                            // property whose owners sum to 60% (or 140%) is
                            // wrong regardless of how any single row looks.
                            ->rule(function () {
                                return function (string $attribute, mixed $value, \Closure $fail): void {
                                    $sum = collect($value)
                                        ->sum(fn (array $row): float => (float) ($row['ownership_percentage'] ?? 0));

                                    if (abs($sum - 100.0) > 0.01) {
                                        $fail(__('Ownership percentages must add up to 100 (currently :sum).', [
                                            'sum' => rtrim(rtrim(number_format($sum, 2), '0'), '.'),
                                        ]));
                                    }
                                };
                            })
                            ->addActionLabel(__('Add Owner'))
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
