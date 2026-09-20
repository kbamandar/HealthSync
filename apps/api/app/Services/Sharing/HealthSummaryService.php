<?php

namespace App\Services\Sharing;

use App\Models\FamilyMember;
use App\Models\HealthRecord;
use App\Models\VitalReading;
use App\Services\Vitals\VitalThresholdService;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Renders a Health Summary PDF via dompdf (pure PHP, no headless browser)
 * instead of the plan's Gotenberg/Puppeteer — neither is available in this
 * sandbox (both need a separate rendering service/Chromium). Generated
 * fresh on every request rather than cached, so a shared summary link
 * always reflects the member's current data.
 */
class HealthSummaryService
{
    public function __construct(private readonly VitalThresholdService $thresholds) {}

    public function generate(FamilyMember $member): string
    {
        $conditions = HealthRecord::query()
            ->where('family_group_id', $member->family_group_id)
            ->where('member_id', $member->id)
            ->where('is_deleted', false)
            ->whereIn('category', ['chronic_condition', 'allergy'])
            ->orderByDesc('record_date')
            ->get();

        $latestVitals = VitalReading::query()
            ->where('family_group_id', $member->family_group_id)
            ->where('member_id', $member->id)
            ->where('recorded_at', '>=', now()->subDays(90))
            ->orderBy('vital_type')
            ->orderByDesc('recorded_at')
            ->get()
            ->unique('vital_type')
            ->values()
            ->map(fn (VitalReading $v) => [
                'vital_type' => $v->vital_type,
                'value' => $v->value,
                'unit' => $v->unit,
                'zone' => $this->thresholds->classify($v->vital_type, $v->value),
                'recorded_at' => $v->recorded_at,
            ]);

        return Pdf::loadView('summaries.health-summary', [
            'member' => $member,
            'conditions' => $conditions,
            'latestVitals' => $latestVitals,
            'generatedAt' => now(),
        ])->output();
    }
}
