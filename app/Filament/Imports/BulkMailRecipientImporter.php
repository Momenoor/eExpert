<?php

namespace App\Filament\Imports;

use App\Models\BulkMailRecipient;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;

class BulkMailRecipientImporter extends Importer
{
    protected static ?string $model = BulkMailRecipient::class;


    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')
                ->requiredMapping()
                ->example('شركة جيه بي ايه لتدقيق ومراجعة الحسابات'),
            ImportColumn::make('email')
                ->requiredMapping()
                ->multiple(',')
                ->example('example1@example.com;example2@example.com'),
        ];
    }

    public function resolveRecord(): BulkMailRecipient
    {
        return new BulkMailRecipient();
    }

    protected function beforeSave(): void
    {
        $this->record->campaign_id = $this->options['campaign_id'];
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your recipients import import has completed and ' . Number::format($import->successful_rows) . ' ' . str('row')->plural($import->successful_rows) . ' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to import.';
        }

        return $body;
    }
}
