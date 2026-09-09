<?php

namespace App\Filament\Resources\EmployeeLoans\Tables;

use App\Enums\LoanKind;
use App\Enums\LoanStatus;
use App\Filament\Concerns\PayrollRefresh;
use App\Models\EmployeeLoan;
use App\Services\LoanScheduleService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EmployeeLoansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['party', 'installments']))
            ->columns([
                TextColumn::make('party.name')
                    ->label(__('Employee'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('kind')
                    ->label(__('Type'))
                    ->badge(),
                TextColumn::make('principal')
                    ->label(__('Amount (AED)'))
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                TextColumn::make('months')
                    ->label(__('Months')),
                TextColumn::make('outstanding')
                    ->label(__('Outstanding (AED)'))
                    // Summed from instalments no payslip has taken yet, rather
                    // than tracked as a running balance: a figure that is derived
                    // cannot drift out of step with the schedule behind it.
                    ->state(fn (EmployeeLoan $record): float => (float) $record->installments
                        ->whereNull('payslip_id')->sum('amount'))
                    ->numeric(decimalPlaces: 2),
                TextColumn::make('starts_on')
                    ->label(__('First Instalment'))
                    ->date()
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(LoanStatus::class)
                    ->default(LoanStatus::ACTIVE->value),
                SelectFilter::make('kind')
                    ->label(__('Type'))
                    ->options(LoanKind::class),
                SelectFilter::make('party_id')
                    ->label(__('Employee'))
                    ->relationship('party', 'name')
                    ->searchable(),
            ])
            ->recordActions([
                Action::make('generate_schedule')
                    ->label(__('Build Schedule'))
                    ->icon('heroicon-o-calculator')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription(__('Replaces the existing instalments. Only possible while nothing has been deducted.'))
                    ->authorize('update')
                    // Hidden once payroll has taken an instalment. The service
                    // refuses in that case anyway; this keeps the operator from
                    // meeting the refusal as an error message.
                    ->visible(fn (EmployeeLoan $record): bool => $record->isEditable())
                    ->action(function (EmployeeLoan $record, $livewire): void {
                        $schedule = app(LoanScheduleService::class)->generateFor($record);

                        Notification::make()
                            ->success()
                            ->title(__('Schedule built'))
                            ->body(__(':count instalments created.', ['count' => count($schedule)]))
                            ->send();

                        // The outstanding column here and the instalments
                        // relation manager both just changed.
                        $livewire->dispatch(PayrollRefresh::EVENT);
                    }),
                // Always available, unlike Edit — a part-recovered advance still
                // has a schedule worth reading.
                ViewAction::make()->iconButton(),
                EditAction::make()
                    ->iconButton()
                    ->visible(fn (EmployeeLoan $record): bool => $record->isEditable()),
                DeleteAction::make()
                    ->iconButton()
                    ->visible(fn (EmployeeLoan $record): bool => $record->isEditable()),
            ])
            ->emptyStateHeading(__('No loans or advances'))
            ->emptyStateActions([
                CreateAction::make()->label(__('New Advance')),
            ]);
    }
}
