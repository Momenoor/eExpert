<?php

namespace App\Mail;

use App\Models\BulkMailCampaign;
use App\Models\BulkMailRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\HtmlString;

class BulkMailMessage extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public BulkMailCampaign  $campaign,
        public BulkMailRecipient $recipient
    )
    {
    }

    public function envelope(): Envelope
    {
        $sender = $this->campaign->sender_config;

        $from = new Address($sender['address'], $sender['name']);

        $subject = $this->campaign->renderSubject($this->recipient);

        return new Envelope(
            from: $from,
            replyTo: [$from],
            subject: $subject,
            tags: ['bulk-mail', "campaign-{$this->campaign->id}"],
            metadata: [
                'campaign_id' => $this->campaign->id,
                'recipient_id' => $this->recipient->id ?? 7,
            ],
        );
    }

    public function content(): Content
    {
        $html = $this->campaign->renderBody($this->recipient);
        return new Content(
            htmlString: new HtmlString($html),
        );
    }

    public function attachments(): array
    {
        $attachments = [];

        if ($this->campaign->has_attachment && $this->campaign->attachment_path) {
            $disk = $this->campaign->attachment_disk ?? 'public';
            foreach ($this->campaign->attachment_path as $path) {
                if ($path) {
                    $attachments[] = Attachment::fromStorageDisk($disk, $path);
                }
            }
        }

        return $attachments;
    }

    public function headers(): Headers
    {
        return new Headers(
            text: [
            'X-Campaign-ID' => $this->campaign->id,
            'X-Recipient-ID' => $this->recipient->id ?? auth()->user()->id,
        ]);
    }
}
