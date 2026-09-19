<?php

namespace App\Services\Family;

use App\Models\FamilyGroup;
use App\Models\FamilyMember;
use App\Models\User;

/**
 * Every user owns exactly one family group and has a 'self' member row in
 * it — neither is explicit in the product schema doc, but both are needed
 * for the user to have somewhere for their own records/vitals to attach to
 * (Sprint 3+). Provisioned lazily once a name exists (family_members.
 * display_name is required, and name isn't known until profile setup —
 * Sprint 1 — completes).
 */
class FamilyGroupProvisioner
{
    public function ensureForUser(User $user): FamilyGroup
    {
        $group = FamilyGroup::firstOrCreate(['owner_id' => $user->id]);

        if ($user->name !== null) {
            FamilyMember::updateOrCreate(
                [
                    'family_group_id' => $group->id,
                    'member_user_id' => $user->id,
                    'relationship' => 'self',
                ],
                [
                    'added_by_user_id' => $user->id,
                    'display_name' => $user->name,
                    'date_of_birth' => $user->date_of_birth,
                    'gender' => $user->gender,
                    'blood_group' => $user->blood_group,
                    'is_guardian_managed' => false,
                    'access_level' => 'full_access',
                    'invite_status' => 'accepted',
                ],
            );
        }

        return $group;
    }
}
