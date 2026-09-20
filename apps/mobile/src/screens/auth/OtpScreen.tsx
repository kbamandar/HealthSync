import { useState } from "react";
import { ActivityIndicator, StyleSheet, Text, TextInput, TouchableOpacity, View } from "react-native";

import { useAuth } from "../../auth/AuthContext";

const POLICY_VERSION = "1.0";

interface OtpScreenProps {
  email: string;
  mobile: string;
  onBack: () => void;
}

export default function OtpScreen({ email, mobile, onBack }: OtpScreenProps) {
  const { verifyOtp } = useAuth();
  const [otp, setOtp] = useState("");
  const [consentGiven, setConsentGiven] = useState(false);
  const [isVerifying, setIsVerifying] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function handleVerify() {
    setError(null);
    setIsVerifying(true);
    try {
      await verifyOtp(
        email,
        mobile,
        otp.trim(),
        consentGiven
          ? { privacy_policy_version: POLICY_VERSION, terms_of_service_version: POLICY_VERSION }
          : undefined,
      );
    } catch {
      setError("That code didn't work. Check it and try again.");
    } finally {
      setIsVerifying(false);
    }
  }

  const canSubmit = otp.trim().length === 6 && !isVerifying;

  return (
    <View style={styles.container}>
      <Text style={styles.title}>Enter your code</Text>
      <Text style={styles.subtitle}>We sent a 6-digit code to {email}.</Text>

      <TextInput
        style={styles.input}
        placeholder="123456"
        keyboardType="number-pad"
        maxLength={6}
        value={otp}
        onChangeText={setOtp}
      />

      <TouchableOpacity style={styles.consentRow} onPress={() => setConsentGiven((prev) => !prev)}>
        <View style={[styles.checkbox, consentGiven && styles.checkboxChecked]}>
          {consentGiven && <Text style={styles.checkboxMark}>✓</Text>}
        </View>
        <Text style={styles.consentText}>
          I agree to the Privacy Policy and Terms of Service (only required if you're new here).
        </Text>
      </TouchableOpacity>

      {error && <Text style={styles.error}>{error}</Text>}

      <TouchableOpacity
        style={[styles.button, !canSubmit && styles.buttonDisabled]}
        disabled={!canSubmit}
        onPress={handleVerify}
      >
        {isVerifying ? <ActivityIndicator color="#fff" /> : <Text style={styles.buttonText}>Verify</Text>}
      </TouchableOpacity>

      <TouchableOpacity onPress={onBack}>
        <Text style={styles.link}>Use a different email or mobile number</Text>
      </TouchableOpacity>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, justifyContent: "center", padding: 24, gap: 12 },
  title: { fontSize: 24, fontWeight: "600", textAlign: "center" },
  subtitle: { fontSize: 14, color: "#666", textAlign: "center", marginBottom: 12 },
  input: {
    borderWidth: 1,
    borderColor: "#ddd",
    borderRadius: 8,
    padding: 12,
    fontSize: 24,
    textAlign: "center",
    letterSpacing: 8,
  },
  consentRow: { flexDirection: "row", alignItems: "flex-start", gap: 10, marginTop: 8 },
  checkbox: {
    width: 22,
    height: 22,
    borderRadius: 4,
    borderWidth: 1,
    borderColor: "#999",
    alignItems: "center",
    justifyContent: "center",
    marginTop: 2,
  },
  checkboxChecked: { backgroundColor: "#0f766e", borderColor: "#0f766e" },
  checkboxMark: { color: "#fff", fontSize: 14, fontWeight: "700" },
  consentText: { flex: 1, fontSize: 13, color: "#444" },
  button: {
    backgroundColor: "#0f766e",
    borderRadius: 8,
    padding: 14,
    alignItems: "center",
    marginTop: 8,
  },
  buttonDisabled: { opacity: 0.5 },
  buttonText: { color: "#fff", fontWeight: "600", fontSize: 16 },
  error: { color: "#dc2626", fontSize: 14 },
  link: { color: "#0f766e", textAlign: "center", marginTop: 12, fontSize: 14 },
});
