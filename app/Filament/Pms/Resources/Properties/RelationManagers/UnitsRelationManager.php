<?php

namespace App\Filament\Pms\Resources\Properties\RelationManagers;

use App\Enums\PMS\ContractType;
use App\Enums\PMS\PropertyClassification;
use App\Enums\PMS\UnitStatus;
use App\Enums\PMS\UnitType;
use App\Filament\Pms\Imports\UnitImporter;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ImportAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UnitsRelationManager extends RelationManager
{
    protected static string $relationship = 'units';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('unit_number')
                ->label(__('Unit Number'))
                ->required()
                ->maxLength(255),
            TextInput::make('floor')
                ->label(__('Floor'))
                ->maxLength(255),
            Select::make('unit_type')
                ->label(__('Unit Type'))
                ->options(UnitType::class)
                ->required()
                ->live()
                // The classification a type normally implies, offered as a
                // default the office can still override — a warehouse inside
                // a mixed-use tower may need a different rate than usual.
                ->afterStateUpdated(function (Set $set, $state): void {
                    $type = $state instanceof UnitType ? $state : UnitType::tryFrom((string) $state);

                    $set('property_classification', $type?->defaultClassification()->value);
                    $set('rental_type', $type ? ContractType::defaultForUnitType($type)?->value : null);
                }),
            Select::make('rental_type')
                ->label(__('Rental Type'))
                ->options(function (Get $get): array {
                    $state = $get('unit_type');
                    $type = $state instanceof UnitType ? $state : UnitType::tryFrom((string) $state);

                    return collect($type ? ContractType::forUnitType($type) : [])
                        ->mapWithKeys(fn (ContractType $rental): array => [$rental->value => $rental->getLabel()])
                        ->all();
                })
                ->helperText(__('Leases on this unit take their contract type from this.')),
            Select::make('property_classification')
                ->label(__('Property Classification'))
                ->options(PropertyClassification::class)
                ->required()
                ->helperText(__('Drives VAT: residential is exempt, commercial and industrial are taxed at 5%.')),
            TextInput::make('rental_rate')
                ->label(__('Rental Rate (AED/year)'))
                ->numeric()
                ->minValue(0)
                ->step(0.01)
                ->required(),
            TextInput::make('area_sqm')
                ->label(__('Area (Square Meter)'))
                ->numeric()
                ->minValue(0)
                ->step(0.01),
            TextInput::make('number_of_rooms')
                ->label(__('No. of Rooms'))
                ->numeric()
                ->minValue(0)
                ->helperText(__('Residential units only.')),
            TextInput::make('premise_number')
                ->label(__('Premise Number'))
                ->helperText(__('DEWA/SEWA/etc. premise number, depending on the property\'s emirate.'))
                ->maxLength(255),
            Select::make('status')
                ->label(__('Status'))
                ->options(UnitStatus::class)
                ->default(UnitStatus::VACANT->value)
                ->required(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('unit_number')
            ->columns([
                TextColumn::make('unit_number')
                    ->label(__('Unit Number'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('floor')
                    ->label(__('Floor'))
                    ->placeholder('—'),
                TextColumn::make('unit_type')
                    ->label(__('Type'))
                    ->badge(),
                TextColumn::make('property_classification')
                    ->label(__('Classification'))
                    ->badge(),
                TextColumn::make('rental_rate')
                    ->label(__('Rental Rate'))
                    ->numeric(decimalPlaces: 2),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge(),
            ])
            ->defaultSort('unit_number')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(UnitStatus::class),
                SelectFilter::make('property_classification')
                    ->label(__('Classification'))
                    ->options(PropertyClassification::class),
            ])
            ->headerActions([
                ImportAction::make()
                    ->importer(UnitImporter::class)
                    ->pluralModelLabel(__('Units')),
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading(__('No units yet'));
    }
}
