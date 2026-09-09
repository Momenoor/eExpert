<?php

namespace App\Filament\Resources\PayrollRuns\Schemas;

use App\Models\PayrollRun;
use App\Services\PayrollRunService;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PayrollRunInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Summary'))
                    ->schema([
                        TextEntry::make('period')->label(__('Period')),
                        TextEntry::make('status')->label(__('Status'))->badge(),
                        TextEntry::make('employees')
                            ->label(__('Employees'))
                            ->state(fn (PayrollRun $record): int => self::totals($record)['employees']),
                        TextEntry::make('gross')
                            ->label(__('Gross (AED)'))
                            ->state(fn (PayrollRun $record): string => number_format(self::totals($record)['gross'], 2)),
                        TextEntry::make('deductions')
                            ->label(__('Deductions (AED)'))
                            ->state(fn (PayrollRun $record): string => number_format(self::totals($record)['deductions'], 2)),
                        TextEntry::make('net')
                            ->label(__('Net Payable (AED)'))
                            ->weight('bold')
                            ->state(fn (PayrollRun $record): string => number_format(self::totals($record)['net'], 2)),
                        TextEntry::make('eosg')
                            ->label(__('Gratuity Accrued (AED)'))
                            // Shown apart from the deductions because it is not
                            // one: the employer owes it, the employee does not
                            // pay it, and it never touches the bank transfer.
                            ->helperText(__('Employer cost — not deducted from employees.'))
                            ->state(fn (PayrollRun $record): string => number_format(self::totals($record)['eosg'], 2)),
                    ])->columns(4),

                Section::make(__('Approvals'))
                    ->schema([
                        TextEntry::make('hrApprover.display_name')
                            ->label(__('HR Approved By'))
                            ->placeholder('—'),
                        TextEntry::make('hr_approved_at')
                            ->label(__('HR Approved At'))
                            ->dateTime()
                            ->placeholder('—'),
                        TextEntry::make('financeApprover.display_name')
                            ->label(__('Finance Approved By'))
                            ->placeholder('—'),
                        TextEntry::make('finance_approved_at')
                            ->label(__('Finance Approved At'))
                            ->dateTime()
                            ->placeholder('—'),
                        TextEntry::make('disbursed_at')
                            ->label(__('Disbursed At'))
                            ->dateTime()
                            ->placeholder('—'),
                        TextEntry::make('notes')
                            ->label(__('Notes'))
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ])->columns(4),
            ]);
    }

    /**
     * Totals for the run, computed once per render.
     *
     * Seven entries each asking the database for its own aggregate would be
     * seven queries for one row of numbers.
     *
     * @return array{employees: int, gross: float, deductions: float, net: float, eosg: float}
     */
    private static function totals(PayrollRun $record): array
    {
        static $cache = [];

        return $cache[$record->getKey()] ??= app(PayrollRunService::class)->totals($record);
    }
}
