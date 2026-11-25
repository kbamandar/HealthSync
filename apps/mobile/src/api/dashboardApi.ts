import { apiRequest } from "./client";
import type { Dashboard, HealthInsightsResponse, HealthScore } from "./types";

export const dashboardApi = {
  get(memberId?: string) {
    const query = memberId ? `?member_id=${memberId}` : "";
    return apiRequest<Dashboard>(`/dashboard${query}`);
  },

  getHealthScore(memberId: string) {
    return apiRequest<HealthScore>(`/members/${memberId}/health-score`);
  },

  getHealthInsights(memberId: string) {
    return apiRequest<HealthInsightsResponse>(`/members/${memberId}/health-insights`);
  },
};
