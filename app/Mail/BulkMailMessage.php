<?php

namespace App\Mail;

use App\Models\BulkMailCampaign;
use App\Models\BulkMailRecipient;
use App\Services\BulkMailService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
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

        $subject = $this->campaign->renderSubject($this->recipient);

        return new Envelope(
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
        if (BulkMailService::containsArabic($html)) {
            $html = '<div dir="rtl">' . $html . '</div>';
        }
        return new Content(
            htmlString: new HtmlString($html),
        );
    }

    public function attachments(): array
    {
        $attachments = [];

        if ($this->campaign->has_attachment && $this->campaign->attachment_path) {
            $disk  = 'public';
            $paths = is_array($this->campaign->attachment_path)
                ? $this->campaign->attachment_path
                : json_decode($this->campaign->attachment_path, true) ?? [];

            foreach ($paths as $path) {

                if ($path && Storage::disk($disk)->exists($path)) {

                    $attachments[] = Attachment::fromStorageDisk($disk, $path)
                        ->withMime(Storage::disk($disk)->mimeType($path) ?? 'application/octet-stream');
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
