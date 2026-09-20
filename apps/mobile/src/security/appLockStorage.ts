import * as Crypto from "expo-crypto";
import * as SecureStore from "expo-secure-store";

const BIOMETRIC_ENABLED_KEY = "healthsync.lock.biometric_enabled";
const PIN_HASH_KEY = "healthsync.lock.pin_hash";
const AUTO_LOCK_TIMEOUT_KEY = "healthsync.lock.auto_lock_timeout_seconds";

/** Auto-lock timeout options, in seconds. 0 = lock immediately on background. */
export const AUTO_LOCK_TIMEOUT_OPTIONS = [
  { value: 0, label: "Immediately" },
  { value: 30, label: "After 30 seconds" },
  { value: 60, label: "After 1 minute" },
  { value: 300, label: "After 5 minutes" },
] as const;

const DEFAULT_TIMEOUT_SECONDS = 0;

async function hashPin(pin: string): Promise<string> {
  return Crypto.digestStringAsync(Crypto.CryptoDigestAlgorithm.SHA256, pin);
}

export const appLockStorage = {
  async isBiometricEnabled(): Promise<boolean> {
    return (await SecureStore.getItemAsync(BIOMETRIC_ENABLED_KEY)) === "true";
  },

  async setBiometricEnabled(enabled: boolean): Promise<void> {
    await SecureStore.setItemAsync(BIOMETRIC_ENABLED_KEY, enabled ? "true" : "false");
  },

  async hasPin(): Promise<boolean> {
    return (await SecureStore.getItemAsync(PIN_HASH_KEY)) !== null;
  },

  async setPin(pin: string): Promise<void> {
    await SecureStore.setItemAsync(PIN_HASH_KEY, await hashPin(pin));
  },

  async verifyPin(pin: string): Promise<boolean> {
    const stored = await SecureStore.getItemAsync(PIN_HASH_KEY);
    if (!stored) return false;
    return stored === (await hashPin(pin));
  },

  async clearPin(): Promise<void> {
    await SecureStore.deleteItemAsync(PIN_HASH_KEY);
  },

  async getAutoLockTimeoutSeconds(): Promise<number> {
    const stored = await SecureStore.getItemAsync(AUTO_LOCK_TIMEOUT_KEY);
    return stored ? Number(stored) : DEFAULT_TIMEOUT_SECONDS;
  },

  async setAutoLockTimeoutSeconds(seconds: number): Promise<void> {
    await SecureStore.setItemAsync(AUTO_LOCK_TIMEOUT_KEY, String(seconds));
  },

  async isLockEnabled(): Promise<boolean> {
    const [biometric, pin] = await Promise.all([this.isBiometricEnabled(), this.hasPin()]);
    return biometric || pin;
  },

  async clearAll(): Promise<void> {
    await Promise.all([
      SecureStore.deleteItemAsync(BIOMETRIC_ENABLED_KEY),
      SecureStore.deleteItemAsync(PIN_HASH_KEY),
      SecureStore.deleteItemAsync(AUTO_LOCK_TIMEOUT_KEY),
    ]);
  },
};
