<?php

namespace App\Http\Controllers;

use App\Mail\LeaveRequestDecisionMail;
use App\Models\LeaveRequest;
use App\Services\LeaveRequestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * Approve/reject a leave request from the email link itself, with no login.
 *
 * The `signed` route middleware is the only authentication — mirrors the
 * existing matter received-date accept/dispute flow. Because there is no form
 * behind either button, approving here always takes the whole request as one
 * block of whatever type the employee stated when filing (or annual leave, if
 * they didn't); anyone who needs a different split still has the full
 * approval modal in the panel.
 *
 * Each action is GET-to-confirm, POST-to-execute, on the very same signed URL
 * — never a plain GET that mutates. A bare GET that decides something the
 * instant it's requested is silently triggered by mail security scanners
 * (Outlook's Safe Links and equivalents visit every link in an email to check
 * it, often before the recipient ever opens the message), so a request would
 * get approved or rejected by a robot before anyone actually clicked. The GET
 * here only ever renders a page with one button; only the POST that button
 * submits changes anything.
 */
class LeaveRequestEmailActionController
{
    public function approve(Request $request, LeaveRequest $leaveRequest, LeaveRequestService $service)
    {
        if (! $leaveRequest->isPending()) {
            return $this->alreadyUsed($leaveRequest);
        }

        if ($request->isMethod('get')) {
            return $this->confirm($leaveRequest, 'approve');
        }

        $service->approve(
            $leaveRequest,
            null,
            $service->suggestSplit($leaveRequest),
            __('Approved by email.'),
        );

        $this->notifyRequester($leaveRequest->fresh());

        return view('mails.leave-request-response', [
            'type' => 'approved',
            'leaveRequest' => $leaveRequest,
            'message' => __('Leave request approved.'),
        ]);
    }

    public function reject(Request $request, LeaveRequest $leaveRequest, LeaveRequestService $service)
    {
        if (! $leaveRequest->isPending()) {
            return $this->alreadyUsed($leaveRequest);
        }

        if ($request->isMethod('get')) {
            return $this->confirm($leaveRequest, 'reject');
        }

        $service->reject($leaveRequest, null, __('Rejected by email.'));

        $this->notifyRequester($leaveRequest->fresh());

        return view('mails.leave-request-response', [
            'type' => 'rejected',
            'leaveRequest' => $leaveRequest,
            'message' => __('Leave request rejected.'),
        ]);
    }

    private function confirm(LeaveRequest $leaveRequest, string $action)
    {
        return view('mails.leave-request-confirm', [
            'leaveRequest' => $leaveRequest,
            'action' => $action,
        ]);
    }

    private function notifyRequester(LeaveRequest $leaveRequest): void
    {
        $email = $leaveRequest->party->email;

        if (blank($email)) {
            return;
        }

        Mail::to($email)
            ->locale('ar')
            ->queue(new LeaveRequestDecisionMail($leaveRequest));
    }

    private function alreadyUsed(LeaveRequest $leaveRequest)
    {
        return view('mails.leave-request-response', [
            'type' => 'error',
            'leaveRequest' => $leaveRequest,
            'message' => __('This link has already been used. The request status is: ').$leaveRequest->status->getLabel(),
        ]);
    }
}
