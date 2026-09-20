import { apiRequest } from "./client";
import type { UserProfile } from "./types";

export interface UpdateProfileInput {
  name?: string;
  date_of_birth?: string;
  gender?: string;
  blood_group?: string;
  profile_photo?: string;
  emergency_contact_name?: string;
  emergency_contact_mobile?: string;
}

export const usersApi = {
  me() {
    return apiRequest<UserProfile>("/users/me");
  },

  updateMe(input: UpdateProfileInput) {
    return apiRequest<UserProfile>("/users/me", { method: "PUT", body: input });
  },
};
