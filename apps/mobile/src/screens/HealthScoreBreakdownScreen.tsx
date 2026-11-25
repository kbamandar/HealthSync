import { useCallback, useState } from "react";
import { ActivityIndicator, ScrollView, StyleSheet, Text, View } from "react-native";
import { useFocusEffect } from "@react-navigation/native";
import type { NativeStackScreenProps } from "@react-navigation/native-stack";

import { dashboardApi } from "../api/dashboardApi";
import type { HealthScore } from "../api/types";
import Badge from "../components/Badge";
import HealthScoreGauge from "../components/HealthScoreGauge";
import { useFamily } from "../family/FamilyContext";
import type { HomeStackParamList } from "../navigation/HomeStack";

type Props = NativeStackScreenProps<HomeStackParamList, "HealthScoreBreakdown">;

function badgeTone(status: string): "success" | "warning" {
  return status === "Good" ? "success" : "warning";
}

export default function HealthScoreBreakdownScreen({}: Props) {
  const { selectedMemberId } = useFamily();
  const [score, setScore] = useState<HealthScore | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  const load = useCallback(async () => {
    if (!selectedMemberId) return;
    setIsLoading(true);
    try {
      setScore(await dashboardApi.getHealthScore(selectedMemberId));
    } finally {
      setIsLoading(false);
    }
  }, [selectedMemberId]);

  useFocusEffect(
    useCallback(() => {
      load();
    }, [load]),
  );

  if (isLoading || !score) {
    return (
      <View style={styles.centered}>
        <ActivityIndicator />
      </View>
    );
  }

  return (
    <ScrollView contentContainerStyle={styles.container}>
      <View style={styles.gaugeCard}>
        <HealthScoreGauge score={score.score} status={score.status} size={120} />
        <Text style={styles.summary}>{score.summary}</Text>
      </View>

      <Text style={styles.sectionLabel}>WHAT MAKES UP YOUR SCORE</Text>

      {score.factors.map((factor) => (
        <View key={factor.key} style={styles.factorRow}>
          <View style={styles.factorTopLine}>
            <Text style={styles.factorLabel}>{factor.label}</Text>
            <Badge label={factor.status} tone={badgeTone(factor.status)} />
          </View>
          <Text style={styles.factorDetail}>{factor.detail}</Text>
        </View>
      ))}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { padding: 20, gap: 10 },
  centered: { flex: 1, alignItems: "center", justifyContent: "center", padding: 24 },
  gaugeCard: {
    backgroundColor: "#fff",
    borderRadius: 12,
    padding: 20,
    alignItems: "center",
    gap: 12,
    marginBottom: 10,
  },
  summary: { fontSize: 13, color: "#666", textAlign: "center" },
  sectionLabel: { fontSize: 12, fontWeight: "600", color: "#666", marginBottom: 2 },
  factorRow: { backgroundColor: "#fff", borderRadius: 12, padding: 14, gap: 6, marginBottom: 10 },
  factorTopLine: { flexDirection: "row", justifyContent: "space-between", alignItems: "center" },
  factorLabel: { fontSize: 14, fontWeight: "600" },
  factorDetail: { fontSize: 12, color: "#666" },
});
