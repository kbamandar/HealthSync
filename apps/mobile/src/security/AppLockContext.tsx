import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState, type ReactNode } from "react";
import { AppState, type AppStateStatus } from "react-native";
import * as LocalAuthentication from "expo-local-authentication";

import { appLockStorage } from "./appLockStorage";

interface AppLockContextValue {
  isLockEnabled: boolean;
  isLocked: boolean;
  isBiometricAvailable: boolean;
  refreshSettings: () => Promise<void>;
  unlockWithBiometrics: () => Promise<boolean>;
  unlockWithPin: (pin: string) => Promise<boolean>;
  lockNow: () => void;
}

const AppLockContext = createContext<AppLockContextValue | null>(null);

export function AppLockProvider({ children }: { children: ReactNode }) {
  const [isLockEnabled, setIsLockEnabled] = useState(false);
  const [isLocked, setIsLocked] = useState(false);
  const [isBiometricAvailable, setIsBiometricAvailable] = useState(false);
  const backgroundedAt = useRef<number | null>(null);
  const appState = useRef<AppStateStatus>(AppState.currentState);

  const refreshSettings = useCallback(async () => {
    const [enabled, hardware, enrolled] = await Promise.all([
      appLockStorage.isLockEnabled(),
      LocalAuthentication.hasHardwareAsync(),
      LocalAuthentication.isEnrolledAsync(),
    ]);

    setIsLockEnabled(enabled);
    setIsBiometricAvailable(hardware && enrolled);

    if (enabled) {
      setIsLocked(true);
    }
  }, []);

  useEffect(() => {
    refreshSettings();
  }, [refreshSettings]);

  useEffect(() => {
    const subscription = AppState.addEventListener("change", async (nextState) => {
      if (appState.current.match(/active/) && nextState.match(/inactive|background/)) {
        backgroundedAt.current = Date.now();
      }

      if (appState.current.match(/inactive|background/) && nextState === "active") {
        const timeoutSeconds = await appLockStorage.getAutoLockTimeoutSeconds();
        const elapsedSeconds = backgroundedAt.current ? (Date.now() - backgroundedAt.current) / 1000 : Infinity;

        if (isLockEnabled && elapsedSeconds >= timeoutSeconds) {
          setIsLocked(true);
        }
      }

      appState.current = nextState;
    });

    return () => subscription.remove();
  }, [isLockEnabled]);

  const unlockWithBiometrics = useCallback(async (): Promise<boolean> => {
    const result = await LocalAuthentication.authenticateAsync({
      promptMessage: "Unlock HealthSync",
      disableDeviceFallback: true,
    });

    if (result.success) {
      setIsLocked(false);
    }

    return result.success;
  }, []);

  const unlockWithPin = useCallback(async (pin: string): Promise<boolean> => {
    const valid = await appLockStorage.verifyPin(pin);
    if (valid) {
      setIsLocked(false);
    }
    return valid;
  }, []);

  const lockNow = useCallback(() => {
    if (isLockEnabled) {
      setIsLocked(true);
    }
  }, [isLockEnabled]);

  const value = useMemo(
    () => ({
      isLockEnabled,
      isLocked,
      isBiometricAvailable,
      refreshSettings,
      unlockWithBiometrics,
      unlockWithPin,
      lockNow,
    }),
    [isLockEnabled, isLocked, isBiometricAvailable, refreshSettings, unlockWithBiometrics, unlockWithPin, lockNow],
  );

  return <AppLockContext.Provider value={value}>{children}</AppLockContext.Provider>;
}

export function useAppLock(): AppLockContextValue {
  const context = useContext(AppLockContext);
  if (!context) {
    throw new Error("useAppLock must be used within an AppLockProvider");
  }
  return context;
}
