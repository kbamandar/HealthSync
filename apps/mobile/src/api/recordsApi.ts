import { apiRequest } from "./client";
import type { HealthRecord, RecordCategory, RecordFile, RecordUploadUrl } from "./types";

export interface RecordFilters {
  member_id?: string;
  category?: RecordCategory;
  q?: string;
}

export interface CreateRecordInput {
  member_id: string;
  category: RecordCategory;
  title?: string;
  record_date?: string;
  doctor_name?: string;
  hospital_clinic?: string;
  notes?: string;
  custom_tags?: string[];
}

export interface UpdateRecordInput {
  category?: RecordCategory;
  title?: string | null;
  record_date?: string | null;
  doctor_name?: string | null;
  hospital_clinic?: string | null;
  notes?: string | null;
  is_favourite?: boolean;
  custom_tags?: string[];
}

function toQueryString(filters: RecordFilters): string {
  const params = Object.entries(filters).filter(([, value]) => value !== undefined && value !== "");
  if (params.length === 0) return "";
  return "?" + new URLSearchParams(params as [string, string][]).toString();
}

export const recordsApi = {
  list(filters: RecordFilters = {}) {
    return apiRequest<HealthRecord[]>(`/records${toQueryString(filters)}`);
  },

  create(input: CreateRecordInput) {
    return apiRequest<HealthRecord>("/records", { method: "POST", body: input });
  },

  get(id: string) {
    return apiRequest<HealthRecord>(`/records/${id}`);
  },

  update(id: string, input: UpdateRecordInput) {
    return apiRequest<HealthRecord>(`/records/${id}`, { method: "PUT", body: input });
  },

  remove(id: string) {
    return apiRequest<null>(`/records/${id}`, { method: "DELETE" });
  },

  getUploadUrl(id: string, mimeType: string) {
    return apiRequest<RecordUploadUrl>(`/records/${id}/upload-url`, {
      method: "POST",
      body: { mime_type: mimeType },
    });
  },

  registerFile(id: string, input: { s3_key: string; mime_type: string }) {
    return apiRequest<RecordFile>(`/records/${id}/files`, { method: "POST", body: input });
  },

  recycleBin() {
    return apiRequest<HealthRecord[]>("/records/recycle-bin");
  },

  restore(id: string) {
    return apiRequest<HealthRecord>(`/records/${id}/restore`, { method: "POST" });
  },
};
