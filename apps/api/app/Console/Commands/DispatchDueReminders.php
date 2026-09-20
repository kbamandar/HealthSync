<?php

namespace App\Console\Commands;

use App\Models\Reminder;
use App\Services\Reminders\ReminderNotifier;
use Illuminate\Console\Command;

class DispatchDueReminders extends Command
{
    protected $signature = 'reminders:dispatch-due';

    protected $description = 'Send due reminders and advance recurring ones to their next occurrence';

    public function handle(ReminderNotifier $notifier): int
    {
        $due = Reminder::query()->due()->get();

        foreach ($due as $reminder) {
            $notifier->send($reminder);

            $reminder->last_sent_at = now();

            $nextDueAt = match ($reminder->recurrence) {
                'daily' => $reminder->due_at->copy()->addDay(),
                'weekly' => $reminder->due_at->copy()->addWeek(),
                'monthly' => $reminder->due_at->copy()->addMonth(),
                default => null,
            };

            if ($nextDueAt) {
                $reminder->due_at = $nextDueAt;
            } else {
                // One-off and 'custom' reminders (a full cron-like schedule
                // parser is out of scope) fire once and are then retired.
                $reminder->is_active = false;
            }

            $reminder->save();
        }

        $this->info("Dispatched {$due->count()} due reminder(s).");

        return self::SUCCESS;
    }
}
