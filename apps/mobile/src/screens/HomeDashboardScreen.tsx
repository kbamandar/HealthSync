import { useCallback, useState } from "react";
import { ActivityIndicator, FlatList, StyleSheet, Text, TouchableOpacity, View } from "react-native";
import { useFocusEffect } from "@react-navigation/native";
import type { NativeStackScreenProps } from "@react-navigation/native-stack";

import { dashboardApi } from "../api/dashboardApi";
import type { Dashboard, HealthInsight, HealthScore } from "../api/types";
import { useAuth } from "../auth/AuthContext";
import Avatar from "../components/Avatar";
import Badge from "../components/Badge";
import HealthScoreGauge from "../components/HealthScoreGauge";
import { useFamily } from "../family/FamilyContext";
import { CATEGORY_LABELS } from "../records/categories";
import { REMINDER_TYPE_LABELS } from "../reminders/reminderTypes";
import type { HomeStackParamList } from "../navigation/HomeStack";

function greeting(): string {
  const hour = new Date().getHours();
  if (hour < 12) return "Good morning";
  if (hour < 17) return "Good afternoon";
  return "Good evening";
}

type Props = NativeStackScreenProps<HomeStackParamList, "HomeDashboard">;

export default function HomeDashboardScreen({ navigation }: Props) {
  const { user } = useAuth();
  const { selectedMemberId } = useFamily();
  const [dashboard, setDashboard] = useState<Dashboard | null>(null);
  const [healthScore, setHealthScore] = useState<HealthScore | null>(null);
  const [insights, setInsights] = useState<HealthInsight[]>([]);
  const [dismissedInsightIds, setDismissedInsightIds] = useState<string[]>([]);
  const [isLoading, setIsLoading] = useState(true);

  const load = useCallback(async () => {
    setIsLoading(true);
    try {
      const [dashboardResult, healthScoreResult, insightsResult] = await Promise.all([
        dashboardApi.get(),
        selectedMemberId ? dashboardApi.getHealthScore(selectedMemberId) : Promise.resolve(null),
        selectedMemberId ? dashboardApi.getHealthInsights(selectedMemberId) : Promise.resolve(null),
      ]);
      setDashboard(dashboardResult);
      setHealthScore(healthScoreResult);
      setInsights(insightsResult?.insights ?? []);
      setDismissedInsightIds([]);
    } finally {
      setIsLoading(false);
    }
  }, [selectedMemberId]);

  useFocusEffect(
    useCallback(() => {
      load();
    }, [load]),
  );

  if (isLoading || !dashboard) {
    return (
      <View style={styles.centered}>
        <ActivityIndicator />
      </View>
    );
  }

  const topInsight = insights.find((insight) => !dismissedInsightIds.includes(insight.id)) ?? null;

  return (
    <FlatList
      data={dashboard.recent_records}
      keyExtractor={(item) => item.id}
      contentContainerStyle={styles.container}
      ListHeaderComponent={
        <View style={styles.headerSection}>
          <Text style={styles.greeting}>
            {greeting()}
            {user?.name ? `, ${user.name.split(" ")[0]}` : ""}
          </Text>

          <View style={styles.quickAccessRow}>
            <TouchableOpacity style={styles.quickAccessButton} onPress={() => navigation.navigate("Timeline")}>
              <Text style={styles.quickAccessText}>Timeline</Text>
            </TouchableOpacity>
            <TouchableOpacity style={styles.quickAccessButton} onPress={() => navigation.navigate("Search")}>
              <Text style={styles.quickAccessText}>Search</Text>
            </TouchableOpacity>
            <TouchableOpacity style={styles.quickAccessButton} onPress={() => navigation.navigate("ManageSharedLinks")}>
              <Text style={styles.quickAccessText}>Shared links</Text>
            </TouchableOpacity>
          </View>

          {healthScore && (
            <View style={styles.scoreWidget}>
              <Text style={styles.scoreTitle}>Your Health Score</Text>
              <View style={styles.scoreRow}>
                <HealthScoreGauge score={healthScore.score} status={healthScore.status} />
                <View style={styles.scoreTextCol}>
                  <Text style={styles.scoreSummary}>{healthScore.summary}</Text>
                  <TouchableOpacity onPress={() => navigation.navigate("HealthScoreBreakdown")}>
                    <Text style={styles.scoreLink}>View breakdown ›</Text>
                  </TouchableOpacity>
                </View>
              </View>
            </View>
          )}

          {topInsight && (
            <View style={styles.insightsCard}>
              <View style={styles.insightsTopLine}>
                <Text style={styles.insightsIcon}>!</Text>
                <Text style={styles.insightsText}>{topInsight.message}</Text>
              </View>
              <TouchableOpacity onPress={() => setDismissedInsightIds((ids) => [...ids, topInsight.id])}>
                <Text style={styles.insightsDismiss}>I understand</Text>
              </TouchableOpacity>
            </View>
          )}

          <View style={styles.glanceCard}>
            <Text style={styles.glanceTitle}>Health at a glance</Text>
            <View style={styles.statRow}>
              <View style={styles.statTile}>
                <Text style={styles.statValue}>{dashboard.stats.records_this_month}</Text>
                <Text style={styles.statLabel}>Records this month</Text>
              </View>
              <View style={[styles.statTile, dashboard.stats.vitals_needing_attention > 0 && styles.statTileWarning]}>
                <Text
                  style={[
                    styles.statValue,
                    dashboard.stats.vitals_needing_attention > 0 && styles.statValueWarning,
                  ]}
                >
                  {dashboard.stats.vitals_needing_attention}
                </Text>
                <Text style={styles.statLabel}>Vitals needing attention</Text>
              </View>
              <View style={styles.statTile}>
                <Text style={styles.statValue}>{dashboard.stats.profile_complete_percent}%</Text>
                <Text style={styles.statLabel}>Profile complete</Text>
              </View>
            </View>
          </View>

          <View style={styles.sectionHeaderRow}>
            <Text style={styles.sectionLabel}>Upcoming reminders</Text>
            <TouchableOpacity onPress={() => navigation.navigate("Reminders")}>
              <Text style={styles.seeAll}>See all</Text>
            </TouchableOpacity>
          </View>
          {dashboard.upcoming_reminders.length === 0 ? (
            <Text style={styles.emptyText}>Nothing coming up.</Text>
          ) : (
            dashboard.upcoming_reminders.map((reminder) => (
              <View key={reminder.id} style={styles.reminderRow}>
                <View style={styles.recordTextCol}>
                  <Text style={styles.recordTitle}>{reminder.title}</Text>
                  <Text style={styles.recordCategory}>{REMINDER_TYPE_LABELS[reminder.reminder_type]}</Text>
                </View>
                <Text style={styles.recordDate}>
                  {new Date(reminder.due_at).toLocaleString(undefined, {
                    weekday: "short",
                    hour: "numeric",
                    minute: "2-digit",
                  })}
                </Text>
              </View>
            ))
          )}

          <Text style={styles.sectionLabel}>Recent records</Text>
        </View>
      }
      renderItem={({ item }) => (
        <View style={styles.recordRow}>
          <View style={styles.recordTextCol}>
            <Text style={styles.recordTitle}>{item.title || "Untitled record"}</Text>
            <Text style={styles.recordCategory}>{CATEGORY_LABELS[item.category]}</Text>
          </View>
          {item.record_date && <Text style={styles.recordDate}>{item.record_date}</Text>}
        </View>
      )}
      ListEmptyComponent={
        <View style={styles.centered}>
          <Text style={styles.emptyText}>No records yet.</Text>
        </View>
      }
      ListFooterComponent={
        <View style={styles.familySection}>
          <Text style={styles.sectionLabel}>Family</Text>
          <View style={styles.familyRow}>
            {dashboard.family_summary.map((member) => (
              <View key={member.member_id} style={styles.memberCard}>
                <Avatar name={member.display_name} size={44} />
                <Text style={styles.memberName} numberOfLines={1}>
                  {member.display_name}
                </Text>
                {member.pending_count > 0 && <Badge label={`${member.pending_count} pending`} tone="warning" />}
              </View>
            ))}
          </View>
        </View>
      }
    />
  );
}

const styles = StyleSheet.create({
  container: { padding: 20, gap: 10, flexGrow: 1 },
  centered: { flex: 1, alignItems: "center", justifyContent: "center", padding: 24 },
  emptyText: { color: "#888" },
  headerSection: { gap: 14 },
  greeting: { fontSize: 22, fontWeight: "700" },
  quickAccessRow: { flexDirection: "row", gap: 10 },
  quickAccessButton: {
    flex: 1,
    backgroundColor: "#fff",
    borderRadius: 10,
    paddingVertical: 10,
    alignItems: "center",
  },
  quickAccessText: { fontSize: 13, fontWeight: "600", color: "#0f766e" },
  scoreWidget: { backgroundColor: "#fff", borderRadius: 12, padding: 14, gap: 12 },
  scoreTitle: { fontSize: 13, fontWeight: "600", color: "#666" },
  scoreRow: { flexDirection: "row", alignItems: "center", gap: 16 },
  scoreTextCol: { flex: 1, gap: 8 },
  scoreSummary: { fontSize: 13, color: "#666" },
  scoreLink: { fontSize: 13, fontWeight: "600", color: "#0f766e" },
  insightsCard: { backgroundColor: "#fef3c7", borderRadius: 12, padding: 14, gap: 10 },
  insightsTopLine: { flexDirection: "row", gap: 10 },
  insightsIcon: { fontSize: 15, fontWeight: "700", color: "#92400e" },
  insightsText: { flex: 1, fontSize: 13, color: "#0f172a", lineHeight: 18 },
  insightsDismiss: { fontSize: 13, fontWeight: "600", color: "#92400e" },
  glanceCard: { backgroundColor: "#fff", borderRadius: 12, padding: 14, gap: 10 },
  glanceTitle: { fontSize: 15, fontWeight: "700" },
  statRow: { flexDirection: "row", gap: 10 },
  statTile: { flex: 1, backgroundColor: "#f0fdfa", borderRadius: 10, padding: 10, gap: 4 },
  statTileWarning: { backgroundColor: "#fef3c7" },
  statValue: { fontSize: 18, fontWeight: "700", color: "#0f766e" },
  statValueWarning: { color: "#92400e" },
  statLabel: { fontSize: 11, color: "#666" },
  sectionLabel: { fontSize: 16, fontWeight: "700" },
  sectionHeaderRow: { flexDirection: "row", justifyContent: "space-between", alignItems: "center" },
  seeAll: { fontSize: 13, color: "#0f766e", fontWeight: "600" },
  reminderRow: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    backgroundColor: "#fff",
    borderRadius: 10,
    padding: 14,
    marginBottom: 10,
  },
  recordRow: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    backgroundColor: "#fff",
    borderRadius: 10,
    padding: 14,
    marginBottom: 10,
  },
  recordTextCol: { flex: 1, gap: 2 },
  recordTitle: { fontSize: 14, fontWeight: "600" },
  recordCategory: { fontSize: 12, color: "#666" },
  recordDate: { fontSize: 12, color: "#888" },
  familySection: { gap: 10, marginTop: 6 },
  familyRow: { flexDirection: "row", gap: 10 },
  memberCard: { flex: 1, backgroundColor: "#fff", borderRadius: 12, padding: 12, alignItems: "center", gap: 6 },
  memberName: { fontSize: 12, fontWeight: "600" },
});
