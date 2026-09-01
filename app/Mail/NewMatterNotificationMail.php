<?php

namespace App\Mail;

use App\Models\Matter;
use App\Models\MatterRequest;
use App\Models\Party;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class NewMatterNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $acceptUrl;

    public string $disputeUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(public Matter $matter, public Party $assistant, public MatterRequest $matterRequest)
    {

        $this->acceptUrl = URL::signedRoute(
            'matter.received.accept',
            ['matter' => $matter->id, 'matterRequest' => $matterRequest->id],
            now()->addDays(1)
        );

        $this->disputeUrl = URL::signedRoute(
            'matter.received.dispute',
            ['matter' => $matter->id, 'matterRequest' => $matterRequest->id],
            now()->addDays(1)
        );
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('New Matter Notification')
            .' — '.$this->matter->year.'/'.$this->matter->number.($this->matter->type ? ' — '.$this->matter->type->name : ''),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mails.new-matter-notification',
        );
    }
}
