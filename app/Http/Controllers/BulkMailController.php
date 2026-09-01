<?php

namespace App\Http\Controllers;

use App\Mail\BulkMailMessage;
use App\Models\BulkMailCampaign;
use App\Models\BulkMailRecipient;
use Illuminate\Http\Request;

class BulkMailController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, BulkMailCampaign $campaign, BulkMailRecipient $recipient)
    {
        $mailable = new BulkMailMessage($campaign, $recipient);

        // Build the mailable to get the Symfony message

        $subject = $campaign->renderSubject($recipient);
        $html = $campaign->renderBody($recipient);
        $sender = $campaign->sender_config;
        $sentAt = $recipient->sent_at ?? now();
        $attachments = $campaign->attachment_path ?? [];
        $cc = array_merge($campaign->cc_emails ?? [], $recipient->cc_emails ?? []);
        $bcc = $campaign->bcc_emails ?? [];

        return view('mails.bulk-mail-preview', compact(
            'subject', 'html', 'sender', 'recipient',
            'sentAt', 'attachments', 'cc', 'bcc', 'campaign'
        ));
    }
}
