import { apiRequest } from "./client";
import type { Session } from "./types";

export const sessionsApi = {
  list() {
    return apiRequest<Session[]>("/sessions");
  },

  revoke(id: string) {
    return apiRequest<null>(`/sessions/${id}`, { method: "DELETE" });
  },
};
