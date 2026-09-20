import { useState } from "react";
import { ActivityIndicator, ScrollView, StyleSheet, Text, TextInput, TouchableOpacity, View } from "react-native";
import type { NativeStackScreenProps } from "@react-navigation/native-stack";

import { useFamily } from "../../family/FamilyContext";
import type { FamilyStackParamList } from "../../navigation/FamilyStack";
import type { AccessLevel, FamilyRelationship } from "../../api/types";

const RELATIONSHIPS: FamilyRelationship[] = ["spouse", "parent", "child", "sibling", "other"];
const ACCESS_LEVELS: { value: AccessLevel; label: string }[] = [
  { value: "full_access", label: "Full access" },
  { value: "self_only", label: "Self only" },
  { value: "view_only", label: "View only" },
];

type Props = NativeStackScreenProps<FamilyStackParamList, "AddMember">;

export default function AddFamilyMemberScreen({ navigation }: Props) {
  const { addMember } = useFamily();
  const [relationship, setRelationship] = useState<FamilyRelationship | null>(null);
  const [mode, setMode] = useState<"guardian" | "invite">("guardian");
  const [displayName, setDisplayName] = useState("");
  const [dateOfBirth, setDateOfBirth] = useState("");
  const [email, setEmail] = useState("");
  const [accessLevel, setAccessLevel] = useState<AccessLevel>("self_only");
  const [isSaving, setIsSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function handleSubmit() {
    if (!relationship) return;

    setError(null);
    setIsSaving(true);
    try {
      if (mode === "guardian") {
        await addMember({
          relationship,
          display_name: displayName.trim(),
          is_guardian_managed: true,
          ...(dateOfBirth.trim() ? { date_of_birth: dateOfBirth.trim() } : {}),
        });
      } else {
        await addMember({
          relationship,
          display_name: displayName.trim(),
          email: email.trim(),
          access_level: accessLevel,
        });
      }
      navigation.goBack();
    } catch {
      setError("Couldn't add this family member. Check the details and try again.");
    } finally {
      setIsSaving(false);
    }
  }

  const canSubmit =
    relationship !== null &&
    displayName.trim().length > 0 &&
    (mode === "guardian" || email.includes("@")) &&
    !isSaving;

  return (
    <ScrollView contentContainerStyle={styles.container}>
      <Text style={styles.label}>Relationship</Text>
      <View style={styles.chipRow}>
        {RELATIONSHIPS.map((option) => (
          <TouchableOpacity
            key={option}
            style={[styles.chip, relationship === option && styles.chipSelected]}
            onPress={() => setRelationship(option)}
          >
            <Text style={[styles.chipText, relationship === option && styles.chipTextSelected]}>{option}</Text>
          </TouchableOpacity>
        ))}
      </View>

      <TextInput style={styles.input} placeholder="Name" value={displayName} onChangeText={setDisplayName} />

      <Text style={styles.label}>How do they use HealthSync?</Text>
      <View style={styles.modeRow}>
        <TouchableOpacity
          style={[styles.modeButton, mode === "guardian" && styles.modeButtonSelected]}
          onPress={() => setMode("guardian")}
        >
          <Text style={[styles.modeText, mode === "guardian" && styles.modeTextSelected]}>
            I manage their records
          </Text>
          <Text style={styles.modeSubtext}>No account of their own (e.g. a young child)</Text>
        </TouchableOpacity>
        <TouchableOpacity
          style={[styles.modeButton, mode === "invite" && styles.modeButtonSelected]}
          onPress={() => setMode("invite")}
        >
          <Text style={[styles.modeText, mode === "invite" && styles.modeTextSelected]}>Invite them</Text>
          <Text style={styles.modeSubtext}>They'll get their own HealthSync account</Text>
        </TouchableOpacity>
      </View>

      {mode === "guardian" ? (
        <TextInput
          style={styles.input}
          placeholder="Date of birth (YYYY-MM-DD)"
          value={dateOfBirth}
          onChangeText={setDateOfBirth}
        />
      ) : (
        <>
          <TextInput
            style={styles.input}
            placeholder="Their email address"
            autoCapitalize="none"
            keyboardType="email-address"
            value={email}
            onChangeText={setEmail}
          />
          <Text style={styles.label}>Access level</Text>
          <View style={styles.chipRow}>
            {ACCESS_LEVELS.map((option) => (
              <TouchableOpacity
                key={option.value}
                style={[styles.chip, accessLevel === option.value && styles.chipSelected]}
                onPress={() => setAccessLevel(option.value)}
              >
                <Text style={[styles.chipText, accessLevel === option.value && styles.chipTextSelected]}>
                  {option.label}
                </Text>
              </TouchableOpacity>
            ))}
          </View>
        </>
      )}

      {error && <Text style={styles.error}>{error}</Text>}

      <TouchableOpacity style={[styles.button, !canSubmit && styles.buttonDisabled]} disabled={!canSubmit} onPress={handleSubmit}>
        {isSaving ? <ActivityIndicator color="#fff" /> : <Text style={styles.buttonText}>Add family member</Text>}
      </TouchableOpacity>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { padding: 24, gap: 12 },
  label: { fontSize: 13, color: "#666", marginTop: 8 },
  input: {
    borderWidth: 1,
    borderColor: "#ddd",
    borderRadius: 8,
    padding: 12,
    fontSize: 16,
  },
  chipRow: { flexDirection: "row", flexWrap: "wrap", gap: 8 },
  chip: { borderWidth: 1, borderColor: "#ddd", borderRadius: 20, paddingVertical: 8, paddingHorizontal: 16 },
  chipSelected: { backgroundColor: "#0f766e", borderColor: "#0f766e" },
  chipText: { color: "#333", textTransform: "capitalize" },
  chipTextSelected: { color: "#fff" },
  modeRow: { gap: 10 },
  modeButton: { borderWidth: 1, borderColor: "#ddd", borderRadius: 10, padding: 12 },
  modeButtonSelected: { borderColor: "#0f766e", backgroundColor: "#f0fdfa" },
  modeText: { fontWeight: "600", fontSize: 15 },
  modeTextSelected: { color: "#0f766e" },
  modeSubtext: { fontSize: 12, color: "#666", marginTop: 2 },
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
