<?php

namespace App\Filament\Resources\PayrollRuns\Pages;

use App\Enums\PayrollRunStatus;
use App\Filament\Concerns\RefreshesPayrollData;
use App\Filament\Resources\PayrollRuns\PayrollRunResource;
use App\Models\PayrollRun;
use App\Services\PayrollJournalVoucherService;
use App\Services\PayrollRunService;
use App\Services\PayrollService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\View\View;
use LogicException;
use Throwable;

class ViewPayrollRun extends ViewRecord
{
    use RefreshesPayrollData;

    protected static string $resource = PayrollRunResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->generateAction(),
            $this->submitAction(),
            $this->hrApproveAction(),
            $this->financeApproveAction(),
            $this->disburseAction(),
            $this->journalVoucherAction(),
            $this->printJournalVoucherAction(),
            $this->printSalaryAuthorizationFormAction(),
            $this->returnToDraftAction(),
        ];
    }

    /**
     * The run this page is showing.
     *
     * getRecord() is declared as a bare Model, so the narrowing happens once
     * here rather than at each of the seven actions below.
     */
    private function run(): PayrollRun
    {
        $record = $this->getRecord();

        if (! $record instanceof PayrollRun) {
            throw new LogicException('This page only shows a payroll run.');
        }

        return $record;
    }

    private function generateAction(): Action
    {
        return Action::make('generate')
            ->label(__('Generate Payslips'))
            ->icon('heroicon-o-calculator')
            ->color('primary')
            ->requiresConfirmation()
            ->modalDescription(__('Rebuilds every payslip from current salaries, approved leave and loan schedules. Hand-entered incentives and deductions are kept.'))
            ->authorize('generate')
            ->visible(fn (): bool => $this->run()->isEditable())
            ->action(function (): void {
                $service = app(PayrollService::class);

                $payslips = $service->generate($this->run());
                $skipped = $service->skippedEmployees($this->run());

                // Who was left out, and why. An employee marked as staff but
                // with no profile or no basic salary is skipped silently by the
                // engine, and a run that quietly omits someone looks identical
                // to a correct one until payday.
                $body = __(':count employees included.', ['count' => $payslips->count()]);

                if ($skipped->isNotEmpty()) {
                    $body .= ' '.__(':count skipped:', ['count' => $skipped->count()]).' '
                        .$skipped->map(fn (array $row): string => $row['party']->name.' — '.$row['reason'])
                            ->implode('; ');
                }

                // An incentive month closing inside this payroll month that pays
                // nobody is far more likely to be an unfinalised draft than a
                // month in which nobody earned anything — so say which.
                $pending = $service->pendingIncentiveCalculations($this->run());

                if ($pending->isNotEmpty()) {
                    $body .= ' '.__('Incentives were not included: :names ends in this period but is not finalised.', [
                        'names' => $pending->pluck('name')->implode(', '),
                    ]);
                }

                $clean = $skipped->isEmpty() && $pending->isEmpty();

                Notification::make()
                    ->{$clean ? 'success' : 'warning'}()
                    ->title(__('Payslips generated'))
                    ->body($body)
                    ->persistent()
                    ->send();

                $this->dispatchPayrollDataUpdated();
            });
    }

    private function submitAction(): Action
    {
        return Action::make('submit_for_review')
            ->label(__('Send to HR Review'))
            ->icon('heroicon-o-paper-airplane')
            ->color('warning')
            ->requiresConfirmation()
            ->authorize('update')
            ->visible(fn (): bool => $this->run()->status === PayrollRunStatus::DRAFT)
            ->action(fn () => $this->runLadderStep(
                fn (PayrollRunService $service) => $service->submitForReview($this->run()),
                __('Sent for HR review.'),
            ));
    }

    private function hrApproveAction(): Action
    {
        return Action::make('hr_approve')
            ->label(__('HR Approve'))
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription(__('Confirms the days, leave and deductions are correct, and passes the run to Finance.'))
            ->authorize('hrApprove')
            ->visible(fn (): bool => $this->run()->status === PayrollRunStatus::HR_REVIEW)
            ->action(fn () => $this->runLadderStep(
                fn (PayrollRunService $service) => $service->hrApprove($this->run(), auth()->user()),
                __('Approved by HR.'),
            ));
    }

    private function financeApproveAction(): Action
    {
        return Action::make('finance_approve')
            ->label(__('Finance Approve'))
            ->icon('heroicon-o-shield-check')
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription(__('Releases the run for payment.'))
            ->authorize('financeApprove')
            ->visible(fn (): bool => $this->run()->status === PayrollRunStatus::FINANCE_APPROVAL)
            ->action(fn () => $this->runLadderStep(
                fn (PayrollRunService $service) => $service->financeApprove($this->run(), auth()->user()),
                __('Approved by Finance.'),
            ));
    }

    private function disburseAction(): Action
    {
        return Action::make('disburse')
            ->label(__('Mark Disbursed'))
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription(__('Records that the transfers went out. Gratuity accruals are booked and fully recovered loans are closed. This cannot be undone.'))
            ->authorize('disburse')
            ->visible(fn (): bool => $this->run()->status === PayrollRunStatus::APPROVED)
            ->action(fn () => $this->runLadderStep(
                fn (PayrollRunService $service) => $service->disburse($this->run()),
                __('Run marked as disbursed.'),
            ));
    }

    private function returnToDraftAction(): Action
    {
        return Action::make('return_to_draft')
            ->label(__('Return to Draft'))
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription(__('Clears the approvals so the figures can be corrected. Approvals cannot carry across a recalculation.'))
            ->authorize('update')
            ->visible(fn (): bool => ! in_array(
                $this->run()->status,
                [PayrollRunStatus::DRAFT, PayrollRunStatus::DISBURSED],
                true,
            ))
            ->action(fn () => $this->runLadderStep(
                fn (PayrollRunService $service) => $service->returnToDraft($this->run()),
                __('Returned to draft.'),
            ));
    }

    private function journalVoucherAction(): Action
    {
        return Action::make('journal_voucher')
            ->label(__('Journal Voucher'))
            ->icon('heroicon-o-document-text')
            ->color('gray')
            ->authorize('viewJournalVoucher')
            ->visible(fn (): bool => $this->run()->payslips()->exists())
            ->modalHeading(fn (): string => __('Journal Voucher — :period', ['period' => $this->run()->period]))
            ->modalContent(fn (): View => view('filament.payroll.journal-voucher', [
                'voucher' => app(PayrollJournalVoucherService::class)->forRun($this->run()),
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('Close'))
            ->modalWidth('4xl');
    }

    /**
     * The same voucher as a printable sheet, in its own tab.
     *
     * The modal is for checking the figures on screen; this is what gets signed
     * and filed beside the bank transfer.
     */
    private function printJournalVoucherAction(): Action
    {
        return Action::make('print_journal_voucher')
            ->label(__('Print Voucher'))
            ->icon('heroicon-o-printer')
            ->color('gray')
            ->authorize('viewJournalVoucher')
            ->visible(fn (): bool => $this->run()->payslips()->exists())
            ->url(fn (): string => route('payroll.run.journal-voucher.print', $this->run()))
            ->openUrlInNewTab();
    }

    /**
     * The bank's own salary-upload form — a different sheet from the journal
     * voucher, carrying IBANs per employee rather than GL accounts, for
     * signing and sending straight to the exchange house.
     */
    private function printSalaryAuthorizationFormAction(): Action
    {
        return Action::make('print_salary_authorization_form')
            ->label(__('Print Salary Form'))
            ->icon('heroicon-o-printer')
            ->color('gray')
            ->authorize('viewJournalVoucher')
            ->visible(fn (): bool => $this->run()->payslips()->exists())
            ->url(fn (): string => route('payroll.run.salary-authorization-form.print', $this->run()))
            ->openUrlInNewTab();
    }

    /**
     * Run a ladder transition, turning its refusal into a notification.
     *
     * The service throws rather than returning false, so that a caller which
     * forgets to check cannot silently skip a rung. Here that exception is the
     * message the operator needs to see.
     *
     * Not named transition(): Livewire\Component already declares a public method
     * by that name, and redeclaring it privately is a fatal error.
     */
    private function runLadderStep(callable $step, string $success): void
    {
        try {
            $step(app(PayrollRunService::class));
        } catch (Throwable $exception) {
            Notification::make()
                ->danger()
                ->title(__('Could not continue'))
                ->body($exception->getMessage())
                ->send();

            return;
        }

        Notification::make()->success()->title($success)->send();

        // The status just changed, so the header actions, the summary totals and
        // the payslips table are all describing the previous state.
        $this->dispatchPayrollDataUpdated();
    }
}
