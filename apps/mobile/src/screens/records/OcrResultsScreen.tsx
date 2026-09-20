import { useCallback, useState } from "react";
import { ActivityIndicator, Alert, ScrollView, StyleSheet, Text, TouchableOpacity, View } from "react-native";
import { useFocusEffect } from "@react-navigation/native";
import type { NativeStackScreenProps } from "@react-navigation/native-stack";

import { recordsApi } from "../../api/recordsApi";
import type { HealthRecord } from "../../api/types";
import Badge from "../../components/Badge";
import type { RecordsStackParamList } from "../../navigation/RecordsStack";

type Props = NativeStackScreenProps<RecordsStackParamList, "OcrResults">;

export default function OcrResultsScreen({ route, navigation }: Props) {
  const { id } = route.params;
  const [record, setRecord] = useState<HealthRecord | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isApplying, setIsApplying] = useState(false);

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

  async function handleApply() {
    setIsApplying(true);
    try {
      await recordsApi.applyOcr(id);
      navigation.goBack();
    } catch {
      Alert.alert("Couldn't apply extracted data", "Try again in a moment.");
    } finally {
      setIsApplying(false);
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
  const fields = file?.ocr_data?.fields;

  if (!file || !fields || (!fields.record_date && !fields.lab_name && fields.test_values.length === 0)) {
    return (
      <View style={styles.centered}>
        <Text style={styles.emptyText}>No data could be extracted from this document.</Text>
      </View>
    );
  }

  return (
    <ScrollView contentContainerStyle={styles.container}>
      <View style={styles.headerRow}>
        <Text style={styles.title}>Extracted from document</Text>
        <Badge label="Ready" tone="success" />
      </View>

      {fields.record_date && (
        <View style={styles.card}>
          <Text style={styles.label}>Record date</Text>
          <Text style={styles.value}>{fields.record_date}</Text>
        </View>
      )}

      {fields.lab_name && (
        <View style={styles.card}>
          <Text style={styles.label}>Lab / hospital</Text>
          <Text style={styles.value}>{fields.lab_name}</Text>
        </View>
      )}

      {fields.test_values.length > 0 && (
        <>
          <Text style={styles.sectionLabel}>Test values found</Text>
          {fields.test_values.map((test, i) => (
            <View key={i} style={styles.testRow}>
              <Text style={styles.testName}>{test.name}</Text>
              <Text style={styles.testValue}>
                {test.value}
                {test.unit ? ` ${test.unit}` : ""}
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

      <TouchableOpacity style={styles.applyButton} disabled={isApplying} onPress={handleApply}>
        {isApplying ? <ActivityIndicator color="#fff" /> : <Text style={styles.applyButtonText}>Apply to record</Text>}
      </TouchableOpacity>
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
  testRow: {
    flexDirection: "row",
    justifyContent: "space-between",
    backgroundColor: "#fff",
    borderRadius: 10,
    padding: 10,
  },
  testName: { fontSize: 13 },
  testValue: { fontSize: 13, fontWeight: "600" },
  rawTextCard: { backgroundColor: "#fff", borderRadius: 10, padding: 12 },
  rawText: { fontSize: 12, color: "#666", lineHeight: 18 },
  applyButton: { backgroundColor: "#0f766e", borderRadius: 8, padding: 14, alignItems: "center", marginTop: 8 },
  applyButtonText: { color: "#fff", fontWeight: "600", fontSize: 16 },
});
