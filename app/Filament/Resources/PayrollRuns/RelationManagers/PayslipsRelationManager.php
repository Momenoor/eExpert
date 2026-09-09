<?php

namespace App\Filament\Resources\PayrollRuns\RelationManagers;

use App\Filament\Concerns\RefreshesPayrollData;
use App\Models\LoanInstallment;
use App\Models\PayrollRun;
use App\Models\Payslip;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * The payslips in a run.
 *
 * Two figures are editable and only while the run is a draft: the monthly
 * incentive and a manual deduction. Everything else is calculated, and both
 * survive a regeneration — an accountant who types a 500 fine into the draft
 * should not lose it the next time anyone presses Generate.
 */
class PayslipsRelationManager extends RelationManager
{
    use RefreshesPayrollData;

    protected static string $relationship = 'payslips';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Payslips');
    }

    /**
     * The run these payslips belong to.
     *
     * getOwnerRecord() is declared as a bare Model; narrowing once here keeps
     * the action closures working with a real type.
     */
    private function payrollRun(): PayrollRun
    {
        $record = $this->getOwnerRecord();

        if (! $record instanceof PayrollRun) {
            throw new LogicException('This relation manager only attaches to a payroll run.');
        }

        return $record;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('party'))
            ->columns([
                TextColumn::make('party.name')
                    ->label(__('Employee'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('basic_snapshot')
                    ->label(__('Basic'))
                    ->numeric(decimalPlaces: 2),
                TextColumn::make('allowances_snapshot')
                    ->label(__('Allowances'))
                    ->numeric(decimalPlaces: 2),
                TextColumn::make('incentive_amount')
                    ->label(__('Incentive'))
                    ->numeric(decimalPlaces: 2),
                TextColumn::make('gross')
                    ->label(__('Gross'))
                    ->numeric(decimalPlaces: 2)
                    ->summarize(Sum::make()->label(__('Total'))),
                TextColumn::make('unpaid_days')
                    ->label(__('Unpaid Days'))
                    ->numeric(decimalPlaces: 1),
                TextColumn::make('unpaid_deduction')
                    ->label(__('Leave Deduction'))
                    ->numeric(decimalPlaces: 2),
                TextColumn::make('loan_deduction')
                    ->label(__('Loan'))
                    ->numeric(decimalPlaces: 2)
                    // One figure cannot explain two concurrent advances plus an
                    // instalment carried over from a month with no payslip, and
                    // that is exactly what HR gets asked about.
                    ->action($this->loanBreakdownAction())
                    ->tooltip(fn (Payslip $record): ?string => $record->installments()->count() > 1
                        ? __('Click for the breakdown')
                        : null),
                TextColumn::make('manual_deduction')
                    ->label(__('Other Deduction'))
                    ->numeric(decimalPlaces: 2),
                TextColumn::make('net_pay')
                    ->label(__('Net Pay'))
                    ->numeric(decimalPlaces: 2)
                    ->weight('bold')
                    ->color(fn (Payslip $record): string => $record->needs_review ? 'danger' : 'success')
                    ->summarize(Sum::make()->label(__('Total'))),
                TextColumn::make('eosg_accrued')
                    ->label(__('Gratuity Accrued'))
                    ->numeric(decimalPlaces: 2)
                    ->summarize(Sum::make()->label(__('Total')))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('iban_snapshot')
                    ->label(__('IBAN'))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('net_pay', 'desc')
            ->filters([
                Filter::make('needs_review')
                    ->label(__('Flagged for Review'))
                    ->query(fn ($query) => $query->where('needs_review', true)),
            ])
            ->recordActions([
                Action::make('adjust')
                    ->label(__('Adjust'))
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn (): bool => $this->payrollRun()->isEditable())
                    ->authorize(fn (): bool => auth()->user()?->can('update', $this->payrollRun()) ?? false)
                    ->fillForm(fn (Payslip $record): array => [
                        'incentive_amount' => $record->incentive_amount,
                        'manual_deduction' => $record->manual_deduction,
                        'review_note' => $record->review_note,
                    ])
                    ->schema([
                        TextInput::make('incentive_amount')
                            ->label(__('Incentive (AED)'))
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            // Imported from the finalised incentive calculation
                            // closing in this period. Changing it here marks the
                            // payslip so the next Generate keeps your figure.
                            ->helperText(__('Imported from the incentive calculation. A change here survives regeneration.')),
                        TextInput::make('manual_deduction')
                            ->label(__('Other Deduction (AED)'))
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                        Textarea::make('review_note')
                            ->label(__('Note'))
                            ->rows(2),
                    ])
                    ->action(fn (Payslip $record, array $data) => $this->applyAdjustment($record, $data)),
            ])
            ->emptyStateHeading(__('No payslips yet'))
            ->emptyStateDescription(__('Generate the run to build payslips from salaries, leave and loans.'));
    }

    /**
     * The instalments behind one payslip's loan deduction.
     */
    private function loanBreakdownAction(): Action
    {
        return Action::make('loan_breakdown')
            ->label(__('Loan Breakdown'))
            ->modalHeading(fn (Payslip $record): string => __('Loan Breakdown — :employee', [
                'employee' => $record->party->name,
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('Close'))
            ->modalWidth('3xl')
            // Nothing was deducted, so there is nothing to open.
            ->visible(fn (Payslip $record): bool => (float) $record->loan_deduction > 0)
            ->modalContent(fn (Payslip $record): View => view('filament.payroll.loan-breakdown', [
                'installments' => $record->installments()->with('loan')->orderBy('due_period')->get(),
                'period' => $this->payrollRun()->period,
                // What these same advances still owe once this run is paid.
                'outstanding' => (float) LoanInstallment::query()
                    ->whereNull('payslip_id')
                    ->whereIn('employee_loan_id', $record->installments()->select('employee_loan_id'))
                    ->sum('amount'),
            ]));
    }

    /**
     * Apply a hand adjustment and re-derive the totals it affects.
     *
     * Gross and net are recomputed here rather than left to the next generation,
     * so the table never shows an incentive that the net pay beside it does not
     * include.
     */
    private function applyAdjustment(Payslip $record, array $data): void
    {
        $incentive = (float) ($data['incentive_amount'] ?? 0);
        $manual = (float) ($data['manual_deduction'] ?? 0);

        $gross = round(
            (float) $record->basic_snapshot + (float) $record->allowances_snapshot + $incentive,
            2,
        );

        $deductions = round(
            (float) $record->unpaid_deduction + (float) $record->loan_deduction + $manual,
            2,
        );

        $net = round($gross - $deductions, 2);

        $record->forceFill([
            'incentive_amount' => $incentive,
            // Only a figure that actually differs counts as an override; saving
            // the modal unchanged should not freeze the imported value.
            'incentive_overridden' => $record->incentive_overridden
                || abs($incentive - (float) $record->incentive_amount) >= 0.005,
            'manual_deduction' => $manual,
            'gross' => $gross,
            'total_deductions' => $deductions,
            'net_pay' => $net,
            'needs_review' => $net < 0,
            'review_note' => $data['review_note'] ?: ($net < 0
                ? __('Deductions exceed gross pay for this period.')
                : null),
        ])->save();

        // The run's summary totals sit on the parent page and have just moved.
        $this->dispatchPayrollDataUpdated();
    }
}
