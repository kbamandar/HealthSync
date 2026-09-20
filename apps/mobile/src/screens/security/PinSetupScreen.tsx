import { useState } from "react";
import { Alert } from "react-native";
import { useNavigation } from "@react-navigation/native";

import { appLockStorage } from "../../security/appLockStorage";
import PinPadScreen from "./PinPadScreen";

export default function PinSetupScreen() {
  const navigation = useNavigation();
  const [firstPin, setFirstPin] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  async function handleFirstEntry(pin: string) {
    setFirstPin(pin);
    setError(null);
  }

  async function handleConfirm(pin: string) {
    if (pin !== firstPin) {
      setError("PINs didn't match — enter a new PIN.");
      setFirstPin(null);
      return;
    }

    await appLockStorage.setPin(pin);
    Alert.alert("PIN set", "Your PIN has been updated.");
    navigation.goBack();
  }

  if (firstPin === null) {
    return (
      <PinPadScreen
        title="Set a 6-digit PIN"
        subtitle="Used to unlock HealthSync when biometrics fail"
        error={error}
        onComplete={handleFirstEntry}
      />
    );
  }

  return (
    <PinPadScreen title="Confirm your PIN" subtitle="Enter the same 6 digits again" onComplete={handleConfirm} />
  );
}
