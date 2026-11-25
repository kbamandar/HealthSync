export type AuditEventType =
  | "record.upload"
  | "record.view"
  | "link.create"
  | "link.access"
  | "member.invite"
  | "login.otp"
  | "auth.logout"
  | "session.revoke"
  | "data_export.requested"
  | "account_deletion.requested"
  | "account_deletion.cancelled";

export interface AuditEvent {
  id: number;
  userId: string | null;
  familyGroupId: string | null;
  eventType: AuditEventType;
  resourceType: string | null;
  resourceId: string | null;
  ipAddress: string | null;
  userAgent: string | null;
  metadata: Record<string, unknown> | null;
  createdAt: string;
}

export type ConsentType = "privacy_policy" | "terms_of_service" | "data_processing";

export interface ConsentRecord {
  id: string;
  userId: string;
  consentType: ConsentType;
  version: string;
  consentedAt: string;
  ipAddress: string | null;
  userAgent: string | null;
}
