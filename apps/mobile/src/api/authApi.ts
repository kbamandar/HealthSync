import { apiRequest } from "./client";
import type { VerifyOtpResponse } from "./types";

export interface ConsentInput {
  privacy_policy_version: string;
  terms_of_service_version: string;
}

export const authApi = {
  sendOtp(email: string, mobile: string) {
    return apiRequest<{ expires_in: number }>("/auth/otp/send", {
      method: "POST",
      body: { email, mobile },
      auth: false,
    });
  },

  verifyOtp(email: string, mobile: string, otp: string, consent?: ConsentInput) {
    return apiRequest<VerifyOtpResponse>("/auth/otp/verify", {
      method: "POST",
      body: { email, mobile, otp, ...(consent ? { consent } : {}) },
      auth: false,
    });
  },

  logout(refreshToken: string) {
    return apiRequest<null>("/auth/logout", {
      method: "POST",
      body: { refresh_token: refreshToken },
      auth: false,
    });
  },
};
