import { useCallback, useState } from "react";
import { ActivityIndicator, Alert, ScrollView, StyleSheet, Text, TouchableOpacity, View } from "react-native";
import { useFocusEffect } from "@react-navigation/native";
import type { NativeStackScreenProps } from "@react-navigation/native-stack";

import { recordsApi } from "../../api/recordsApi";
import type { HealthRecord } from "../../api/types";
import Badge from "../../components/Badge";
import type { RecordsStackParamList } from "../../navigation/RecordsStack";

type Props = NativeStackScreenProps<RecordsStackParamList, "PrescriptionReader">;

export default function PrescriptionReaderScreen({ route, navigation }: Props) {
  const { id } = route.params;
  const [record, setRecord] = useState<HealthRecord | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isCreating, setIsCreating] = useState(false);
  const [remindersCreatedCount, setRemindersCreatedCount] = useState<number | null>(null);

  const load = useCallback(async () => {
    setIsLoading(true);
    try {
      setRecord(await recordsApi.get(id));
    } finally {
      setIsLoading(false);
    }
  }, [id]);

  useFocusEffect(
    useCallback(() => {
      load();
    }, [load]),
  );

  async function handleCreateReminders() {
    setIsCreating(true);
    try {
      const updated = await recordsApi.applyOcr(id);
      setRemindersCreatedCount(updated.reminders_created?.length ?? 0);
    } catch {
      Alert.alert("Couldn't create reminders", "Try again in a moment.");
    } finally {
      setIsCreating(false);
    }
  }

  if (isLoading || !record) {
    return (
      <View style={styles.centered}>
        <ActivityIndicator />
      </View>
    );
  }

  const file = record.files?.find((f) => f.ocr_extracted);
  const rawFields = file?.ocr_data?.fields;
  const fields = rawFields && "medicines" in rawFields ? rawFields : null;

  if (!file || !fields || (!fields.doctor_name && !fields.prescription_date && fields.medicines.length === 0)) {
    return (
      <View style={styles.centered}>
        <Text style={styles.emptyText}>No data could be extracted from this prescription.</Text>
      </View>
    );
  }

  return (
    <ScrollView contentContainerStyle={styles.container}>
      <View style={styles.headerRow}>
        <Text style={styles.title}>Prescription scanned</Text>
        <Badge label="Ready" tone="success" />
      </View>

      {fields.doctor_name && (
        <View style={styles.card}>
          <Text style={styles.label}>Prescribed by</Text>
          <Text style={styles.value}>{fields.doctor_name}</Text>
        </View>
      )}

      {fields.prescription_date && (
        <View style={styles.card}>
          <Text style={styles.label}>Date</Text>
          <Text style={styles.value}>{fields.prescription_date}</Text>
        </View>
      )}

      {fields.medicines.length > 0 && (
        <>
          <Text style={styles.sectionLabel}>Medicines found</Text>
          {fields.medicines.map((medicine, i) => (
            <View key={i} style={styles.medicineCard}>
              <Text style={styles.medicineName}>{medicine.name}</Text>
              <Text style={styles.medicineDetail}>
                {medicine.frequency}
                {medicine.duration_days ? ` · ${medicine.duration_days} days` : ""}
              </Text>
            </View>
          ))}
        </>
      )}

      {file.ocr_data?.raw_text && (
        <>
          <Text style={styles.sectionLabel}>Raw extracted text</Text>
          <View style={styles.rawTextCard}>
            <Text style={styles.rawText}>{file.ocr_data.raw_text}</Text>
          </View>
        </>
      )}

      {remindersCreatedCount !== null ? (
        <View style={styles.doneCard}>
          <Text style={styles.doneText}>
            {remindersCreatedCount === 0
              ? "Reminders were already created for this prescription."
              : `${remindersCreatedCount} medication reminder(s) created.`}
          </Text>
          <TouchableOpacity onPress={() => navigation.goBack()}>
            <Text style={styles.doneLink}>Back to record</Text>
          </TouchableOpacity>
        </View>
      ) : (
        <TouchableOpacity style={styles.createButton} disabled={isCreating} onPress={handleCreateReminders}>
          {isCreating ? (
            <ActivityIndicator color="#fff" />
          ) : (
            <Text style={styles.createButtonText}>Create reminders</Text>
          )}
        </TouchableOpacity>
      )}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { padding: 20, gap: 10 },
  centered: { flex: 1, alignItems: "center", justifyContent: "center", padding: 24 },
  emptyText: { color: "#888", textAlign: "center" },
  headerRow: { flexDirection: "row", justifyContent: "space-between", alignItems: "center" },
  title: { fontSize: 19, fontWeight: "700" },
  card: { backgroundColor: "#fff", borderRadius: 10, padding: 12, gap: 4 },
  label: { fontSize: 11, color: "#888", textTransform: "uppercase" },
  value: { fontSize: 15, fontWeight: "700" },
  sectionLabel: { fontSize: 14, fontWeight: "700", marginTop: 6 },
  medicineCard: {
    backgroundColor: "#fff",
    borderRadius: 10,
    borderWidth: 1,
    borderColor: "#e2e8f0",
    padding: 12,
    gap: 3,
  },
  medicineName: { fontSize: 14, fontWeight: "600" },
  medicineDetail: { fontSize: 12, color: "#666" },
  rawTextCard: { backgroundColor: "#fff", borderRadius: 10, padding: 12 },
  rawText: { fontSize: 12, color: "#666", lineHeight: 18 },
  createButton: { backgroundColor: "#0f766e", borderRadius: 8, padding: 14, alignItems: "center", marginTop: 8 },
  createButtonText: { color: "#fff", fontWeight: "600", fontSize: 16 },
  doneCard: { backgroundColor: "#f0fdfa", borderRadius: 10, padding: 14, gap: 8, marginTop: 8 },
  doneText: { fontSize: 14, color: "#0f172a" },
  doneLink: { fontSize: 13, fontWeight: "600", color: "#0f766e" },
});
