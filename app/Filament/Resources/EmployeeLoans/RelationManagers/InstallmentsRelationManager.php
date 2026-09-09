<?php

namespace App\Filament\Resources\EmployeeLoans\RelationManagers;

use App\Filament\Concerns\RefreshesPayrollData;
use App\Models\LoanInstallment;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * The instalment schedule, read-only.
 *
 * Amounts are frozen when the schedule is built. Editing one here would let a
 * later change shift every remaining instalment without the total moving, which
 * is the sort of drift that leaves a loan never quite closing.
 */
class InstallmentsRelationManager extends RelationManager
{
    use RefreshesPayrollData;

    protected static string $relationship = 'installments';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Instalments');
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('seq')
                    ->label(__('No.'))
                    ->sortable(),
                TextColumn::make('due_period')
                    ->label(__('Due Period'))
                    ->sortable(),
                TextColumn::make('amount')
                    ->label(__('Amount (AED)'))
                    ->numeric(decimalPlaces: 2)
                    ->summarize(Sum::make()->label(__('Total'))),
                TextColumn::make('payslip_id')
                    ->label(__('Status'))
                    ->badge()
                    ->state(fn (LoanInstallment $record): string => $record->payslip_id === null
                        ? __('Outstanding')
                        : __('Deducted'))
                    ->color(fn (LoanInstallment $record): string => $record->payslip_id === null
                        ? 'warning'
                        : 'success'),
            ])
            ->defaultSort('seq')
            ->paginated(false)
            ->emptyStateHeading(__('No schedule built yet'));
    }
}
