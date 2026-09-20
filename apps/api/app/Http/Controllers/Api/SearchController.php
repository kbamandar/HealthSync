<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\FamilyMember;
use App\Models\HealthRecord;
use App\Services\Family\FamilyGroupProvisioner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SearchController extends Controller
{
    public function __construct(private readonly FamilyGroupProvisioner $familyGroups) {}

    public function search(Request $request)
    {
        $request->validate(['q' => ['required', 'string', 'min:2']]);

        $group = $this->familyGroups->ensureForUser($request->user());
        $q = $request->string('q')->toString();

        $records = HealthRecord::query()
            ->where('family_group_id', $group->id)
            ->active()
            ->whereRaw(
                "to_tsvector('english', coalesce(title,'') || ' ' || coalesce(notes,'')) @@ plainto_tsquery('english', ?)",
                [$q],
            )
            ->orderByDesc('record_date')
            ->get();

        $grouped = $records->groupBy('category')->map(fn ($group, $category) => [
            'category' => $category,
            'records' => $group->map(fn (HealthRecord $r) => [
                'id' => $r->id,
                'title' => $r->title,
                'notes' => $r->notes,
                'record_date' => $r->record_date?->toDateString(),
            ])->values(),
        ])->values();

        return ApiResponse::success($grouped);
    }

    public function timeline(Request $request)
    {
        $group = $this->familyGroups->ensureForUser($request->user());

        $recordEvents = DB::table('health_records')
            ->select([
                'id',
                DB::raw("'record' as kind"),
                'category as sub_type',
                'title',
                DB::raw('null::numeric as value'),
                DB::raw('null as unit'),
                DB::raw('null as is_abnormal'),
                DB::raw('coalesce(record_date, created_at::date) as event_date'),
                'created_at',
                'member_id',
            ])
            ->where('family_group_id', $group->id)
            ->where('is_deleted', false);

        $vitalEvents = DB::table('vital_readings')
            ->select([
                'id',
                DB::raw("'vital' as kind"),
                'vital_type as sub_type',
                DB::raw('null as title'),
                'value',
                'unit',
                'is_abnormal',
                DB::raw('recorded_at::date as event_date'),
                'created_at',
                'member_id',
            ])
            ->where('family_group_id', $group->id);

        if ($request->filled('member_id')) {
            $memberId = $request->string('member_id')->toString();
            $recordEvents->where('member_id', $memberId);
            $vitalEvents->where('member_id', $memberId);
        }

        if ($request->filled('from')) {
            $from = $request->date('from');
            $recordEvents->where(DB::raw('coalesce(record_date, created_at::date)'), '>=', $from);
            $vitalEvents->where(DB::raw('recorded_at::date'), '>=', $from);
        }

        if ($request->filled('to')) {
            $to = $request->date('to');
            $recordEvents->where(DB::raw('coalesce(record_date, created_at::date)'), '<=', $to);
            $vitalEvents->where(DB::raw('recorded_at::date'), '<=', $to);
        }

        $combined = $recordEvents->unionAll($vitalEvents);

        $perPage = min((int) $request->integer('per_page', 20), 100);
        $page = DB::query()
            ->fromSub($combined, 'timeline_events')
            ->orderByDesc('event_date')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $memberNames = FamilyMember::query()
            ->where('family_group_id', $group->id)
            ->pluck('display_name', 'id');

        $items = collect($page->items())->map(fn ($row) => [
            'id' => $row->id,
            'kind' => $row->kind,
            'sub_type' => $row->sub_type,
            'title' => $row->title,
            'value' => $row->value !== null ? (float) $row->value : null,
            'unit' => $row->unit,
            'is_abnormal' => $row->is_abnormal !== null ? (bool) $row->is_abnormal : null,
            'event_date' => $row->event_date,
            'member_id' => $row->member_id,
            'member_name' => $memberNames->get($row->member_id),
        ]);

        return ApiResponse::success($items, [
            'page' => $page->currentPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
        ]);
    }
}
