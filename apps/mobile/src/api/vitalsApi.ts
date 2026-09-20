import { apiRequest } from "./client";
import type { VitalReading, VitalType } from "./types";

export interface VitalFilters {
  member_id?: string;
  vital_type?: VitalType;
  from?: string;
  to?: string;
}

export interface LogVitalInput {
  member_id: string;
  vital_type: VitalType;
  value: number;
  reading_context?: string;
  notes?: string;
  recorded_at?: string;
}

function toQueryString(filters: VitalFilters): string {
  const params = Object.entries(filters).filter(([, value]) => value !== undefined && value !== "");
  if (params.length === 0) return "";
  return "?" + new URLSearchParams(params as [string, string][]).toString();
}

export const vitalsApi = {
  list(filters: VitalFilters = {}) {
    return apiRequest<VitalReading[]>(`/vitals${toQueryString(filters)}`);
  },

  log(input: LogVitalInput) {
    return apiRequest<VitalReading>("/vitals", { method: "POST", body: input });
  },

  remove(id: string) {
    return apiRequest<null>(`/vitals/${id}`, { method: "DELETE" });
  },
};
