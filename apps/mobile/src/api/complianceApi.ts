import { apiRequest } from "./client";
import type { AccountDeletionStatus, AuditLogEntry } from "./types";

export const complianceApi = {
  auditLog(page = 1) {
    return apiRequest<AuditLogEntry[]>(`/audit-log?page=${page}`);
  },

  requestDataExport() {
    return apiRequest<{ message: string }>("/data-export", { method: "POST" });
  },

  requestAccountDeletion() {
    return apiRequest<AccountDeletionStatus>("/account/delete", { method: "POST" });
  },

  cancelAccountDeletion() {
    return apiRequest<null>("/account/delete", { method: "DELETE" });
  },
};
