import { useState } from "react";
import { ActivityIndicator, ScrollView, StyleSheet, Text, TextInput, TouchableOpacity, View } from "react-native";
import type { NativeStackScreenProps } from "@react-navigation/native-stack";

import { vitalsApi } from "../../api/vitalsApi";
import type { VitalType } from "../../api/types";
import { useFamily } from "../../family/FamilyContext";
import { VITAL_TABS } from "../../vitals/vitalTabs";
import type { VitalsStackParamList } from "../../navigation/VitalsStack";

const CONTEXTS = ["Morning", "Evening", "After exercise", "After medication"];

type Props = NativeStackScreenProps<VitalsStackParamList, "LogVital">;

export default function LogVitalScreen({ route, navigation }: Props) {
  const { selectedMember } = useFamily();
  const tab = VITAL_TABS.find((t) => t.key === route.params.tabKey)!;

  const [selectedType, setSelectedType] = useState<VitalType>(tab.options[0].type);
  const [systolic, setSystolic] = useState("");
  const [diastolic, setDiastolic] = useState("");
  const [value, setValue] = useState("");
  const [context, setContext] = useState<string | null>(null);
  const [notes, setNotes] = useState("");
  const [isSaving, setIsSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const canSubmit = tab.isBloodPressure
    ? systolic.trim().length > 0 && diastolic.trim().length > 0
    : value.trim().length > 0;

  async function handleSave() {
    if (!selectedMember || !canSubmit) return;

    setError(null);
    setIsSaving(true);
    try {
      const recordedAt = new Date().toISOString();

      if (tab.isBloodPressure) {
        await vitalsApi.log({
          member_id: selectedMember.id,
          vital_type: "bp_systolic",
          value: Number(systolic),
          recorded_at: recordedAt,
          ...(context ? { reading_context: context } : {}),
          ...(notes.trim() ? { notes: notes.trim() } : {}),
        });
        await vitalsApi.log({
          member_id: selectedMember.id,
          vital_type: "bp_diastolic",
          value: Number(diastolic),
          recorded_at: recordedAt,
          ...(context ? { reading_context: context } : {}),
          ...(notes.trim() ? { notes: notes.trim() } : {}),
        });
      } else {
        await vitalsApi.log({
          member_id: selectedMember.id,
          vital_type: selectedType,
          value: Number(value),
          ...(context ? { reading_context: context } : {}),
          ...(notes.trim() ? { notes: notes.trim() } : {}),
        });
      }

      navigation.goBack();
    } catch {
      setError("Couldn't save this reading. Check the value and try again.");
    } finally {
      setIsSaving(false);
    }
  }

  return (
    <ScrollView contentContainerStyle={styles.container}>
      <Text style={styles.title}>Log a {tab.label.toLowerCase()} reading</Text>

      {tab.isBloodPressure ? (
        <View style={styles.row}>
          <TextInput
            style={[styles.input, styles.flexInput]}
            placeholder="Systolic"
            keyboardType="numeric"
            value={systolic}
            onChangeText={setSystolic}
          />
          <TextInput
            style={[styles.input, styles.flexInput]}
            placeholder="Diastolic"
            keyboardType="numeric"
            value={diastolic}
            onChangeText={setDiastolic}
          />
        </View>
      ) : (
        <>
          {tab.options.length > 1 && (
            <View style={styles.chipRow}>
              {tab.options.map((option) => (
                <TouchableOpacity
                  key={option.type}
                  style={[styles.chip, selectedType === option.type && styles.chipSelected]}
                  onPress={() => setSelectedType(option.type)}
                >
                  <Text style={[styles.chipText, selectedType === option.type && styles.chipTextSelected]}>
                    {option.label}
                  </Text>
                </TouchableOpacity>
              ))}
            </View>
          )}
          <TextInput
            style={styles.input}
            placeholder={tab.options.find((o) => o.type === selectedType)?.label ?? "Value"}
            keyboardType="numeric"
            value={value}
            onChangeText={setValue}
          />
        </>
      )}

      <Text style={styles.label}>Reading context</Text>
      <View style={styles.chipRow}>
        {CONTEXTS.map((option) => (
          <TouchableOpacity
            key={option}
            style={[styles.chip, context === option && styles.chipSelected]}
            onPress={() => setContext(context === option ? null : option)}
          >
            <Text style={[styles.chipText, context === option && styles.chipTextSelected]}>{option}</Text>
          </TouchableOpacity>
        ))}
      </View>

      <TextInput
        style={styles.input}
        placeholder="Notes (optional)"
        value={notes}
        onChangeText={setNotes}
      />

      {error && <Text style={styles.error}>{error}</Text>}

      <TouchableOpacity
        style={[styles.button, !canSubmit && styles.buttonDisabled]}
        disabled={!canSubmit || isSaving}
        onPress={handleSave}
      >
        {isSaving ? <ActivityIndicator color="#fff" /> : <Text style={styles.buttonText}>Save reading</Text>}
      </TouchableOpacity>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { padding: 20, gap: 14 },
  title: { fontSize: 20, fontWeight: "700" },
  row: { flexDirection: "row", gap: 10 },
  flexInput: { flex: 1 },
  label: { fontSize: 13, color: "#666" },
  chipRow: { flexDirection: "row", flexWrap: "wrap", gap: 8 },
  chip: { borderWidth: 1, borderColor: "#ddd", borderRadius: 20, paddingVertical: 8, paddingHorizontal: 16 },
  chipSelected: { backgroundColor: "#0f766e", borderColor: "#0f766e" },
  chipText: { color: "#333" },
  chipTextSelected: { color: "#fff" },
  input: { borderWidth: 1, borderColor: "#ddd", borderRadius: 8, padding: 12, fontSize: 16 },
  button: { backgroundColor: "#0f766e", borderRadius: 8, padding: 14, alignItems: "center", marginTop: 8 },
  buttonDisabled: { opacity: 0.5 },
  buttonText: { color: "#fff", fontWeight: "600", fontSize: 16 },
  error: { color: "#dc2626", fontSize: 14 },
});
