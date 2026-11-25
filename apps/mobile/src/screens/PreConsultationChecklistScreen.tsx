import { useCallback, useState } from "react";
import { ActivityIndicator, ScrollView, StyleSheet, Text, TouchableOpacity, View } from "react-native";
import { useFocusEffect } from "@react-navigation/native";
import type { NativeStackScreenProps } from "@react-navigation/native-stack";

import { dashboardApi } from "../api/dashboardApi";
import type { Dashboard, HealthInsight } from "../api/types";
import { VITAL_TABS, ZONE_COLORS } from "../vitals/vitalTabs";
import type { HomeStackParamList } from "../navigation/HomeStack";

type Props = NativeStackScreenProps<HomeStackParamList, "PreConsultationChecklist">;

const VITAL_LABELS = Object.fromEntries(
  VITAL_TABS.flatMap((tab) => tab.options.map((option) => [option.type, option.label])),
);

function Checkbox({ checked }: { checked: boolean }) {
  return (
    <View style={[styles.checkbox, checked && styles.checkboxChecked]}>
      {checked && <Text style={styles.checkmark}>✓</Text>}
    </View>
  );
}

export default function PreConsultationChecklistScreen({ route, navigation }: Props) {
  const { memberId, title, dueAt } = route.params;
  const [dashboard, setDashboard] = useState<Dashboard | null>(null);
  const [insights, setInsights] = useState<HealthInsight[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [checked, setChecked] = useState<Record<string, boolean>>({});

  const load = useCallback(async () => {
    setIsLoading(true);
    try {
      const [dashboardResult, insightsResult] = await Promise.all([
        dashboardApi.get(memberId),
        dashboardApi.getHealthInsights(memberId),
      ]);
      setDashboard(dashboardResult);
      setInsights(insightsResult.insights);
    } finally {
      setIsLoading(false);
    }
  }, [memberId]);

  useFocusEffect(
    useCallback(() => {
      load();
    }, [load]),
  );

  function toggle(key: string) {
    setChecked((current) => ({ ...current, [key]: !current[key] }));
  }

  if (isLoading || !dashboard) {
    return (
      <View style={styles.centered}>
        <ActivityIndicator />
      </View>
    );
  }

  const recordsToBring = dashboard.recent_records.slice(0, 3);

  return (
    <ScrollView contentContainerStyle={styles.container}>
      <Text style={styles.title}>Prepare for your visit</Text>
      <Text style={styles.subtitle}>
        {title} ·{" "}
        {new Date(dueAt).toLocaleString(undefined, {
          day: "numeric",
          month: "short",
          hour: "numeric",
          minute: "2-digit",
        })}
      </Text>

      {recordsToBring.length > 0 && (
        <>
          <Text style={styles.sectionLabel}>BRING THESE RECORDS</Text>
          {recordsToBring.map((record) => {
            const key = `record:${record.id}`;
            return (
              <TouchableOpacity key={key} style={styles.checkRow} onPress={() => toggle(key)}>
                <Checkbox checked={!!checked[key]} />
                <Text style={styles.checkRowText}>
                  {record.title || "Untitled record"}
                  {record.record_date ? ` (${record.record_date})` : ""}
                </Text>
              </TouchableOpacity>
            );
          })}
        </>
      )}

      {insights.length > 0 && (
        <>
          <Text style={styles.sectionLabel}>QUESTIONS TO ASK</Text>
          {insights.map((insight) => {
            const key = `insight:${insight.id}`;
            return (
              <TouchableOpacity key={key} style={styles.checkRow} onPress={() => toggle(key)}>
                <Checkbox checked={!!checked[key]} />
                <Text style={styles.checkRowText}>Ask about: {insight.title}</Text>
              </TouchableOpacity>
            );
          })}
        </>
      )}

      {dashboard.latest_vitals.length > 0 && (
        <>
          <Text style={styles.sectionLabel}>RECENT VITALS TO MENTION</Text>
          {dashboard.latest_vitals.map((vital) => (
            <View key={vital.vital_type} style={styles.vitalCard}>
              <View style={styles.vitalTopLine}>
                <Text style={styles.vitalName}>{VITAL_LABELS[vital.vital_type] ?? vital.vital_type}</Text>
                <View style={[styles.zonePill, { backgroundColor: ZONE_COLORS[vital.zone].bg }]}>
                  <Text style={[styles.zonePillText, { color: ZONE_COLORS[vital.zone].fg }]}>{vital.zone}</Text>
                </View>
              </View>
              <Text style={styles.vitalValue}>
                {vital.value} {vital.unit}
              </Text>
            </View>
          ))}
        </>
      )}

      <TouchableOpacity style={styles.doneButton} onPress={() => navigation.goBack()}>
        <Text style={styles.doneButtonText}>Mark as prepared</Text>
      </TouchableOpacity>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { padding: 20, gap: 10 },
  centered: { flex: 1, alignItems: "center", justifyContent: "center", padding: 24 },
  title: { fontSize: 20, fontWeight: "700" },
  subtitle: { fontSize: 13, color: "#666", marginBottom: 8 },
  sectionLabel: { fontSize: 12, fontWeight: "600", color: "#666", marginTop: 6 },
  checkRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    backgroundColor: "#fff",
    borderRadius: 10,
    padding: 14,
  },
  checkRowText: { flex: 1, fontSize: 14, fontWeight: "600" },
  checkbox: {
    width: 20,
    height: 20,
    borderRadius: 5,
    borderWidth: 1.5,
    borderColor: "#cbd5e1",
  },
  checkboxChecked: { backgroundColor: "#0f766e", borderColor: "#0f766e", alignItems: "center", justifyContent: "center" },
  checkmark: { color: "#fff", fontSize: 13, fontWeight: "700" },
  vitalCard: { backgroundColor: "#fff", borderRadius: 10, padding: 12, gap: 4 },
  vitalTopLine: { flexDirection: "row", justifyContent: "space-between", alignItems: "center" },
  vitalName: { fontSize: 14, fontWeight: "600" },
  vitalValue: { fontSize: 13, color: "#666" },
  zonePill: { borderRadius: 20, paddingVertical: 3, paddingHorizontal: 10 },
  zonePillText: { fontSize: 11, fontWeight: "600" },
  doneButton: { backgroundColor: "#0f766e", borderRadius: 8, padding: 14, alignItems: "center", marginTop: 10 },
  doneButtonText: { color: "#fff", fontWeight: "600", fontSize: 16 },
});
