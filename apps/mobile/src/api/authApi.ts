import * as Device from "expo-device";
import { Platform } from "react-native";

import { apiRequest } from "./client";
import type { VerifyOtpResponse } from "./types";

export interface ConsentInput {
  privacy_policy_version: string;
  terms_of_service_version: string;
}

function currentDeviceInfo(): { device_name: string | null; platform: "ios" | "android" | "web" } {
  return {
    device_name: Device.deviceName ?? null,
    platform: Platform.OS === "ios" ? "ios" : Platform.OS === "android" ? "android" : "web",
  };
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
      body: { email, mobile, otp, ...currentDeviceInfo(), ...(consent ? { consent } : {}) },
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
