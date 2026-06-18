<?php

namespace App\Filament\Exports;

use App\Enums\FeeType;
use App\Models\MatterParty;
use Filament\Actions\Exports\Enums\ExportFormat;
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

class AssistantMatterFeesExporter extends Exporter
{
    protected static ?string $model = MatterParty::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('matter.reference')
                ->label(__('Matter'))
                ->getStateUsing(fn($record) => $record->matter->year . '/' . $record->matter->number),

            ExportColumn::make('matter.court.name')
                ->label(__('Court')),

            ExportColumn::make('matter.type.name')
                ->label(__('Type')),

            ExportColumn::make('party.name')
                ->label(__('Assistant')),

            ExportColumn::make('matter.final_report_at')
                ->label(__('Final Report Date')),

            ExportColumn::make('total_matter_fees')
                ->label(__('Total Matter Fees'))
                ->getStateUsing(fn($record) => number_format($record->total_matter_fees ?? 0, 2)),

            ExportColumn::make('divided_fees')
                ->label(__('Divided Fees'))
                ->getStateUsing(fn($record) => number_format(
                    ($record->assistants_count > 0) ? ($record->total_matter_fees / $record->assistants_count) : 0, 2
                )),
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
        $options->setColumnWidth(25, 4);  // Assistant
        $options->setColumnWidth(18, 5);  // Final Report Date
        $options->setColumnWidth(22, 6);  // Total Matter Fees
        $options->setColumnWidth(22, 7);  // Divided Fees

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
        $sheetView->setRightToLeft(app()->getLocale() == 'ar');

        $sheet = $writer->getCurrentSheet();
        $sheet->setSheetView($sheetView);
        $sheet->setName(__('Assistant Fees'));

        return $writer;
    }
    public static function getChunkSize(): int
    {
        return 4000; // Try lowering this to ease memory pressure per job loop
    }
    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with([
            'matter.court',
            'matter.type',
            'party',
        ])
        ->withSum(['matter_fees as total_matter_fees' => function ($q) {
            $q->where('type', '!=', FeeType::VAT->value);
        }], 'amount')
        ->withCount(['matter_assistants as assistants_count']);
    }
    public function getFormats(): array
    {
        return [
            ExportFormat::Xlsx, // Remove ExportFormat::Xlsx if it's there
        ];
    }
}
