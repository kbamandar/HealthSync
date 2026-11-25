<?php

namespace App\Services\HealthScore;

use App\Models\FamilyMember;
use App\Models\HealthRecord;
use App\Models\Reminder;
use App\Models\VitalReading;
use App\Services\HealthInsights\HealthInsightsService;
use App\Services\Vitals\VitalThresholdService;

/**
 * Computes a single 0-100 "Health Score" for a family member from signals the
 * app already has (vitals, reminders, records, profile fields). There is no
 * appointment/visit or dose-confirmation tracking anywhere in the schema, so
 * "annual checkup" and "medication adherence" are both proxied off reminder
 * scheduling (due_at vs. now) rather than any confirmed-completion event —
 * consistent with how reminders work everywhere else in this app.
 */
class HealthScoreService
{
    private const WEIGHTS = [
        'vitals_trend' => 0.30,
        'medication_adherence' => 0.25,
        'records_completeness' => 0.25,
        'annual_checkup' => 0.20,
    ];

    public function __construct(
        private readonly VitalThresholdService $thresholds,
        private readonly HealthInsightsService $insights,
    ) {}

    public function compute(FamilyMember $member, string $familyGroupId): array
    {
        $factors = [
            'annual_checkup' => $this->annualCheckupFactor($member, $familyGroupId),
            'vitals_trend' => $this->vitalsTrendFactor($member, $familyGroupId),
            'medication_adherence' => $this->medicationAdherenceFactor($member, $familyGroupId),
            'records_completeness' => $this->recordsCompletenessFactor($member, $familyGroupId),
        ];

        $weightedSum = 0.0;
        foreach ($factors as $key => $factor) {
            $weightedSum += $factor['score'] * self::WEIGHTS[$key];
        }
        $score = (int) round($weightedSum * 100);

        return [
            'score' => $score,
            'status' => $this->overallStatus($score),
            'summary' => $this->summary($score, $factors),
            'factors' => collect($factors)->map(fn ($factor, $key) => [
                'key' => $key,
                'label' => $factor['label'],
                'status' => $factor['status'],
                'detail' => $factor['detail'],
                'weight' => self::WEIGHTS[$key],
            ])->values()->all(),
            'computed_at' => now()->toIso8601String(),
        ];
    }

    private function annualCheckupFactor(FamilyMember $member, string $groupId): array
    {
        $reminder = Reminder::query()
            ->where('family_group_id', $groupId)
            ->where('member_id', $member->id)
            ->where('reminder_type', 'annual_checkup')
            ->active()
            ->orderBy('due_at')
            ->first();

        if (! $reminder) {
            return [
                'label' => 'Annual checkup',
                'score' => 0.6,
                'status' => 'Not scheduled',
                'detail' => 'No annual checkup reminder is set up for this member.',
            ];
        }

        $daysUntilDue = (int) now()->diffInDays($reminder->due_at, false);

        if ($daysUntilDue < 0) {
            return [
                'label' => 'Annual checkup',
                'score' => 0.3,
                'status' => 'Overdue',
                'detail' => 'The annual checkup was due '.abs($daysUntilDue).' day(s) ago.',
            ];
        }

        if ($daysUntilDue <= 30) {
            return [
                'label' => 'Annual checkup',
                'score' => 0.7,
                'status' => 'Due soon',
                'detail' => "The annual checkup is coming up in {$daysUntilDue} day(s).",
            ];
        }

        return [
            'label' => 'Annual checkup',
            'score' => 1.0,
            'status' => 'Good',
            'detail' => 'The next annual checkup is scheduled and on track.',
        ];
    }

    private function vitalsTrendFactor(FamilyMember $member, string $groupId): array
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

        if ($latestByType->isEmpty()) {
            return [
                'label' => 'Vitals trend',
                'score' => 0.6,
                'status' => 'No recent readings',
                'detail' => 'Log a vital reading to start tracking this.',
            ];
        }

        $zoneScore = fn (string $zone) => match ($zone) {
            'normal' => 1.0,
            'borderline' => 0.6,
            default => 0.2,
        };

        $scores = $latestByType->map(
            fn (VitalReading $reading) => $zoneScore($this->thresholds->classify($reading->vital_type, $reading->value))
        );
        $avg = $scores->avg();

        $worsening = collect($this->insights->worseningVitals($member, $groupId))->first();

        if ($worsening !== null) {
            $detail = "{$worsening} has trended upward over your last 3 readings — consider logging it more often.";
        } elseif ($avg >= 0.85) {
            $detail = 'Your latest vitals are within normal range.';
        } elseif ($avg >= 0.5) {
            $detail = 'Some recent vitals are borderline — worth keeping an eye on.';
        } else {
            $detail = 'Some recent vitals are outside the normal range.';
        }

        return [
            'label' => 'Vitals trend',
            'score' => $avg,
            'status' => $avg >= 0.85 ? 'Good' : ($avg >= 0.5 ? 'Watch' : 'Needs attention'),
            'detail' => $detail,
        ];
    }

    private function medicationAdherenceFactor(FamilyMember $member, string $groupId): array
    {
        $medicationReminders = Reminder::query()
            ->where('family_group_id', $groupId)
            ->where('member_id', $member->id)
            ->where('reminder_type', 'medication')
            ->active()
            ->get();

        if ($medicationReminders->isEmpty()) {
            return [
                'label' => 'Medication adherence',
                'score' => 0.8,
                'status' => 'No active medications',
                'detail' => 'This member has no medication reminders set up.',
            ];
        }

        $overdueCount = $medicationReminders->filter(fn (Reminder $r) => $r->due_at->isPast())->count();
        $ratio = 1 - ($overdueCount / $medicationReminders->count());

        if ($ratio >= 0.85) {
            $status = 'Good';
            $detail = 'Medication reminders are up to date.';
        } elseif ($ratio >= 0.5) {
            $status = 'Watch';
            $detail = "{$overdueCount} medication reminder(s) are overdue.";
        } else {
            $status = 'Behind';
            $detail = "{$overdueCount} medication reminder(s) are overdue.";
        }

        return [
            'label' => 'Medication adherence',
            'score' => $ratio,
            'status' => $status,
            'detail' => $detail,
        ];
    }

    private function recordsCompletenessFactor(FamilyMember $member, string $groupId): array
    {
        $profileFields = ['date_of_birth', 'gender', 'blood_group'];
        $filled = collect($profileFields)->filter(fn ($field) => filled($member->{$field}))->count();
        $profileScore = $filled / count($profileFields);

        $hasRecords = HealthRecord::query()
            ->where('family_group_id', $groupId)
            ->where('member_id', $member->id)
            ->active()
            ->exists();

        $recordsScore = $hasRecords ? 1.0 : 0.5;
        $combined = ($profileScore * 0.6) + ($recordsScore * 0.4);

        if ($combined >= 0.85) {
            $detail = 'Profile and record history are up to date.';
        } elseif (! $hasRecords) {
            $detail = 'No health records have been added for this member yet.';
        } else {
            $missing = count($profileFields) - $filled;
            $detail = "Add {$missing} more profile detail(s) (date of birth, gender, blood group) to complete this.";
        }

        return [
            'label' => 'Records completeness',
            'score' => $combined,
            'status' => $combined >= 0.85 ? 'Good' : 'Needs attention',
            'detail' => $detail,
        ];
    }

    private function overallStatus(int $score): string
    {
        return match (true) {
            $score >= 90 => 'excellent',
            $score >= 75 => 'good',
            $score >= 55 => 'fair',
            default => 'needs_attention',
        };
    }

    private function summary(int $score, array $factors): string
    {
        $label = match ($this->overallStatus($score)) {
            'excellent' => 'Excellent',
            'good' => 'Good',
            'fair' => 'Fair',
            default => 'Needs attention',
        };

        $weakest = collect($factors)->sortBy('score')->first();

        if ($weakest['score'] >= 0.85) {
            return "{$label} — everything is on track.";
        }

        $reason = match ($weakest['label']) {
            'Annual checkup' => 'slightly behind on your annual checkup',
            'Vitals trend' => 'keep an eye on your vitals',
            'Medication adherence' => 'behind on medication reminders',
            'Records completeness' => 'your profile could be more complete',
            default => 'a few things to tend to',
        };

        return "{$label} — {$reason}.";
    }
}
