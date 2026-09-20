<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\FamilyMember;
use App\Models\HealthRecord;
use App\Models\VitalReading;
use App\Services\Family\FamilyGroupProvisioner;
use App\Services\Vitals\VitalThresholdService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    private const PROFILE_FIELDS = [
        'name', 'date_of_birth', 'gender', 'blood_group', 'profile_photo',
        'emergency_contact_name', 'emergency_contact_mobile',
    ];

    public function __construct(
        private readonly FamilyGroupProvisioner $familyGroups,
        private readonly VitalThresholdService $thresholds,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $group = $this->familyGroups->ensureForUser($user);

        $members = FamilyMember::query()
            ->where('family_group_id', $group->id)
            ->active()
            ->get();

        $selfMember = $members->firstWhere('relationship', 'self');
        $memberId = $request->string('member_id')->toString() ?: $selfMember?->id;

        $recentRecords = HealthRecord::query()
            ->where('family_group_id', $group->id)
            ->active()
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn (HealthRecord $r) => [
                'id' => $r->id,
                'category' => $r->category,
                'title' => $r->title,
                'record_date' => $r->record_date?->toDateString(),
                'created_at' => $r->created_at->toIso8601String(),
            ]);

        $latestVitals = collect();
        if ($memberId) {
            $latestVitals = VitalReading::query()
                ->where('family_group_id', $group->id)
                ->where('member_id', $memberId)
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
                    'recorded_at' => $v->recorded_at->toIso8601String(),
                ]);
        }

        $abnormalSince = now()->subDays(30);
        $familySummary = $members->map(function (FamilyMember $member) use ($group, $abnormalSince) {
            $pendingCount = VitalReading::query()
                ->where('family_group_id', $group->id)
                ->where('member_id', $member->id)
                ->where('is_abnormal', true)
                ->where('recorded_at', '>=', $abnormalSince)
                ->count();

            return [
                'member_id' => $member->id,
                'display_name' => $member->display_name,
                'relationship' => $member->relationship,
                'pending_count' => $pendingCount,
            ];
        });

        $filledFields = collect(self::PROFILE_FIELDS)->filter(fn ($field) => filled($user->{$field}))->count();
        $profileCompletePercent = (int) round(($filledFields / count(self::PROFILE_FIELDS)) * 100);

        return ApiResponse::success([
            'stats' => [
                'records_this_month' => HealthRecord::query()
                    ->where('family_group_id', $group->id)
                    ->active()
                    ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
                    ->count(),
                'vitals_needing_attention' => $familySummary->sum('pending_count'),
                'profile_complete_percent' => $profileCompletePercent,
            ],
            'recent_records' => $recentRecords,
            'latest_vitals' => $latestVitals,
            'family_summary' => $familySummary,
        ]);
    }

    public function healthScore(Request $request, string $memberId)
    {
        // Health Score is a Sprint 9 feature (Health Score & Prescription
        // Reader) — not yet implemented.
        return ApiResponse::error('NOT_IMPLEMENTED', 'Health Score is not available yet.', status: 501);
    }
}
