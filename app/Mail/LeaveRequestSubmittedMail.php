<?php

namespace App\Mail;

use App\Models\LeaveRequest;
use App\Services\LeaveEntitlementService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * Sent to the office the moment a leave request is filed, carrying signed
 * approve/reject links that need no login — the signature on each URL is the
 * only authentication, exactly like the existing matter received-date flow.
 */
class LeaveRequestSubmittedMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $approveUrl;

    public string $rejectUrl;

    /**
     * The annual balance as it stood before this request — an approver reading
     * the email needs to see what is left, not just what was asked for.
     */
    public float $annualLeaveBalance;

    public function __construct(public LeaveRequest $leaveRequest)
    {
        $this->approveUrl = URL::signedRoute(
            'leave-request.email-action.approve',
            ['leaveRequest' => $leaveRequest->id],
            now()->addDays(7),
        );

        $this->rejectUrl = URL::signedRoute(
            'leave-request.email-action.reject',
            ['leaveRequest' => $leaveRequest->id],
            now()->addDays(7),
        );

        $this->annualLeaveBalance = app(LeaveEntitlementService::class)
            ->forDate($leaveRequest->party, $leaveRequest->start_date)
            ->annualRemaining();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('New Leave Request').' — '.$this->leaveRequest->party->name,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mails.leave-request-submitted');
    }
}
