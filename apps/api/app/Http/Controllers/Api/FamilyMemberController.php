<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Mail\FamilyInviteMail;
use App\Models\AuditEvent;
use App\Models\FamilyMember;
use App\Services\Family\FamilyGroupProvisioner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FamilyMemberController extends Controller
{
    public function __construct(private readonly FamilyGroupProvisioner $familyGroups) {}

    public function index(Request $request)
    {
        $group = $this->familyGroups->ensureForUser($request->user());

        $members = FamilyMember::query()
            ->where('family_group_id', $group->id)
            ->active()
            ->orderByRaw("relationship = 'self' desc")
            ->orderBy('created_at')
            ->get();

        return ApiResponse::success($members->map($this->serialize(...))->all());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'relationship' => ['required', 'string', 'in:spouse,parent,child,sibling,other'],
            'custom_label' => ['nullable', 'string', 'max:100'],
            'display_name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'max:20'],
            'blood_group' => ['nullable', 'string', 'max:10'],
            'is_guardian_managed' => ['sometimes', 'boolean'],
            'email' => ['nullable', 'email'],
            'access_level' => ['sometimes', 'string', 'in:full_access,self_only,view_only'],
        ]);

        $isGuardianManaged = $data['is_guardian_managed'] ?? false;

        if (! $isGuardianManaged && empty($data['email'])) {
            throw ValidationException::withMessages([
                'email' => 'An email is required to invite a family member with their own account.',
            ]);
        }

        $user = $request->user();
        $group = $this->familyGroups->ensureForUser($user);

        $member = new FamilyMember([
            'family_group_id' => $group->id,
            'added_by_user_id' => $user->id,
            'relationship' => $data['relationship'],
            'custom_label' => $data['custom_label'] ?? null,
            'display_name' => $data['display_name'],
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'gender' => $data['gender'] ?? null,
            'blood_group' => $data['blood_group'] ?? null,
            'is_guardian_managed' => $isGuardianManaged,
        ]);

        if ($isGuardianManaged) {
            $member->access_level = $data['access_level'] ?? 'full_access';
            $member->invite_status = 'accepted';
        } else {
            $member->access_level = $data['access_level'] ?? 'self_only';
            $member->invite_status = 'pending';
            $member->invite_token = Str::random(48);
            $member->invite_email = $data['email'];
            $member->invite_sent_at = now();
        }

        $member->save();

        if (! $isGuardianManaged) {
            Mail::to($member->invite_email)->send(new FamilyInviteMail($member, $user->name ?? 'A HealthSync user'));
        }

        AuditEvent::record('member.invite', $user->id, $request, [
            'family_member_id' => $member->id,
            'is_guardian_managed' => $isGuardianManaged,
        ]);

        return ApiResponse::success($this->serialize($member));
    }

    public function update(Request $request, string $id)
    {
        $member = $this->findOwnedMember($request, $id);

        if (! $member) {
            return ApiResponse::error('NOT_FOUND', 'Family member not found.', status: 404);
        }

        $data = $request->validate([
            'relationship' => ['sometimes', 'string', 'in:spouse,parent,child,sibling,other'],
            'custom_label' => ['sometimes', 'nullable', 'string', 'max:100'],
            'display_name' => ['sometimes', 'string', 'max:255'],
            'date_of_birth' => ['sometimes', 'nullable', 'date'],
            'gender' => ['sometimes', 'nullable', 'string', 'max:20'],
            'blood_group' => ['sometimes', 'nullable', 'string', 'max:10'],
            'access_level' => ['sometimes', 'string', 'in:full_access,self_only,view_only'],
        ]);

        if ($member->relationship === 'self' && array_key_exists('relationship', $data)) {
            return ApiResponse::error('SELF_MEMBER_IMMUTABLE', "You can't change your own relationship entry.", status: 422);
        }

        $member->update($data);

        return ApiResponse::success($this->serialize($member));
    }

    public function destroy(Request $request, string $id)
    {
        $member = $this->findOwnedMember($request, $id);

        if (! $member) {
            return ApiResponse::error('NOT_FOUND', 'Family member not found.', status: 404);
        }

        if ($member->relationship === 'self') {
            return ApiResponse::error('SELF_MEMBER_IMMUTABLE', "You can't remove yourself from your own family group.", status: 422);
        }

        $member->update(['removed_at' => now()]);

        return ApiResponse::success();
    }

    public function acceptInvite(Request $request)
    {
        $data = $request->validate(['invite_token' => ['required', 'string']]);

        $member = $this->findPendingInviteOrFail($request, $data['invite_token']);

        if ($member instanceof JsonResponse) {
            return $member;
        }

        $member->update([
            'member_user_id' => $request->user()->id,
            'invite_status' => 'accepted',
            'invite_token' => null,
        ]);

        AuditEvent::record('member.invite', $request->user()->id, $request, [
            'family_member_id' => $member->id,
            'action' => 'accepted',
        ]);

        return ApiResponse::success($this->serialize($member));
    }

    public function declineInvite(Request $request)
    {
        $data = $request->validate(['invite_token' => ['required', 'string']]);

        $member = $this->findPendingInviteOrFail($request, $data['invite_token']);

        if ($member instanceof JsonResponse) {
            return $member;
        }

        $member->update([
            'invite_status' => 'declined',
            'invite_token' => null,
        ]);

        return ApiResponse::success();
    }

    private function findPendingInviteOrFail(Request $request, string $token): FamilyMember|JsonResponse
    {
        $member = FamilyMember::query()
            ->where('invite_token', $token)
            ->where('invite_status', 'pending')
            ->active()
            ->first();

        if (! $member) {
            return ApiResponse::error('INVITE_INVALID', 'This invite is invalid or has already been used.', status: 404);
        }

        if (mb_strtolower($member->invite_email ?? '') !== mb_strtolower($request->user()->email)) {
            return ApiResponse::error('INVITE_EMAIL_MISMATCH', 'This invite was sent to a different email address.', status: 403);
        }

        return $member;
    }

    private function findOwnedMember(Request $request, string $id): ?FamilyMember
    {
        return FamilyMember::query()
            ->where('id', $id)
            ->whereHas('familyGroup', fn ($q) => $q->where('owner_id', $request->user()->id))
            ->active()
            ->first();
    }

    private function serialize(FamilyMember $member): array
    {
        return [
            'id' => $member->id,
            'relationship' => $member->relationship,
            'custom_label' => $member->custom_label,
            'display_name' => $member->display_name,
            'date_of_birth' => $member->date_of_birth?->toDateString(),
            'gender' => $member->gender,
            'blood_group' => $member->blood_group,
            'is_guardian_managed' => $member->is_guardian_managed,
            'access_level' => $member->access_level,
            'invite_status' => $member->invite_status,
            'invite_email' => $member->invite_email,
            'member_user_id' => $member->member_user_id,
            'created_at' => $member->created_at->toIso8601String(),
        ];
    }
}
