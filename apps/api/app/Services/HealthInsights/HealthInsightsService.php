<?php

namespace App\Services\HealthInsights;

use App\Models\FamilyMember;
use App\Models\Reminder;
use App\Models\VitalReading;
use App\Services\Vitals\VitalThresholdService;

/**
 * MVP-lite rule-based insight generator — no ML, just the same handful of
 * "worth a nudge" conditions the Health Score factors already watch for
 * (see HealthScoreService, which reuses worseningVitals() from here so the
 * two features never disagree about what counts as a worsening trend).
 *
 * Insights are computed fresh on every call rather than persisted/dismissed
 * server-side — there's no dismissed-insights table in the schema, and the
 * "I understand" action in the UI is a client-local hide for the session.
 */
class HealthInsightsService
{
    private const VITAL_LABELS = [
        'bp_systolic' => 'Systolic BP',
        'bp_diastolic' => 'Diastolic BP',
        'sugar_fasting' => 'Fasting sugar',
        'sugar_pp' => 'Post-meal sugar',
        'sugar_random' => 'Random sugar',
        'hba1c' => 'HbA1c',
        'heart_rate' => 'Heart rate',
        'spo2' => 'SpO2',
        'temperature' => 'Temperature',
    ];

    public function __construct(private readonly VitalThresholdService $thresholds) {}

    public function generateFor(FamilyMember $member, string $groupId): array
    {
        $insights = [];

        foreach ($this->worseningVitals($member, $groupId) as $vitalType => $label) {
            $insights[] = [
                'id' => "vital_trend:{$vitalType}",
                'type' => 'vital_trend',
                'severity' => 'warning',
                'title' => "{$label} trending up",
                'message' => "Your {$label} has trended upward over your last 3 readings — consider logging it more often.",
            ];
        }

        $overdueMedication = $this->overdueMedicationCount($member, $groupId);
        if ($overdueMedication > 0) {
            $insights[] = [
                'id' => 'medication_overdue',
                'type' => 'medication_overdue',
                'severity' => 'warning',
                'title' => 'Medication reminders overdue',
                'message' => $overdueMedication === 1
                    ? 'You have 1 overdue medication reminder.'
                    : "You have {$overdueMedication} overdue medication reminders.",
            ];
        }

        $checkupOverdueDays = $this->annualCheckupOverdueDays($member, $groupId);
        if ($checkupOverdueDays !== null) {
            $insights[] = [
                'id' => 'annual_checkup_overdue',
                'type' => 'annual_checkup_overdue',
                'severity' => 'info',
                'title' => 'Annual checkup overdue',
                'message' => "Your annual checkup was due {$checkupOverdueDays} day(s) ago — consider booking one.",
            ];
        }

        return $insights;
    }

    /**
     * Vital types whose latest reading is outside the normal range and
     * whose last 3 readings are monotonically worsening in that direction.
     *
     * @return array<string, string> vital_type => human label
     */
    public function worseningVitals(FamilyMember $member, string $groupId): array
    {
        $since = now()->subDays(90);

        $latestByType = VitalReading::query()
            ->where('family_group_id', $groupId)
            ->where('member_id', $member->id)
            ->where('recorded_at', '>=', $since)
            ->orderBy('vital_type')
            ->orderByDesc('recorded_at')
            ->get()
            ->unique('vital_type');

        $worsening = [];

        foreach ($latestByType as $latest) {
            if ($this->thresholds->classify($latest->vital_type, $latest->value) === 'normal') {
                continue;
            }

            $lastThree = VitalReading::query()
                ->where('family_group_id', $groupId)
                ->where('member_id', $member->id)
                ->where('vital_type', $latest->vital_type)
                ->orderByDesc('recorded_at')
                ->limit(3)
                ->pluck('value');

            if ($lastThree->count() === 3 && $lastThree[0] > $lastThree[1] && $lastThree[1] > $lastThree[2]) {
                $worsening[$latest->vital_type] = self::VITAL_LABELS[$latest->vital_type] ?? $latest->vital_type;
            }
        }

        return $worsening;
    }

    private function overdueMedicationCount(FamilyMember $member, string $groupId): int
    {
        return Reminder::query()
            ->where('family_group_id', $groupId)
            ->where('member_id', $member->id)
            ->where('reminder_type', 'medication')
            ->active()
            ->where('due_at', '<', now())
            ->count();
    }

    private function annualCheckupOverdueDays(FamilyMember $member, string $groupId): ?int
    {
        $reminder = Reminder::query()
            ->where('family_group_id', $groupId)
            ->where('member_id', $member->id)
            ->where('reminder_type', 'annual_checkup')
            ->active()
            ->where('due_at', '<', now())
            ->orderBy('due_at')
            ->first();

        return $reminder ? (int) $reminder->due_at->diffInDays(now()) : null;
    }
}
