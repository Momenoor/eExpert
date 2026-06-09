<?php

namespace App\Filament\Exports;

use App\Models\Matter;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Common\Exception\InvalidArgumentException;
use OpenSpout\Writer\Exception\InvalidSheetNameException;
use OpenSpout\Writer\Exception\WriterNotOpenedException;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

class MatterExporter extends Exporter
{
    protected static ?string $model = Matter::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('reference')
                ->label(__('Matter'))
                ->getStateUsing(fn($record) => $record->year . '/' . $record->number),

            ExportColumn::make('court.name')
                ->label(__('Court')),

            ExportColumn::make('type.name')
                ->label(__('Type')),

            ExportColumn::make('matter_level')
                ->label(__('Level'))
                ->getStateUsing(fn($record) => $record->level?->getLabel()),

            ExportColumn::make('parties')
                ->label(__('Parties'))
                ->getStateUsing(fn($record) => $record->indexedParties
                    ->map(fn($mp) => sprintf(
                        '%s #%d — %s',
                        __($mp->type ? ucfirst(str_replace('-', ' ', $mp->type)) : ''),
                        $mp->role_index,
                        $mp->party?->name ?? '—'
                    ))
                    ->join("\n")
                ),

            ExportColumn::make('experts')
                ->label(__('Experts'))
                ->getStateUsing(fn($record) => $record->indexedExperts
                    ->map(fn($mp) => sprintf(
                        '%s #%d — %s',
                        __($mp->type ? ucfirst(str_replace('-', ' ', $mp->type)) : ''),
                        $mp->role_index,
                        $mp->party?->name ?? '—' // ⚠️ double-check this — should it be $mp->expert->name or $mp->party->name?
                    ))
                    ->join("\n")
                ),

            ExportColumn::make('total_fees_without_vat')
                ->label(__('Total Fees (Without VAT)'))
                ->getStateUsing(fn($record) => number_format($record->fees->where('type', '!=', 'vat')->sum('amount'))),

            ExportColumn::make('total_vat')
                ->label(__('Total VAT'))
                ->getStateUsing(fn($record) => number_format($record->fees->where('type', 'vat')->sum('amount'))),

            ExportColumn::make('total_fees')
                ->label(__('Total'))
                ->getStateUsing(fn($record) => number_format($record->fees->sum('amount'))),

            ExportColumn::make('total_allocations')
                ->label(__('Sum Allocations'))
                ->getStateUsing(fn($record) => number_format($record->allocations->sum('amount'))),

            ExportColumn::make('unpaid_amount')
                ->label(__('Unpaid'))
                ->getStateUsing(fn($record) => number_format($record->fees->sum('amount') - $record->allocations->sum('amount'))),

            ExportColumn::make('notes')
                ->label(__('Notes'))
                ->getStateUsing(fn($record) => $record->notes
                    ->map(fn($note) => $note->text)
                    ->filter()
                    ->join("\n")
                ),

            ExportColumn::make('status')
                ->label(__('Status'))
                ->getStateUsing(fn($record) => $record->status?->getLabel()),

            ExportColumn::make('collection_status')
                ->label(__('Collection Status'))
                ->getStateUsing(fn($record) => $record->collection_status?->getLabel()),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = trans_choice('export_completed', $export->successful_rows, ['count' => Number::format($export->successful_rows)]);

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . trans_choice('export_failed', $failedRowsCount, ['count' => Number::format($failedRowsCount)]);
        }

        return $body;
    }

    public function getXlsxCellStyle(): ?Style
    {
        return new Style()
            ->setFontSize(11)
            ->setShouldWrapText()
            ->setFontName('Arial');
    }

    /**
     * @throws InvalidArgumentException
     */
    public function getXlsxHeaderCellStyle(): ?Style
    {
        return new Style()
            ->setFontBold()
            ->setShouldWrapText()
            ->setFontSize(11)
            ->setFontName('Arial')
            ->setFontColor(Color::rgb(255, 255, 255))
            ->setBackgroundColor(Color::rgb(31, 56, 100))
            ->setCellAlignment(CellAlignment::CENTER);
    }

    public function getXlsxWriterOptions(): ?Options
    {
        $options = new Options();
        $options->setColumnWidth(15, 1);  // Matter
        $options->setColumnWidth(22, 2);  // Court
        $options->setColumnWidth(18, 3);  // Type
        $options->setColumnWidth(18, 4);  // Level
        $options->setColumnWidth(40, 5);  // Parties
        $options->setColumnWidth(40, 6);  // Experts
        $options->setColumnWidth(22, 7);  // Total Fees (Without VAT)
        $options->setColumnWidth(14, 8);  // Total VAT
        $options->setColumnWidth(14, 9);  // Total
        $options->setColumnWidth(18, 10); // Sum Allocations
        $options->setColumnWidth(14, 11); // Unpaid
        $options->setColumnWidth(30, 12); // Notes
        $options->setColumnWidth(14, 13); // Status
        $options->setColumnWidth(20, 14); // Collection Status

        return $options;
    }

    /**
     * @throws InvalidSheetNameException
     * @throws WriterNotOpenedException
     * @throws InvalidArgumentException
     */
    public function configureXlsxWriterBeforeClose(Writer $writer): Writer
    {
        $sheetView = new SheetView();
        $sheetView->setFreezeRow(2);

        $sheet = $writer->getCurrentSheet();
        $sheet->setSheetView($sheetView);
        $sheet->setName('Matters');

        return $writer;
    }

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with([
            'court',
            'type',
            'mainPartiesOnly.party',           // used by getIndexedPartiesAttribute()
            'mainPartiesOnly.representatives.party', // loaded inside indexedParties too
            'expertsOnly.party',           // used by getIndexedExpertsAttribute()
            'fees',
            'notes',
            'allocations',
        ]);
    }

}
