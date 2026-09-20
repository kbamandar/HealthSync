import { useCallback, useState } from "react";

import { useAppLock } from "../../security/AppLockContext";
import LockScreen from "./LockScreen";
import PinPadScreen from "./PinPadScreen";

export default function AppLockGate() {
  const { isBiometricAvailable, unlockWithBiometrics, unlockWithPin } = useAppLock();
  const [mode, setMode] = useState<"biometric" | "pin">(isBiometricAvailable ? "biometric" : "pin");
  const [isAuthenticating, setIsAuthenticating] = useState(false);
  const [pinError, setPinError] = useState<string | null>(null);

  const handleBiometrics = useCallback(async () => {
    setIsAuthenticating(true);
    try {
      await unlockWithBiometrics();
    } finally {
      setIsAuthenticating(false);
    }
  }, [unlockWithBiometrics]);

  async function handlePinComplete(pin: string) {
    const valid = await unlockWithPin(pin);
    setPinError(valid ? null : "Incorrect PIN — try again.");
  }

  if (mode === "pin") {
    return (
      <PinPadScreen
        title="Enter your PIN"
        subtitle="Unlock HealthSync to continue"
        error={pinError}
        onComplete={handlePinComplete}
      />
    );
  }

  return (
    <LockScreen
      isBiometricAvailable={isBiometricAvailable}
      isAuthenticating={isAuthenticating}
      onUnlockWithBiometrics={handleBiometrics}
      onUsePin={() => setMode("pin")}
    />
  );
}
