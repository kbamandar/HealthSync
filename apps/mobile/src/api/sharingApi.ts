import { apiRequest } from "./client";
import type { PublicSharedRecord, SharedLink, SharedLinkWithQr } from "./types";

export const sharingApi = {
  shareRecord(recordId: string, expiresInDays?: number) {
    return apiRequest<SharedLinkWithQr>("/share/record", {
      method: "POST",
      body: { record_id: recordId, ...(expiresInDays ? { expires_in_days: expiresInDays } : {}) },
    });
  },

  shareSummary(memberId: string, expiresInDays?: number) {
    return apiRequest<SharedLinkWithQr>("/share/summary", {
      method: "POST",
      body: { member_id: memberId, ...(expiresInDays ? { expires_in_days: expiresInDays } : {}) },
    });
  },

  list() {
    return apiRequest<SharedLink[]>("/share");
  },

  revoke(id: string) {
    return apiRequest<null>(`/share/${id}`, { method: "DELETE" });
  },

  publicAccess(token: string) {
    return apiRequest<PublicSharedRecord>(`/public/share/${token}`, { auth: false });
  },
};
