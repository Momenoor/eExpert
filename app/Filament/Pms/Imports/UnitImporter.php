<?php

namespace App\Filament\Pms\Imports;

use App\Enums\PMS\ContractType;
use App\Enums\PMS\PropertyClassification;
use App\Enums\PMS\UnitStatus;
use App\Enums\PMS\UnitType;
use App\Models\Property;
use App\Models\Unit;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;

/**
 * A unit is matched to its property by NAME (not ID) since that's what a
 * spreadsheet naturally has — the property must already exist (import
 * properties first). Re-importing the same unit (same property + unit
 * number) updates it rather than creating a duplicate.
 */
class UnitImporter extends Importer
{
    protected static ?string $model = Unit::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('property_name')
                ->label(__('Property Name'))
                ->requiredMapping()
                ->example('Marina Tower')
                ->rules(['required', 'string']),
            ImportColumn::make('unit_number')
                ->label(__('Unit Number'))
                ->requiredMapping()
                ->example('101')
                ->rules(['required', 'string']),
            ImportColumn::make('floor')
                ->label(__('Floor'))
                ->example('1'),
            ImportColumn::make('rental_rate')
                ->label(__('Rental Rate (AED/year)'))
                ->example('60000')
                ->rules(['nullable', 'numeric']),
            ImportColumn::make('area_sqm')
                ->label(__('Area (Square Meter)'))
                ->example('85.5')
                ->rules(['nullable', 'numeric']),
            ImportColumn::make('number_of_rooms')
                ->label(__('No. of Rooms'))
                ->example('2')
                ->rules(['nullable', 'integer']),
            ImportColumn::make('premise_number')
                ->label(__('Premise Number'))
                ->example('2874718841'),
            ImportColumn::make('unit_type')
                ->label(__('Unit Type'))
                ->requiredMapping()
                ->example('two_bedroom')
                ->castStateUsing(fn (?string $state) => $state ? UnitType::tryFrom(strtolower(trim($state)))?->value : null)
                ->rules(['required']),
            ImportColumn::make('rental_type')
                ->label(__('Rental Type'))
                ->example('family')
                ->castStateUsing(fn (?string $state) => $state ? ContractType::tryFrom(strtolower(trim($state)))?->value : null),
            ImportColumn::make('property_classification')
                ->label(__('Property Classification'))
                ->example('residential')
                ->castStateUsing(fn (?string $state) => $state ? PropertyClassification::tryFrom(strtolower(trim($state)))?->value : null),
            ImportColumn::make('status')
                ->label(__('Status'))
                ->example('vacant')
                ->castStateUsing(fn (?string $state) => $state ? UnitStatus::tryFrom(strtolower(trim($state)))?->value : UnitStatus::VACANT->value),
        ];
    }

    public function resolveRecord(): Unit
    {
        $property = Property::where('name', $this->data['property_name'])->first();

        if (! $property) {
            throw new RowImportFailedException(__(
                'No property named ":name" was found. Import properties first.',
                ['name' => $this->data['property_name']],
            ));
        }

        $unit = Unit::firstOrNew([
            'property_id' => $property->id,
            'unit_number' => $this->data['unit_number'],
        ]);

        // firstOrNew won't infer a classification for a brand-new unit the
        // way the form's reactive Select does — fall back to the type's own
        // default rather than leaving the column empty.
        if (! $unit->exists && empty($this->data['property_classification'])) {
            $type = UnitType::tryFrom($this->data['unit_type'] ?? '');
            $unit->property_classification = $type?->defaultClassification();
        }

        return $unit;
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = __(':count unit(s) imported.', ['count' => Number::format($import->successful_rows)]);

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.__(':count row(s) failed to import.', ['count' => Number::format($failedRowsCount)]);
        }

        return $body;
    }
}
