import { apiRequest } from "./client";
import type { Dashboard } from "./types";

export const dashboardApi = {
  get(memberId?: string) {
    const query = memberId ? `?member_id=${memberId}` : "";
    return apiRequest<Dashboard>(`/dashboard${query}`);
  },
};
