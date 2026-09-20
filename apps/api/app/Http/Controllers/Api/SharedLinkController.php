<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\AuditEvent;
use App\Models\FamilyMember;
use App\Models\HealthRecord;
use App\Models\SharedLink;
use App\Services\Family\FamilyGroupProvisioner;
use App\Services\Records\RecordStorageService;
use App\Services\Sharing\HealthSummaryService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class SharedLinkController extends Controller
{
    public function __construct(
        private readonly FamilyGroupProvisioner $familyGroups,
        private readonly RecordStorageService $storage,
        private readonly HealthSummaryService $summaries,
    ) {}

    public function shareRecord(Request $request)
    {
        $data = $request->validate([
            'record_id' => ['required', 'uuid'],
            'expires_in_days' => ['sometimes', 'integer', 'min:1', 'max:90'],
            'max_access' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ]);

        $group = $this->familyGroups->ensureForUser($request->user());

        $record = HealthRecord::query()
            ->where('id', $data['record_id'])
            ->where('family_group_id', $group->id)
            ->active()
            ->first();

        if (! $record) {
            throw ValidationException::withMessages([
                'record_id' => 'This record was not found in your family group.',
            ]);
        }

        $link = SharedLink::create([
            'created_by_id' => $request->user()->id,
            'link_type' => 'record',
            'record_id' => $record->id,
            'token' => bin2hex(random_bytes(32)),
            'expires_at' => now()->addDays($data['expires_in_days'] ?? 7),
            'max_access' => $data['max_access'] ?? null,
            'access_count' => 0,
            'is_revoked' => false,
            'created_at' => now(),
        ]);

        AuditEvent::record('link.create', $request->user()->id, $request, [
            'shared_link_id' => $link->id,
            'link_type' => 'record',
            'record_id' => $record->id,
        ]);

        return ApiResponse::success($this->serialize($link));
    }

    public function shareSummary(Request $request)
    {
        $data = $request->validate([
            'member_id' => ['required', 'uuid'],
            'expires_in_days' => ['sometimes', 'integer', 'min:1', 'max:90'],
            'max_access' => ['sometimes', 'nullable', 'integer', 'min:1'],
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

        $link = SharedLink::create([
            'created_by_id' => $request->user()->id,
            'link_type' => 'health_summary',
            'member_id' => $member->id,
            'token' => bin2hex(random_bytes(32)),
            'expires_at' => now()->addDays($data['expires_in_days'] ?? 7),
            'max_access' => $data['max_access'] ?? null,
            'access_count' => 0,
            'is_revoked' => false,
            'created_at' => now(),
        ]);

        AuditEvent::record('link.create', $request->user()->id, $request, [
            'shared_link_id' => $link->id,
            'link_type' => 'health_summary',
            'member_id' => $member->id,
        ]);

        return ApiResponse::success($this->serialize($link));
    }

    public function index(Request $request)
    {
        $links = SharedLink::query()
            ->where('created_by_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get();

        return ApiResponse::success($links->map(fn (SharedLink $l) => $this->serializeSummary($l))->all());
    }

    public function destroy(Request $request, string $id)
    {
        $link = SharedLink::query()
            ->where('id', $id)
            ->where('created_by_id', $request->user()->id)
            ->first();

        if (! $link) {
            return ApiResponse::error('NOT_FOUND', 'Shared link not found.', status: 404);
        }

        $link->update(['is_revoked' => true]);

        AuditEvent::record('link.revoke', $request->user()->id, $request, ['shared_link_id' => $link->id]);

        return ApiResponse::success();
    }

    public function publicAccess(Request $request, string $token)
    {
        $link = SharedLink::query()->where('token', $token)->first();

        if (! $link || ! $link->isUsable()) {
            return ApiResponse::error('LINK_INVALID', 'This link is invalid, expired, or has been revoked.', status: 404);
        }

        $link->increment('access_count');

        AuditEvent::record('link.access', null, $request, ['shared_link_id' => $link->id]);

        if ($link->link_type === 'health_summary') {
            $member = $link->member;

            if (! $member) {
                return ApiResponse::error('LINK_INVALID', 'This link is invalid, expired, or has been revoked.', status: 404);
            }

            return response($this->summaries->generate($member), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="health-summary.pdf"',
            ]);
        }

        $record = $link->record;

        if (! $record) {
            return ApiResponse::error('LINK_INVALID', 'This link is invalid, expired, or has been revoked.', status: 404);
        }

        return ApiResponse::success([
            'title' => $record->title,
            'category' => $record->category,
            'record_date' => $record->record_date?->toDateString(),
            'doctor_name' => $record->doctor_name,
            'hospital_clinic' => $record->hospital_clinic,
            'files' => $record->files->map(fn ($f) => [
                'file_type' => $f->file_type,
                'download_url' => $this->storage->createDownloadUrl($f->s3_key),
            ])->all(),
        ]);
    }

    private function serializeSummary(SharedLink $link): array
    {
        return [
            'id' => $link->id,
            'link_type' => $link->link_type,
            'label' => $link->link_type === 'health_summary'
                ? 'Health summary — '.($link->member?->display_name ?? 'Unknown')
                : ($link->record?->title ?: 'Untitled record'),
            'url' => route('public.share', $link->token),
            'expires_at' => $link->expires_at->toIso8601String(),
            'access_count' => $link->access_count,
            'max_access' => $link->max_access,
            'is_revoked' => $link->is_revoked,
            'created_at' => $link->created_at->toIso8601String(),
        ];
    }

    private function serialize(SharedLink $link): array
    {
        $url = route('public.share', $link->token);

        return $this->serializeSummary($link) + [
            'qr_code_svg' => (string) QrCode::format('svg')->size(200)->generate($url),
        ];
    }
}
