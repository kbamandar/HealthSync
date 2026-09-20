<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\AuditEvent;
use App\Models\FamilyMember;
use App\Models\VitalReading;
use App\Services\Family\FamilyGroupProvisioner;
use App\Services\Vitals\VitalThresholdService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class VitalReadingController extends Controller
{
    public function __construct(
        private readonly FamilyGroupProvisioner $familyGroups,
        private readonly VitalThresholdService $thresholds,
    ) {}

    public function index(Request $request)
    {
        $group = $this->familyGroups->ensureForUser($request->user());

        $query = VitalReading::query()->where('family_group_id', $group->id);

        if ($request->filled('member_id')) {
            $query->where('member_id', $request->string('member_id'));
        }

        if ($request->filled('vital_type')) {
            $query->where('vital_type', $request->string('vital_type'));
        }

        if ($request->filled('from')) {
            $query->where('recorded_at', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->where('recorded_at', '<=', $request->date('to'));
        }

        $readings = $query->orderByDesc('recorded_at')->limit(500)->get();

        return ApiResponse::success($readings->map(fn (VitalReading $r) => $this->serialize($r))->all());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'member_id' => ['required', 'uuid'],
            'vital_type' => ['required', 'string', 'in:'.implode(',', VitalThresholdService::VITAL_TYPES)],
            'value' => ['required', 'numeric'],
            'reading_context' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'recorded_at' => ['nullable', 'date'],
        ]);

        $group = $this->familyGroups->ensureForUser($request->user());

        $member = FamilyMember::query()
            ->where('id', $data['member_id'])
            ->where('family_group_id', $group->id)
            ->active()
            ->first();

        if (! $member) {
            throw ValidationException::withMessages([
                'member_id' => 'This family member was not found in your family group.',
            ]);
        }

        $isAbnormal = $this->thresholds->isAbnormal($data['vital_type'], (float) $data['value']);

        $reading = VitalReading::create([
            'family_group_id' => $group->id,
            'member_id' => $member->id,
            'logged_by_id' => $request->user()->id,
            'vital_type' => $data['vital_type'],
            'value' => $data['value'],
            'unit' => $this->thresholds->unitFor($data['vital_type']),
            'reading_context' => $data['reading_context'] ?? null,
            'notes' => $data['notes'] ?? null,
            'recorded_at' => $data['recorded_at'] ?? now(),
            'is_abnormal' => $isAbnormal,
            'alert_sent' => false,
        ]);

        AuditEvent::record('vital.log', $request->user()->id, $request, [
            'vital_reading_id' => $reading->id,
            'vital_type' => $reading->vital_type,
            'is_abnormal' => $isAbnormal,
        ]);

        return ApiResponse::success($this->serialize($reading));
    }

    public function destroy(Request $request, string $id)
    {
        $group = $this->familyGroups->ensureForUser($request->user());

        $reading = VitalReading::query()
            ->where('id', $id)
            ->where('family_group_id', $group->id)
            ->first();

        if (! $reading) {
            return ApiResponse::error('NOT_FOUND', 'Vital reading not found.', status: 404);
        }

        $reading->delete();

        AuditEvent::record('vital.delete', $request->user()->id, $request, ['vital_reading_id' => $id]);

        return ApiResponse::success();
    }

    private function serialize(VitalReading $reading): array
    {
        return [
            'id' => $reading->id,
            'member_id' => $reading->member_id,
            'vital_type' => $reading->vital_type,
            'value' => $reading->value,
            'unit' => $reading->unit,
            'reading_context' => $reading->reading_context,
            'notes' => $reading->notes,
            'recorded_at' => $reading->recorded_at->toIso8601String(),
            'is_abnormal' => $reading->is_abnormal,
            'zone' => $this->thresholds->classify($reading->vital_type, $reading->value),
        ];
    }
}
