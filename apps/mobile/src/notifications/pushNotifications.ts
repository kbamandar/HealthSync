import * as Notifications from "expo-notifications";
import { Platform } from "react-native";

import { devicesApi } from "../api/devicesApi";

export type PushPermissionStatus = "granted" | "denied" | "undetermined";

export async function getPushPermissionStatus(): Promise<PushPermissionStatus> {
  const { status } = await Notifications.getPermissionsAsync();
  return status;
}

/**
 * Requests the OS push permission (the genuinely testable part of this
 * feature in Expo Go) and, if granted, tries to fetch an Expo push token and
 * register it with the backend. Token retrieval needs an EAS `projectId`,
 * which this sandbox has never been provisioned with — that failure is
 * caught and swallowed so permission opt-in still works and is recorded
 * even though there's no real push delivery to receive here.
 */
export async function requestPushPermissionAndRegister(): Promise<PushPermissionStatus> {
  const { status } = await Notifications.requestPermissionsAsync();

  if (status !== "granted") {
    return status;
  }

  if (Platform.OS === "android") {
    await Notifications.setNotificationChannelAsync("default", {
      name: "default",
      importance: Notifications.AndroidImportance.DEFAULT,
    });
  }

  try {
    const { data: pushToken } = await Notifications.getExpoPushTokenAsync();
    await devicesApi.register(pushToken, Platform.OS === "ios" ? "ios" : "android");
  } catch {
    // No EAS projectId configured in this sandbox — permission is still
    // genuinely granted; there's just no push token to register yet.
  }

  return status;
}
