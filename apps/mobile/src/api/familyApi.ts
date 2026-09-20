import { apiRequest } from "./client";
import type { AccessLevel, FamilyMember, FamilyRelationship } from "./types";

export interface AddGuardianManagedMemberInput {
  relationship: FamilyRelationship;
  display_name: string;
  is_guardian_managed: true;
  date_of_birth?: string;
  gender?: string;
  blood_group?: string;
  access_level?: AccessLevel;
}

export interface InviteFamilyMemberInput {
  relationship: FamilyRelationship;
  display_name: string;
  email: string;
  access_level?: AccessLevel;
}

export type AddFamilyMemberInput = AddGuardianManagedMemberInput | InviteFamilyMemberInput;

export interface UpdateFamilyMemberInput {
  relationship?: FamilyRelationship;
  custom_label?: string | null;
  display_name?: string;
  date_of_birth?: string | null;
  gender?: string | null;
  blood_group?: string | null;
  access_level?: AccessLevel;
}

export const familyApi = {
  list() {
    return apiRequest<FamilyMember[]>("/family/members");
  },

  add(input: AddFamilyMemberInput) {
    return apiRequest<FamilyMember>("/family/members", { method: "POST", body: input });
  },

  update(id: string, input: UpdateFamilyMemberInput) {
    return apiRequest<FamilyMember>(`/family/members/${id}`, { method: "PUT", body: input });
  },

  remove(id: string) {
    return apiRequest<null>(`/family/members/${id}`, { method: "DELETE" });
  },

  acceptInvite(inviteToken: string) {
    return apiRequest<FamilyMember>("/family/invite/accept", {
      method: "POST",
      body: { invite_token: inviteToken },
    });
  },

  declineInvite(inviteToken: string) {
    return apiRequest<null>("/family/invite/decline", {
      method: "POST",
      body: { invite_token: inviteToken },
    });
  },
};
