<?php

namespace App\Filament\Resources\PayrollRuns\Tables;

use App\Enums\PayrollRunStatus;
use App\Models\PayrollRun;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PayrollRunsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query
                ->withCount('payslips')
                ->withSum('payslips', 'gross')
                ->withSum('payslips', 'net_pay'))
            ->columns([
                TextColumn::make('period')
                    ->label(__('Period'))
                    ->sortable(),
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge(),
                TextColumn::make('payslips_count')
                    ->label(__('Employees')),
                TextColumn::make('payslips_sum_gross')
                    ->label(__('Gross (AED)'))
                    ->numeric(decimalPlaces: 2)
                    ->placeholder('—'),
                TextColumn::make('payslips_sum_net_pay')
                    ->label(__('Net Payable (AED)'))
                    ->numeric(decimalPlaces: 2)
                    ->weight('bold')
                    ->placeholder('—'),
                TextColumn::make('disbursed_at')
                    ->label(__('Disbursed'))
                    ->dateTime()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('period', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(PayrollRunStatus::class)
                    ->multiple(),
            ])
            ->recordActions([
                ViewAction::make()->iconButton(),
                DeleteAction::make()
                    ->iconButton()
                    // Only an untouched draft may be removed. Anything HR has
                    // seen is a record of a review, whatever came of it.
                    ->visible(fn (PayrollRun $record): bool => $record->status === PayrollRunStatus::DRAFT),
            ])
            ->emptyStateHeading(__('No payroll runs yet'))
            ->emptyStateActions([
                CreateAction::make()->label(__('New Payroll Run')),
            ]);
    }
}
