import { useEffect, useState } from "react";
import { ActivityIndicator, ScrollView, StyleSheet, Text, TextInput, TouchableOpacity, View } from "react-native";
import type { NativeStackScreenProps } from "@react-navigation/native-stack";

import { recordsApi } from "../../api/recordsApi";
import type { RecordCategory } from "../../api/types";
import { CATEGORIES, CATEGORY_LABELS } from "../../records/categories";
import type { RecordsStackParamList } from "../../navigation/RecordsStack";

type Props = NativeStackScreenProps<RecordsStackParamList, "EditRecord">;

export default function EditRecordScreen({ route, navigation }: Props) {
  const { id } = route.params;
  const [isLoading, setIsLoading] = useState(true);
  const [isSaving, setIsSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [category, setCategory] = useState<RecordCategory>("other");
  const [title, setTitle] = useState("");
  const [recordDate, setRecordDate] = useState("");
  const [doctorName, setDoctorName] = useState("");
  const [hospitalClinic, setHospitalClinic] = useState("");
  const [notes, setNotes] = useState("");

  useEffect(() => {
    (async () => {
      const record = await recordsApi.get(id);
      setCategory(record.category);
      setTitle(record.title ?? "");
      setRecordDate(record.record_date ?? "");
      setDoctorName(record.doctor_name ?? "");
      setHospitalClinic(record.hospital_clinic ?? "");
      setNotes(record.notes ?? "");
      setIsLoading(false);
    })();
  }, [id]);

  async function handleSave() {
    setError(null);
    setIsSaving(true);
    try {
      await recordsApi.update(id, {
        category,
        title: title.trim() || null,
        record_date: recordDate.trim() || null,
        doctor_name: doctorName.trim() || null,
        hospital_clinic: hospitalClinic.trim() || null,
        notes: notes.trim() || null,
      });
      navigation.goBack();
    } catch {
      setError("Couldn't save your changes. Try again.");
    } finally {
      setIsSaving(false);
    }
  }

  if (isLoading) {
    return (
      <View style={styles.centered}>
        <ActivityIndicator />
      </View>
    );
  }

  return (
    <ScrollView contentContainerStyle={styles.container}>
      <Text style={styles.label}>Category</Text>
      <View style={styles.chipRow}>
        {CATEGORIES.map((option) => (
          <TouchableOpacity
            key={option}
            style={[styles.chip, category === option && styles.chipSelected]}
            onPress={() => setCategory(option)}
          >
            <Text style={[styles.chipText, category === option && styles.chipTextSelected]}>
              {CATEGORY_LABELS[option]}
            </Text>
          </TouchableOpacity>
        ))}
      </View>

      <TextInput style={styles.input} placeholder="Title" value={title} onChangeText={setTitle} />
      <TextInput
        style={styles.input}
        placeholder="Record date (YYYY-MM-DD)"
        value={recordDate}
        onChangeText={setRecordDate}
      />
      <TextInput style={styles.input} placeholder="Doctor name" value={doctorName} onChangeText={setDoctorName} />
      <TextInput
        style={styles.input}
        placeholder="Hospital / clinic"
        value={hospitalClinic}
        onChangeText={setHospitalClinic}
      />
      <TextInput
        style={[styles.input, styles.notesInput]}
        placeholder="Notes"
        value={notes}
        onChangeText={setNotes}
        multiline
      />

      {error && <Text style={styles.error}>{error}</Text>}

      <TouchableOpacity style={styles.button} disabled={isSaving} onPress={handleSave}>
        {isSaving ? <ActivityIndicator color="#fff" /> : <Text style={styles.buttonText}>Save changes</Text>}
      </TouchableOpacity>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { padding: 20, gap: 12 },
  centered: { flex: 1, alignItems: "center", justifyContent: "center" },
  label: { fontSize: 13, color: "#666" },
  chipRow: { flexDirection: "row", flexWrap: "wrap", gap: 8 },
  chip: { borderWidth: 1, borderColor: "#ddd", borderRadius: 20, paddingVertical: 8, paddingHorizontal: 16 },
  chipSelected: { backgroundColor: "#0f766e", borderColor: "#0f766e" },
  chipText: { color: "#333" },
  chipTextSelected: { color: "#fff" },
  input: { borderWidth: 1, borderColor: "#ddd", borderRadius: 8, padding: 12, fontSize: 16 },
  notesInput: { minHeight: 80, textAlignVertical: "top" },
  button: { backgroundColor: "#0f766e", borderRadius: 8, padding: 14, alignItems: "center", marginTop: 8 },
  buttonText: { color: "#fff", fontWeight: "600", fontSize: 16 },
  error: { color: "#dc2626", fontSize: 14 },
});
