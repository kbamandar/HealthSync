<?php

namespace App\Services\Reminders;

use App\Mail\ReminderDueMail;
use App\Models\Reminder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * The plan's `notify_via` supports push/SMS/WhatsApp, none of which have
 * working infrastructure in this sandbox (no Firebase project — see
 * Sprint 4's device registration — no MSG91/Gupshup credentials). Email is
 * the one channel with real, working infra (the same Mail setup that
 * already sends OTP and family-invite mail), so every due reminder sends
 * a real email to whoever the reminder is for, and the other requested
 * channels are logged as skipped rather than silently pretended to work.
 */
class ReminderNotifier
{
    public function send(Reminder $reminder): void
    {
        $recipient = $reminder->member->memberUser ?? $reminder->createdBy;

        if ($recipient?->email) {
            Mail::to($recipient->email)->send(new ReminderDueMail($reminder));
        }

        $unavailableChannels = array_diff($reminder->notify_via, ['email']);
        if ($unavailableChannels !== []) {
            Log::info('Reminder channel(s) not available in this environment', [
                'reminder_id' => $reminder->id,
                'requested_channels' => $unavailableChannels,
            ]);
        }
    }
}
