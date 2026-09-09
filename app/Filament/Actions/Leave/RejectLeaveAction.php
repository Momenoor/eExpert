<?php

namespace App\Filament\Actions\Leave;

use App\Filament\Concerns\PayrollRefresh;
use App\Models\LeaveRequest;
use App\Services\LeaveRequestService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class RejectLeaveAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'reject_leave';
    }

    public function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('Reject'))
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(__('Reject Leave Request'))
            ->authorize('approve')
            ->visible(fn (LeaveRequest $record): bool => $record->isPending())
            ->successNotificationTitle(__('Leave request rejected.'))
            ->action(function (LeaveRequest $record, array $data, $livewire): void {
                app(LeaveRequestService::class)
                    ->reject($record, auth()->user(), $data['approved_comment']);

                // A rejection retracts anything that reached the leave ledger.
                $livewire->dispatch(PayrollRefresh::EVENT);
            });
    }

    public function getSchema(Schema $schema): Schema
    {
        return $schema->components([
            // Required, and the service enforces it again. A rejection with no
            // explanation is the commonest complaint about approval queues, and
            // the employee has to be told something.
            Textarea::make('approved_comment')
                ->label(__('Reason for Rejection'))
                ->required()
                ->rows(2),
        ]);
    }
}
