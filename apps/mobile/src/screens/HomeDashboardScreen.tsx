import { useCallback, useState } from "react";
import { ActivityIndicator, FlatList, StyleSheet, Text, View } from "react-native";
import { useFocusEffect } from "@react-navigation/native";

import { dashboardApi } from "../api/dashboardApi";
import type { Dashboard } from "../api/types";
import { useAuth } from "../auth/AuthContext";
import Avatar from "../components/Avatar";
import Badge from "../components/Badge";
import { CATEGORY_LABELS } from "../records/categories";

function greeting(): string {
  const hour = new Date().getHours();
  if (hour < 12) return "Good morning";
  if (hour < 17) return "Good afternoon";
  return "Good evening";
}

export default function HomeDashboardScreen() {
  const { user } = useAuth();
  const [dashboard, setDashboard] = useState<Dashboard | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  const load = useCallback(async () => {
    setIsLoading(true);
    try {
      setDashboard(await dashboardApi.get());
    } finally {
      setIsLoading(false);
    }
  }, []);

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
  glanceCard: { backgroundColor: "#fff", borderRadius: 12, padding: 14, gap: 10 },
  glanceTitle: { fontSize: 15, fontWeight: "700" },
  statRow: { flexDirection: "row", gap: 10 },
  statTile: { flex: 1, backgroundColor: "#f0fdfa", borderRadius: 10, padding: 10, gap: 4 },
  statTileWarning: { backgroundColor: "#fef3c7" },
  statValue: { fontSize: 18, fontWeight: "700", color: "#0f766e" },
  statValueWarning: { color: "#92400e" },
  statLabel: { fontSize: 11, color: "#666" },
  sectionLabel: { fontSize: 16, fontWeight: "700" },
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
