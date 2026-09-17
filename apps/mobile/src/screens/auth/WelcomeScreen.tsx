import { useState } from "react";
import { ActivityIndicator, StyleSheet, Text, TextInput, TouchableOpacity, View } from "react-native";

import { useAuth } from "../../auth/AuthContext";

interface WelcomeScreenProps {
  onCodeSent: (email: string, mobile: string) => void;
}

export default function WelcomeScreen({ onCodeSent }: WelcomeScreenProps) {
  const { sendOtp } = useAuth();
  const [email, setEmail] = useState("");
  const [mobile, setMobile] = useState("");
  const [isSending, setIsSending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function handleSend() {
    setError(null);
    setIsSending(true);
    try {
      await sendOtp(email.trim(), mobile.trim());
      onCodeSent(email.trim(), mobile.trim());
    } catch {
      setError("Couldn't send a code. Check the details and try again.");
    } finally {
      setIsSending(false);
    }
  }

  const canSubmit = email.includes("@") && mobile.trim().length >= 8 && !isSending;

  return (
    <View style={styles.container}>
      <Text style={styles.title}>Welcome to HealthSync</Text>
      <Text style={styles.subtitle}>We'll email you a verification code.</Text>

      <TextInput
        style={styles.input}
        placeholder="Email address"
        autoCapitalize="none"
        keyboardType="email-address"
        value={email}
        onChangeText={setEmail}
      />
      <TextInput
        style={styles.input}
        placeholder="Mobile number (e.g. +919999999999)"
        keyboardType="phone-pad"
        value={mobile}
        onChangeText={setMobile}
      />

      {error && <Text style={styles.error}>{error}</Text>}

      <TouchableOpacity
        style={[styles.button, !canSubmit && styles.buttonDisabled]}
        disabled={!canSubmit}
        onPress={handleSend}
      >
        {isSending ? <ActivityIndicator color="#fff" /> : <Text style={styles.buttonText}>Send code</Text>}
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
    fontSize: 16,
  },
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
});
