<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Jobs\GenerateDataExport;
use App\Models\AuditEvent;
use App\Services\Family\FamilyGroupProvisioner;
use Illuminate\Http\Request;

class ComplianceController extends Controller
{
    public function __construct(private readonly FamilyGroupProvisioner $familyGroups) {}

    public function requestDataExport(Request $request)
    {
        $group = $this->familyGroups->ensureForUser($request->user());

        GenerateDataExport::dispatch($request->user()->id, $group->id);

        AuditEvent::record('data_export.requested', $request->user()->id, $request);

        return ApiResponse::success([
            'message' => "We're preparing your export. You'll receive an email with a download link shortly.",
        ]);
    }

    public function requestAccountDeletion(Request $request)
    {
        $user = $request->user();

        if ($user->deletion_requested_at === null) {
            $user->update(['deletion_requested_at' => now()]);
            AuditEvent::record('account_deletion.requested', $user->id, $request);
        }

        return ApiResponse::success([
            'deletion_requested_at' => $user->deletion_requested_at->toIso8601String(),
            'purge_at' => $user->deletion_requested_at->copy()->addDays(30)->toIso8601String(),
        ]);
    }

    public function cancelAccountDeletion(Request $request)
    {
        $user = $request->user();

        if ($user->deletion_requested_at !== null) {
            $user->update(['deletion_requested_at' => null]);
            AuditEvent::record('account_deletion.cancelled', $user->id, $request);
        }

        return ApiResponse::success();
    }

    public function auditLog(Request $request)
    {
        $perPage = min((int) $request->integer('per_page', 20), 100);

        $events = AuditEvent::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return ApiResponse::success(
            collect($events->items())->map(fn (AuditEvent $e) => [
                'id' => $e->id,
                'event_type' => $e->event_type,
                'resource_type' => $e->resource_type,
                'resource_id' => $e->resource_id,
                'ip_address' => $e->ip_address,
                'metadata' => $e->metadata,
                'created_at' => $e->created_at->toIso8601String(),
            ])->all(),
            [
                'page' => $events->currentPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total(),
            ],
        );
    }
}
