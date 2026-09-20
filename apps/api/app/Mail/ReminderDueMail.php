<?php

namespace App\Mail;

use App\Models\Reminder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReminderDueMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Reminder $reminder) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Reminder: {$this->reminder->title}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reminder-due',
            with: [
                'title' => $this->reminder->title,
                'description' => $this->reminder->description,
                'dueAt' => $this->reminder->due_at,
                'memberName' => $this->reminder->member->display_name,
            ],
        );
    }
}
