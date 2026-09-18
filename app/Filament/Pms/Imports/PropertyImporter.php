<?php

namespace App\Filament\Pms\Imports;

use App\Enums\PMS\Emirate;
use App\Enums\PMS\PropertyType;
use App\Models\Property;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;

/**
 * Re-importing the same property (matched by `name`) updates it rather than
 * creating a duplicate, so a corrected spreadsheet can be re-uploaded safely.
 */
class PropertyImporter extends Importer
{
    protected static ?string $model = Property::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')
                ->label(__('Property Name'))
                ->requiredMapping()
                ->example('Marina Tower')
                ->rules(['required', 'string']),
            ImportColumn::make('emirate')
                ->label(__('Emirate'))
                ->example('sharjah')
                ->castStateUsing(fn (?string $state) => $state ? Emirate::tryFrom(strtolower(trim($state)))?->value : null),
            ImportColumn::make('address')
                ->label(__('Address'))
                ->example('Al Majaz, Sharjah'),
            ImportColumn::make('municipality')
                ->label(__('Municipality'))
                ->example('Sharjah Municipality'),
            ImportColumn::make('suburb')
                ->label(__('Suburb'))
                ->example('Al Majaz'),
            ImportColumn::make('area')
                ->label(__('Area'))
                ->example('Al Majaz 3'),
            ImportColumn::make('title_deed_number')
                ->label(__('Title Deed No.'))
                ->example('TD-12345'),
            ImportColumn::make('title_deed_date')
                ->label(__('Title Deed Date'))
                ->example('2020-01-15'),
            ImportColumn::make('plot_number')
                ->label(__('Government No.'))
                ->example('525-312-633'),
            ImportColumn::make('property_type')
                ->label(__('Property Type'))
                ->example('building')
                ->castStateUsing(fn (?string $state) => $state ? PropertyType::tryFrom(strtolower(trim($state)))?->value : null),
            ImportColumn::make('property_number')
                ->label(__('Property No.'))
                ->example('P-4567'),
            ImportColumn::make('total_units')
                ->label(__('Total Units'))
                ->example('40')
                ->rules(['nullable', 'integer']),
            ImportColumn::make('year_built')
                ->label(__('Year Built'))
                ->example('2015')
                ->rules(['nullable', 'integer']),
        ];
    }

    public function resolveRecord(): Property
    {
        return Property::firstOrNew(['name' => $this->data['name']]);
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = __(':count propert(ies) imported.', ['count' => Number::format($import->successful_rows)]);

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.__(':count row(s) failed to import.', ['count' => Number::format($failedRowsCount)]);
        }

        return $body;
    }
}
