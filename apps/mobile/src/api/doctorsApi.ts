import { apiRequest } from "./client";
import type { Doctor, DoctorVisit } from "./types";

export interface DoctorInput {
  name: string;
  speciality?: string | null;
  hospital_clinic?: string | null;
  phone?: string | null;
  location?: string | null;
  notes?: string | null;
}

export const doctorsApi = {
  list(query?: string) {
    return apiRequest<Doctor[]>(`/doctors${query ? `?q=${encodeURIComponent(query)}` : ""}`);
  },

  create(input: DoctorInput) {
    return apiRequest<Doctor>("/doctors", { method: "POST", body: input });
  },

  update(id: string, input: Partial<DoctorInput>) {
    return apiRequest<Doctor>(`/doctors/${id}`, { method: "PUT", body: input });
  },

  remove(id: string) {
    return apiRequest<null>(`/doctors/${id}`, { method: "DELETE" });
  },

  visits(id: string) {
    return apiRequest<DoctorVisit[]>(`/doctors/${id}/visits`);
  },
};
