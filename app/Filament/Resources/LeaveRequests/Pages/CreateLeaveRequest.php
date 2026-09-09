<?php

namespace App\Filament\Resources\LeaveRequests\Pages;

use App\Enums\RequestStatus;
use App\Filament\Resources\LeaveRequests\LeaveRequestResource;
use App\Models\LeaveRequest;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateLeaveRequest extends CreateRecord
{
    protected static string $resource = LeaveRequestResource::class;

    /**
     * A new request always enters the queue pending, filed by whoever submitted
     * it, against a party that user is entitled to file for.
     *
     * None of the three is taken from the form. The status and the submitter are
     * not fields at all, and while `party_id` is a field it is disabled for
     * everyone but management — a disabled field still arrives in the request
     * payload, so the only place that restriction can actually be enforced is
     * here.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = RequestStatus::PENDING;
        $data['requested_by'] = auth()->id();

        if (! auth()->user()?->can('manageOthers', LeaveRequest::class)) {
            $ownParty = auth()->user()?->party?->getKey();

            if ($ownParty === null) {
                throw ValidationException::withMessages([
                    'party_id' => __('Your user account is not linked to an employee record, so you cannot file leave requests.'),
                ]);
            }

            $data['party_id'] = $ownParty;
        }

        return $data;
    }
}
