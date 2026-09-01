<?php

namespace App\Filament\Exports;

use App\Models\Matter;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Common\Exception\InvalidArgumentException;
use OpenSpout\Writer\Common\Manager\Style\StyleMerger;

class MattersCompletingDataExporter extends Exporter
{
    protected static ?string $model = Matter::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),
            ExportColumn::make('year'),
            ExportColumn::make('number'),
            ExportColumn::make('commissioning'),
            ExportColumn::make('distributed_at'),
            ExportColumn::make('next_session_date'),
            ExportColumn::make('received_at'),
            ExportColumn::make('initial_report_at'),
            ExportColumn::make('final_report_at'),
            ExportColumn::make('court.name'),
            ExportColumn::make('experts')
                ->getStateUsing(fn ($record) => $record->indexedExperts
                    ->map(fn ($mp) => sprintf(
                        '%s #%d — %s',
                        __($mp->type ? ucfirst(str_replace('-', ' ', $mp->type)) : ''),
                        $mp->role_index,
                        $mp->party?->name ?? '—' // ⚠️ double-check this — should it be $mp->expert->name or $mp->party->name?
                    ))
                    ->join("\n")
                ),
            ExportColumn::make('level'),
            ExportColumn::make('type.name'),
            ExportColumn::make('custom_fields'),
            ExportColumn::make('parent.id'),
            ExportColumn::make('created_at'),
            ExportColumn::make('updated_at'),
            ExportColumn::make('deleted_at'),
            ExportColumn::make('difficulty'),
            ExportColumn::make('collection_status'),
            ExportColumn::make('review_count'),
            ExportColumn::make('has_substantive_changes'),
            ExportColumn::make('has_court_penalty'),
            ExportColumn::make('final_report_memo_date'),
            ExportColumn::make('fees_exists')
                ->exists('fees'),
        ];
    }

    public function makeXlsxRow(array $values, ?Style $style = null): Row
    {
        $styleMerger = new StyleMerger;
        $cells = [];
        foreach (array_keys($this->columnMap) as $columnIndex => $column) {
            $cellStyle = match ($column) {
                'final_report_at', 'final_report_memo_date', 'initial_report_at', 'distributed_at', 'received_at' => $values[$columnIndex] == null
                    ? $styleMerger->merge(new Style()->setBackgroundColor(Color::RED), $style)
                    : $style,

                'fees_exists' => $values[$columnIndex] != 1
                    ? $styleMerger->merge(new Style()->setBackgroundColor(Color::RED), $style)
                    : $style,

                default => $style,
            };

            $cells[] = Cell::fromValue($values[$columnIndex], $cellStyle);
        }

        return new Row($cells, $style);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function getXlsxCellStyle(): ?Style
    {
        return new Style()
            ->setFontSize(11)
            ->setFontName('Calibri')
            ->setCellAlignment(CellAlignment::JUSTIFY)
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function getXlsxHeaderCellStyle(): ?Style
    {
        return new Style()
            ->setFontBold()
            ->setFontItalic()
            ->setFontSize(11)
            ->setFontName('Calibri')
            ->setCellAlignment(CellAlignment::JUSTIFY)
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER);
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your matter export has completed and '.Number::format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
