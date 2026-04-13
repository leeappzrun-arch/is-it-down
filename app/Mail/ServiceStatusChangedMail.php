<?php

namespace App\Mail;

use App\Models\Service;
use App\Models\ServiceDowntime;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ServiceStatusChangedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Service $service,
        public string $currentStatus,
        public ?string $previousStatus,
        public string $reason,
        public ?int $responseCode,
        public CarbonInterface $checkedAt,
        public ?ServiceDowntime $downtime = null,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subjectPrefix = $this->currentStatus === Service::STATUS_DOWN ? 'Down' : 'It Is Up!';

        return new Envelope(
            subject: '['.config('app.name').'] '.$subjectPrefix.': '.$this->service->name,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.service-status-changed',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if (! $this->downtime instanceof ServiceDowntime || ! $this->downtime->hasScreenshot()) {
            return [];
        }

        return [
            Attachment::fromStorageDisk(
                (string) $this->downtime->screenshot_disk,
                (string) $this->downtime->screenshot_path,
            )->as('service-downtime-'.$this->downtime->id.'.png'),
        ];
    }
}
