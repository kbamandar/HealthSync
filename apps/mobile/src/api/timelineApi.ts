import { apiRequest } from "./client";
import type { TimelineEntry } from "./types";

export interface TimelineFilters {
  member_id?: string;
  from?: string;
  to?: string;
}

function toQueryString(filters: TimelineFilters): string {
  const params = Object.entries(filters).filter(([, value]) => value !== undefined && value !== "");
  if (params.length === 0) return "";
  return "?" + new URLSearchParams(params as [string, string][]).toString();
}

export const timelineApi = {
  list(filters: TimelineFilters = {}) {
    return apiRequest<TimelineEntry[]>(`/timeline${toQueryString(filters)}`);
  },
};
