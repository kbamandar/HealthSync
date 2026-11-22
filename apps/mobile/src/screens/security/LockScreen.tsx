import { useEffect } from "react";
import { ActivityIndicator, StyleSheet, Text, TouchableOpacity, View } from "react-native";

interface LockScreenProps {
  isBiometricAvailable: boolean;
  isAuthenticating: boolean;
  onUnlockWithBiometrics: () => void;
  onUsePin: () => void;
}

export default function LockScreen({
  isBiometricAvailable,
  isAuthenticating,
  onUnlockWithBiometrics,
  onUsePin,
}: LockScreenProps) {
  useEffect(() => {
    if (isBiometricAvailable) {
      onUnlockWithBiometrics();
    }
    // Only auto-trigger once, on mount.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  return (
    <View style={styles.container}>
      <View style={styles.iconCircle}>
        <Text style={styles.icon}>{"\u{1F512}"}</Text>
      </View>
      <Text style={styles.title}>HealthSync is locked</Text>
      <Text style={styles.subtitle}>
        {isBiometricAvailable ? "Use Face ID to continue" : "Enter your PIN to continue"}
      </Text>

      {isBiometricAvailable && (
        <TouchableOpacity style={styles.button} onPress={onUnlockWithBiometrics} disabled={isAuthenticating}>
          {isAuthenticating ? (
            <ActivityIndicator color="#fff" />
          ) : (
            <Text style={styles.buttonText}>Unlock with Face ID</Text>
          )}
        </TouchableOpacity>
      )}

      <TouchableOpacity onPress={onUsePin}>
        <Text style={styles.pinLink}>{isBiometricAvailable ? "Use PIN instead" : "Enter PIN"}</Text>
      </TouchableOpacity>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: "#f8fafc", alignItems: "center", justifyContent: "center", gap: 28, paddingHorizontal: 32 },
  iconCircle: { width: 88, height: 88, borderRadius: 44, backgroundColor: "#f0fdfa", alignItems: "center", justifyContent: "center" },
  icon: { fontSize: 36 },
  title: { fontSize: 20, fontWeight: "700", color: "#0f172a" },
  subtitle: { fontSize: 14, color: "#64748b" },
  button: { backgroundColor: "#0f766e", borderRadius: 8, paddingVertical: 14, paddingHorizontal: 24, alignItems: "center" },
  buttonText: { color: "#fff", fontWeight: "600", fontSize: 15 },
  pinLink: { color: "#0f766e", fontWeight: "600", fontSize: 14 },
});
