<?php

namespace App\Mail;

use App\Models\Matter;
use App\Models\MatterRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class RequestActionNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Matter $matter,
        public MatterRequest $matterRequest,
        public string $statusLabel
    ) {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Request :status', ['status' => $this->statusLabel])
            .' — '.$this->matter->year.'/'.$this->matter->number.($this->matter->type ? ' — '.$this->matter->type->name : ''),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mails.request-action-notification',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $attachments = $this->matterRequest->attachments()->get();
        $mailAttachments = [];

        foreach ($attachments as $attachment) {
            $exists = Storage::disk('public')->exists($attachment->path);

            if (! $exists) {
                \Log::warning('Attachment file not found on public disk: '.$attachment->path);

                continue;
            }

            try {
                $mailAttachments[] = Attachment::fromStorageDisk('public', $attachment->path)
                    ->as($attachment->name)
                    ->withMime(
                        Storage::disk('public')->mimeType($attachment->path) ?? 'application/octet-stream'
                    );
            } catch (\Exception $e) {
                \Log::error('Failed to attach file: '.$attachment->path.' Error: '.$e->getMessage());
            }
        }

        return $mailAttachments;
    }
}
