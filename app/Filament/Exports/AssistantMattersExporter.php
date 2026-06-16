<?php

namespace App\Filament\Exports;

use App\Enums\FeeType;
use App\Enums\MatterStatus;
use App\Models\Matter;
use App\Models\MatterParty;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
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

class AssistantMattersExporter extends Exporter
{
    protected static ?string $model = Matter::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('reference')
                ->label(__('Matter'))
                ->getStateUsing(fn($record) => $record->year . '/' . $record->number),

                        ExportColumn::make('court')
                ->label(__('Court'))
                ->getStateUsing(fn($record) => $record->court?->name ?? '—'),

            ExportColumn::make('type')
                ->label(__('Type'))
                ->getStateUsing(fn($record) => $record->type?->name ?? '—'),

            ExportColumn::make('status')
                ->label(__('Status'))
                ->getStateUsing(fn($record) => $record->status->getLabel()),

            ExportColumn::make('difficulty')
                ->label(__('Difficulty'))
                ->getStateUsing(fn($record) => $record->difficulty?->getLabel() ?? '—'),

            ExportColumn::make('experts')
                ->label(__('Experts'))
                ->getStateUsing(fn($record) => $record->mainExpertsOnly
                    ->map(fn($e) => $e->name)->join(' | ') ?: '—'
                ),

            ExportColumn::make('assistant')
                ->label(__('Assistant'))
                ->getStateUsing(fn($record) => $record->matterParties
                    ->where('role', 'expert')
                    ->where('type', 'assistant')
                    ->first()?->party?->name ?? '—'
                ),

            ExportColumn::make('plaintiffs')
                ->label(__('Plaintiffs'))
                ->getStateUsing(fn($record) => $record->matterParties
                    ->where('role', 'party')
                    ->where('type', 'plaintiff')
                    ->map(fn($mp) => $mp->party?->name ?? '—')
                    ->join("\n") ?: '—'
                ),

            ExportColumn::make('defendants')
                ->label(__('Defendants'))
                ->getStateUsing(fn($record) => $record->matterParties
                    ->where('role', 'party')
                    ->where('type', 'defendant')
                    ->map(fn($mp) => $mp->party?->name ?? '—')
                    ->join(' | ') ?: '—'
                ),

            ExportColumn::make('distributed_at')
                ->label(__('Distributed At')),

            ExportColumn::make('initial_report_at')
                ->label(__('Initial Report Date')),

            ExportColumn::make('final_report_at')
                ->label(__('Final Report Date')),

            ExportColumn::make('total_fees')
                ->label(__('Total Fees Matter'))
                ->getStateUsing(fn($record) => number_format($record->total_fees ?? 0, 2)),

            ExportColumn::make('divided_fees')
                ->label(__('Fees Divided by Assistants'))
                ->getStateUsing(fn($record) => number_format(
                    ($record->assistants_count > 0) ? ($record->total_fees / $record->assistants_count) : 0, 2
                )),

            ExportColumn::make('total_collected')
                ->label(__('Total Collected (excl. VAT)'))
                ->getStateUsing(fn($record) => number_format($record->total_allocations ?? 0, 2)),

            ExportColumn::make('collected_divided_fees')
                ->label(__('Collected Fees Divided by Assistants'))
                ->getStateUsing(fn($record) => number_format(
                    ($record->assistants_count > 0) ? ($record->total_allocations / $record->assistants_count) : 0, 2
                )),

            ExportColumn::make('notes')
                ->label(__('Notes'))
                ->getStateUsing(fn($record) => $record->notes
                    ->map(fn($note) => $note->text)->filter()->join(' | ') ?: '—'
                ),
        ];
    }

    public function getXlsxWriterOptions(): ?Options
    {
        $options = new Options();

        $options->setColumnWidth(15, 1);  // Matter
        $options->setColumnWidth(22, 2);  // Court
        $options->setColumnWidth(18, 3);  // Type
        $options->setColumnWidth(15, 4);  // Status
        $options->setColumnWidth(15, 5);  // Difficulty
        $options->setColumnWidth(30, 6);  // Experts
        $options->setColumnWidth(25, 7);  // Assistant
        $options->setColumnWidth(40, 8);  // Plaintiffs
        $options->setColumnWidth(40, 9);  // Defendants
        $options->setColumnWidth(18, 10); // Distributed At
        $options->setColumnWidth(18, 11); // Initial Report Date
        $options->setColumnWidth(18, 12); // Final Report Date
        $options->setColumnWidth(22, 13); // Total Fees Matter
        $options->setColumnWidth(22, 14); // Fees Divided by Assistants
        $options->setColumnWidth(22, 15); // Total Collected (excl. VAT)
        $options->setColumnWidth(22, 16); // Collected Fees Divided by Assistants
        $options->setColumnWidth(50, 17); // Notes
        return $options;
    }
    public static function modifyQuery(Builder $query): Builder
    {
        return $query
            ->whereHas('matterParties', fn($q) => $q
                ->where('role', 'expert')
                ->where('type', 'assistant')
            )
            ->withSum(['fees as total_fees' => function ($q) {
                $q->where('type', '!=', FeeType::VAT->value);
            }], 'amount')
            ->withSum(['allocations as total_allocations' => function ($q) {
                $q->whereHas('fee', function ($f) {
                    $f->where('type', '!=', FeeType::VAT->value);
                });
            }], 'amount')
            ->withCount(['assistantsOnly as assistants_count'])
            ->with([
                'court',
                'type',
                'notes',
                'mainExpertsOnly',
                'matterParties' => fn($q) => $q->with('party'),
            ]);
    }

    public function getXlsxCellStyle(): ?Style
    {
        return new Style()
            ->setShouldWrapText()
            ->setFontSize(11)
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
        $sheet->setName('Assistant Matters');

        return $writer;
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        dd($export);
        $body = trans_choice('export_completed', $export->successful_rows, ['count' => Number::format($export->successful_rows)]);

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . trans_choice('export_failed', $failedRowsCount, ['count' => Number::format($failedRowsCount)]);

        }

        return $body;
    }

    public function getFileName(Export $export): string
    {
        return 'Assistant Matters Report-' . date('Y-m-d_H-i-s');
    }
}
