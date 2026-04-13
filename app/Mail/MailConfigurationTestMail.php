<?php

namespace App\Mail;

use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MailConfigurationTestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public CarbonInterface $sentAt,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '['.config('app.name').'] Mail configuration test',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.mail-configuration-test',
        );
    }
}
