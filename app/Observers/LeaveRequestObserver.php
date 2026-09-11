<?php

namespace App\Observers;

use App\Mail\LeaveRequestSubmittedMail;
use App\Models\LeaveRequest;
use Illuminate\Support\Facades\Mail;

/**
 * A new leave request is announced by email the moment it is filed — the
 * office reviews and decides from the inbox, via the one-click approve/reject
 * links the mail carries, rather than needing to be in the panel at all.
 */
class LeaveRequestObserver
{
    private const TO = 'redha@jpaemirates.com';

    private const CC = [
        'expert@jpaemirates.com',
        'momen.noor@jpaemirates.com',
        'info@jpaemirates.com',
    ];

    public function created(LeaveRequest $leaveRequest): void
    {
        Mail::to(self::TO)
            ->cc(self::CC)
            ->locale('ar')
            ->queue(new LeaveRequestSubmittedMail($leaveRequest));
    }
}
