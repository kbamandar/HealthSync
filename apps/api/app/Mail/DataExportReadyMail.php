<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DataExportReadyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly string $downloadUrl) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your HealthSync data export is ready',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.data-export-ready',
            with: ['downloadUrl' => $this->downloadUrl],
        );
    }
}
