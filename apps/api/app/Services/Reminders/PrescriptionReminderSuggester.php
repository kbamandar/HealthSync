<?php

namespace App\Services\Reminders;

use App\Models\HealthRecord;
use App\Models\Reminder;
use Illuminate\Support\Collection;

/**
 * Auto-creates one medication reminder per medicine parsed from a
 * prescription's OCR text, mirroring VaccinationReminderSuggester's
 * scoped-down approach: a sensible default schedule the user can edit or
 * delete like any other reminder, not a dosage-tracking system.
 */
class PrescriptionReminderSuggester
{
    /** @param array<int, array{name: string, frequency: string, duration_days: int|null}> $medicines */
    public function suggestFor(HealthRecord $record, array $medicines): Collection
    {
        if ($record->category !== 'prescription' || $medicines === []) {
            return collect();
        }

        // Idempotent: re-applying OCR (or re-pressing "Create reminders")
        // must not create duplicate reminders for the same prescription.
        if (Reminder::where('linked_record_id', $record->id)->where('reminder_type', 'medication')->exists()) {
            return collect();
        }

        return collect($medicines)->map(fn (array $medicine) => Reminder::create([
            'family_group_id' => $record->family_group_id,
            'member_id' => $record->member_id,
            'created_by_id' => $record->uploaded_by_id,
            'reminder_type' => 'medication',
            'title' => $medicine['name'],
            'description' => $this->describeDuration($medicine),
            'due_at' => now()->addDay(),
            'recurrence' => 'daily',
            'is_active' => true,
            'linked_record_id' => $record->id,
        ]));
    }

    private function describeDuration(array $medicine): string
    {
        $frequency = $medicine['frequency'] ?? '';
        $durationDays = $medicine['duration_days'] ?? null;

        if ($durationDays === null) {
            return $frequency;
        }

        return trim($frequency.' · '.$durationDays.' day(s)', ' ·');
    }
}
