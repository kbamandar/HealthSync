<?php

namespace App\Mail;

use App\Models\FamilyMember;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FamilyInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly FamilyMember $invite,
        public readonly string $inviterName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "{$this->inviterName} invited you to their HealthSync family",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.family-invite',
            with: [
                'acceptUrl' => "healthsync://family/invite/accept?token={$this->invite->invite_token}",
                'declineUrl' => "healthsync://family/invite/decline?token={$this->invite->invite_token}",
                'inviterName' => $this->inviterName,
                'relationship' => $this->invite->relationship,
            ],
        );
    }
}
