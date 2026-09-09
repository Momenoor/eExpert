<?php

namespace App\Filament\Resources\LeaveRequests\Pages;

use App\Filament\Resources\LeaveRequests\LeaveRequestResource;
use App\Models\LeaveRequest;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLeaveRequest extends EditRecord
{
    protected static string $resource = LeaveRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * The same substitution the create page makes.
     *
     * Record resolution already runs through the resource's scoped query, so an
     * employee can only reach their own request here — but party_id is still a
     * (disabled) field on the form, and guarding one page and not the other
     * would leave the pair disagreeing about who is allowed to set it.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! auth()->user()?->can('manageOthers', LeaveRequest::class)) {
            $ownParty = auth()->user()?->party?->getKey();

            if ($ownParty !== null) {
                $data['party_id'] = $ownParty;
            }
        }

        return $data;
    }

    /**
     * A decided request is a record of a decision, not a draft.
     *
     * Editing one after approval would leave the leave ledger describing days
     * the request no longer claims.
     */
    public function mount(int|string $record): void
    {
        parent::mount($record);

        /** @var LeaveRequest $leaveRequest */
        $leaveRequest = $this->getRecord();

        if (! $leaveRequest->isPending()) {
            $this->redirect(static::getResource()::getUrl('index'));
        }
    }
}
