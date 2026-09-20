import type { BloodGroup, Gender } from "./user";

// "self" isn't in the product schema doc's relationship list, but every
// family group needs exactly one member representing its owner (Sprint 2 —
// see FamilyGroupProvisioner on the API side) so records/vitals have
// something of the owner's own to attach to.
export type FamilyRelationship = "self" | "spouse" | "parent" | "child" | "sibling" | "other";
export type AccessLevel = "full_access" | "self_only" | "view_only";
export type InviteStatus = "pending" | "accepted" | "declined";

export interface FamilyGroup {
  id: string;
  ownerId: string;
  name: string | null;
  createdAt: string;
}

export interface FamilyMember {
  id: string;
  familyGroupId: string;
  memberUserId: string | null;
  addedByUserId: string;
  relationship: FamilyRelationship;
  customLabel: string | null;
  displayName: string;
  dateOfBirth: string | null;
  gender: Gender | null;
  bloodGroup: BloodGroup | null;
  isGuardianManaged: boolean;
  accessLevel: AccessLevel;
  inviteStatus: InviteStatus;
  inviteToken: string | null;
  // Holds the invitee's contact address until they accept and memberUserId
  // is filled in — added in Sprint 2, not in the original schema doc.
  inviteEmail: string | null;
  inviteSentAt: string | null;
  createdAt: string;
  updatedAt: string;
  // Soft-remove marker (Sprint 2) — the schema doc calls DELETE a
  // "soft-remove" but didn't define a column for it.
  removedAt: string | null;
}
