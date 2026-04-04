<?php

namespace App\Mail;

use App\Models\Service;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WebhookDeliveryFailedMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, array{recipient_name: string, webhook_url: string, reason: string, authentication: string, sources: array<int, string>}>  $failures
     */
    public function __construct(
        public Service $service,
        public string $triggeredStatus,
        public array $failures,
        public CarbonInterface $checkedAt,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '['.config('app.name').'] Webhook delivery failed for '.$this->service->name,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.webhook-delivery-failed',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
