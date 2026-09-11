<?php

namespace App\Mail;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the requester once their leave request has been approved or
 * rejected, whether that decision came from the panel or from an office
 * email's one-click link.
 */
class LeaveRequestDecisionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public LeaveRequest $leaveRequest) {}

    public function envelope(): Envelope
    {
        $statusLabel = $this->leaveRequest->status->getLabel();

        return new Envelope(
            subject: __('Leave Request').' — '.$statusLabel,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mails.leave-request-decision');
    }
}
