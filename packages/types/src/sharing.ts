export type SharedLinkType = "record" | "health_summary";

export interface SharedLink {
  id: string;
  createdById: string;
  linkType: SharedLinkType;
  recordId: string | null;
  memberId: string | null;
  token: string;
  expiresAt: string;
  accessCount: number;
  maxAccess: number | null;
  isRevoked: boolean;
  createdAt: string;
}
