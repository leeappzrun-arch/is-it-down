<?php

namespace App\Mail;

use App\Models\Service;
use App\Support\Monitoring\SslCertificateInspectionResult;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ServiceSslExpiryWarningMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Service $service,
        public SslCertificateInspectionResult $certificate,
        public CarbonInterface $checkedAt,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '['.config('app.name').'] SSL certificate expiring soon: '.$this->service->name,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.service-ssl-expiry-warning',
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
