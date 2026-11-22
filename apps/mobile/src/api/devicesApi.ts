import { apiRequest } from "./client";

export const devicesApi = {
  register(pushToken: string, platform: "ios" | "android") {
    return apiRequest<null>("/devices", { method: "POST", body: { push_token: pushToken, platform } });
  },
};
