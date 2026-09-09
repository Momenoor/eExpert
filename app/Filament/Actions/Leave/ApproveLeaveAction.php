<?php

namespace App\Filament\Actions\Leave;

use App\Enums\LeaveType;
use App\Filament\Concerns\PayrollRefresh;
use App\Models\LeaveRequest;
use App\Services\LeaveEntitlementService;
use App\Services\LeaveRequestService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Approving a request and classifying it are the same act.
 *
 * The employee asked for dates; this form is where the office says what those
 * dates cost — which days come out of the annual balance, which are casual,
 * which are unpaid. That decision lands here rather than on the submission form
 * because it depends on balances the employee cannot see, and because it changes
 * their pay.
 */
class ApproveLeaveAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'approve_leave';
    }

    public function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('Approve'))
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->modalHeading(__('Approve Leave Request'))
            ->modalDescription(__('Say how the absence divides across leave types. Approved days are written to the leave ledger and count against payroll and monthly quotas.'))
            ->modalSubmitActionLabel(__('Approve'))
            // authorize() is a server-side check, unlike visible(), which only
            // hides the button. Both are here on purpose: the first decides, the
            // second keeps a pending-only action off decided rows.
            ->authorize('approve')
            ->visible(fn (LeaveRequest $record): bool => $record->isPending())
            ->fillForm(fn (LeaveRequest $record): array => [
                'periods' => app(LeaveRequestService::class)->suggestSplit($record),
            ])
            ->action(function (LeaveRequest $record, array $data, Action $action, $livewire): void {
                try {
                    app(LeaveRequestService::class)->approve(
                        $record,
                        auth()->user(),
                        $data['periods'] ?? [],
                        $data['approved_comment'] ?? null,
                    );
                } catch (RuntimeException $exception) {
                    // The service refuses a split that does not describe the
                    // absence requested. That refusal is information the
                    // approver needs, not a crash: halt and keep the modal open
                    // with their work still in it.
                    Notification::make()
                        ->danger()
                        ->title(__('Could not approve'))
                        ->body($exception->getMessage())
                        ->send();

                    $action->halt();
                }

                Notification::make()
                    ->success()
                    ->title(__('Leave request approved.'))
                    ->send();

                // Approval writes rows into the leave ledger, which the vacation
                // calendar and the payroll engine both read.
                $livewire->dispatch(PayrollRefresh::EVENT);
            });
    }

    public function getSchema(Schema $schema): Schema
    {
        return $schema->components([
            Placeholder::make('requested')
                ->label(__('Requested'))
                ->content(fn (LeaveRequest $record): string => __(':days days, :from to :to', [
                    'days' => number_format($record->requestedDays(), 1)
                            |> (fn ($x) => rtrim($x, '0'))
                            |> (fn ($x) => rtrim($x, '.')),
                    'from' => $record->start_date->translatedFormat('d M Y'),
                    'to' => $record->end_date->translatedFormat('d M Y'),
                ])),

            // The balances the decision turns on, put in front of the person
            // making it. Without them the approver is choosing a leave type
            // blind and finding out later that the annual balance was already
            // spent.
            Placeholder::make('balances')
                ->label(__('Balances'))
                ->content(fn (LeaveRequest $record): string => self::describeBalances($record)),

            Repeater::make('periods')
                ->label(__('Leave Breakdown'))
                ->minItems(1)
                ->schema([
                    Select::make('leave_type')
                        ->label(__('Leave Type'))
                        ->options(LeaveType::class)
                        ->default(LeaveType::ANNUAL->value)
                        ->required()
                        ->live()
                        ->helperText(fn ($state) => $state === null
                            ? null
                            : self::payDescription($state)),
                    DatePicker::make('start_date')
                        ->label(__('From'))
                        ->required()
                        // Bounded by the request itself, so the calendar cannot
                        // offer a day the employee never asked for.
                        ->minDate(fn (LeaveRequest $record) => $record->start_date)
                        ->maxDate(fn (LeaveRequest $record) => $record->end_date)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Get $get, Set $set) => self::recountDays($get, $set)),
                    DatePicker::make('end_date')
                        ->label(__('To'))
                        ->required()
                        ->afterOrEqual('start_date')
                        ->minDate(fn (LeaveRequest $record) => $record->start_date)
                        ->maxDate(fn (LeaveRequest $record) => $record->end_date)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Get $get, Set $set) => self::recountDays($get, $set)),
                    TextInput::make('day_count')
                        ->label(__('Days'))
                        ->numeric()
                        ->step(0.5)
                        ->minValue(0.5)
                        ->required()
                        // Counted from the dates but left editable, so a half day
                        // is expressible without a separate half-day control.
                        ->helperText(__('Use 0.5 for a half day.')),
                ])
                ->columns(4)
                ->itemLabel(fn (array $state): ?string => filled($state['leave_type'] ?? null)
                    ? $state['leave_type']->getLabel()
                    : null)
                ->columnSpanFull(),

            Textarea::make('approved_comment')
                ->label(__('Reviewer Comment'))
                ->rows(2),
        ]);
    }

    /**
     * The employee's remaining entitlements for the service year in question.
     */
    private static function describeBalances(LeaveRequest $record): string
    {
        $entitlements = app(LeaveEntitlementService::class);
        $entitlement = $entitlements->forDate($record->party, $record->start_date);

        $sickFullLeft = max(0.0, $entitlements->sickFullDays() - (float) $entitlement->sick_full_taken);
        $sickHalfLeft = max(0.0, $entitlements->sickHalfDays() - (float) $entitlement->sick_half_taken);

        return __('Annual remaining: :annual · Sick at full pay: :full · Sick at half pay: :half', [
            'annual' => number_format($entitlement->annualRemaining(), 1)
                    |> (fn ($x) => rtrim($x, '0'))
                    |> (fn ($x) => rtrim($x, '.')),
            'full' => number_format($sickFullLeft, 1)
                    |> (fn ($x) => rtrim($x, '0'))
                    |> (fn ($x) => rtrim($x, '.')),
            'half' => number_format($sickHalfLeft, 1)
                    |> (fn ($x) => rtrim($x, '0'))
                    |> (fn ($x) => rtrim($x, '.')),
        ]);
    }

    /**
     * Recompute a period's day count from its dates.
     */
    private static function recountDays(Get $get, Set $set): void
    {
        $start = $get('start_date');
        $end = $get('end_date');

        if (blank($start) || blank($end)) {
            return;
        }

        $days = Carbon::parse($start)->startOfDay()->diffInDays(Carbon::parse($end)->startOfDay()) + 1;

        $set('day_count', max(0.5, $days));
    }

    private static function payDescription(LeaveType $type): string
    {
        return match (true) {
            $type->payFactor() >= 1.0 => __('Paid in full.'),
            $type->payFactor() > 0.0 => __('Paid at half rate; the balance is deducted.'),
            default => __('Unpaid; deducted at basic salary ÷ 30 per day.'),
        };
    }
}
