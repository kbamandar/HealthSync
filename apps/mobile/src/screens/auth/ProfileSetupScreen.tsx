import { useState } from "react";
import { ActivityIndicator, ScrollView, StyleSheet, Text, TextInput, TouchableOpacity, View } from "react-native";

import { useAuth } from "../../auth/AuthContext";

const GENDERS = ["male", "female", "other"];
const BLOOD_GROUPS = ["A+", "A-", "B+", "B-", "AB+", "AB-", "O+", "O-"];

export default function ProfileSetupScreen() {
  const { completeProfile } = useAuth();
  const [name, setName] = useState("");
  const [dateOfBirth, setDateOfBirth] = useState("");
  const [gender, setGender] = useState<string | null>(null);
  const [bloodGroup, setBloodGroup] = useState<string | null>(null);
  const [isSaving, setIsSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function handleSave() {
    setError(null);
    setIsSaving(true);
    try {
      await completeProfile({
        name: name.trim(),
        ...(dateOfBirth.trim() ? { date_of_birth: dateOfBirth.trim() } : {}),
        ...(gender ? { gender } : {}),
        ...(bloodGroup ? { blood_group: bloodGroup } : {}),
      });
    } catch {
      setError("Couldn't save your profile. Try again.");
    } finally {
      setIsSaving(false);
    }
  }

  const canSubmit = name.trim().length > 0 && !isSaving;

  return (
    <ScrollView contentContainerStyle={styles.container}>
      <Text style={styles.title}>Complete your profile</Text>
      <Text style={styles.subtitle}>Just a few details to get started.</Text>

      <TextInput style={styles.input} placeholder="Full name" value={name} onChangeText={setName} />
      <TextInput
        style={styles.input}
        placeholder="Date of birth (YYYY-MM-DD)"
        value={dateOfBirth}
        onChangeText={setDateOfBirth}
      />

      <Text style={styles.label}>Gender</Text>
      <View style={styles.chipRow}>
        {GENDERS.map((option) => (
          <TouchableOpacity
            key={option}
            style={[styles.chip, gender === option && styles.chipSelected]}
            onPress={() => setGender(option)}
          >
            <Text style={[styles.chipText, gender === option && styles.chipTextSelected]}>{option}</Text>
          </TouchableOpacity>
        ))}
      </View>

      <Text style={styles.label}>Blood group</Text>
      <View style={styles.chipRow}>
        {BLOOD_GROUPS.map((option) => (
          <TouchableOpacity
            key={option}
            style={[styles.chip, bloodGroup === option && styles.chipSelected]}
            onPress={() => setBloodGroup(option)}
          >
            <Text style={[styles.chipText, bloodGroup === option && styles.chipTextSelected]}>{option}</Text>
          </TouchableOpacity>
        ))}
      </View>

      {error && <Text style={styles.error}>{error}</Text>}

      <TouchableOpacity
        style={[styles.button, !canSubmit && styles.buttonDisabled]}
        disabled={!canSubmit}
        onPress={handleSave}
      >
        {isSaving ? <ActivityIndicator color="#fff" /> : <Text style={styles.buttonText}>Continue</Text>}
      </TouchableOpacity>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flexGrow: 1, justifyContent: "center", padding: 24, gap: 12 },
  title: { fontSize: 24, fontWeight: "600", textAlign: "center" },
  subtitle: { fontSize: 14, color: "#666", textAlign: "center", marginBottom: 12 },
  input: {
    borderWidth: 1,
    borderColor: "#ddd",
    borderRadius: 8,
    padding: 12,
    fontSize: 16,
  },
  label: { fontSize: 13, color: "#666", marginTop: 8 },
  chipRow: { flexDirection: "row", flexWrap: "wrap", gap: 8 },
  chip: {
    borderWidth: 1,
    borderColor: "#ddd",
    borderRadius: 20,
    paddingVertical: 8,
    paddingHorizontal: 16,
  },
  chipSelected: { backgroundColor: "#0f766e", borderColor: "#0f766e" },
  chipText: { color: "#333" },
  chipTextSelected: { color: "#fff" },
  button: {
    backgroundColor: "#0f766e",
    borderRadius: 8,
    padding: 14,
    alignItems: "center",
    marginTop: 12,
  },
  buttonDisabled: { opacity: 0.5 },
  buttonText: { color: "#fff", fontWeight: "600", fontSize: 16 },
  error: { color: "#dc2626", fontSize: 14 },
});
