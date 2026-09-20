<?php

namespace App\Services\Reminders;

use App\Models\HealthRecord;
use App\Models\Reminder;

/**
 * Scoped-down stand-in for the plan's "Vaccination reminder: auto-suggest
 * based on uploaded vaccination records". A real vaccine schedule (age-
 * banded, per-vaccine intervals) is its own clinical dataset and out of
 * scope here; this creates one sensible one-year follow-up reminder per
 * vaccination record, which the user can edit or delete like any other.
 */
class VaccinationReminderSuggester
{
    public function suggestFor(HealthRecord $record): ?Reminder
    {
        if ($record->category !== 'vaccination') {
            return null;
        }

        $baseDate = $record->record_date ?? now();

        return Reminder::create([
            'family_group_id' => $record->family_group_id,
            'member_id' => $record->member_id,
            'created_by_id' => $record->uploaded_by_id,
            'reminder_type' => 'vaccination',
            'title' => 'Follow-up: '.($record->title ?: 'vaccination'),
            'description' => 'Suggested based on a vaccination record uploaded on '.$record->created_at->toDateString().'.',
            'due_at' => $baseDate->copy()->addYear(),
            'is_active' => true,
            'linked_record_id' => $record->id,
        ]);
    }
}
