import type { BloodGroup, Gender } from "./user";

export type FamilyRelationship = "spouse" | "parent" | "child" | "sibling" | "other";
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
  inviteSentAt: string | null;
  createdAt: string;
  updatedAt: string;
}
