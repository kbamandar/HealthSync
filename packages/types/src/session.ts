export type SessionPlatform = "ios" | "android" | "web";

/**
 * "Device trust management" (Sprint 7). Backed by refresh_tokens rather
 * than a separate sessions table — a live, non-revoked refresh token
 * already carries everything a session/device row would.
 */
export interface Session {
  id: string;
  deviceName: string | null;
  platform: SessionPlatform | null;
  ipAddress: string | null;
  isCurrent: boolean;
  createdAt: string;
  expiresAt: string;
}
