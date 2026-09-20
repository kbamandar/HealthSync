import { useCallback, useMemo, useState } from "react";
import { ActivityIndicator, FlatList, ScrollView, StyleSheet, Text, TouchableOpacity, View } from "react-native";
import { useFocusEffect } from "@react-navigation/native";
import type { NativeStackScreenProps } from "@react-navigation/native-stack";

import { vitalsApi } from "../../api/vitalsApi";
import type { VitalReading, VitalZone } from "../../api/types";
import { useFamily } from "../../family/FamilyContext";
import TrendChart from "../../components/TrendChart";
import { NORMAL_RANGES, RANGE_OPTIONS, VITAL_TABS, ZONE_COLORS } from "../../vitals/vitalTabs";
import type { VitalsStackParamList } from "../../navigation/VitalsStack";

const ZONE_SEVERITY: Record<VitalZone, number> = { normal: 0, borderline: 1, abnormal: 2 };

type Props = NativeStackScreenProps<VitalsStackParamList, "VitalsHome">;

export default function VitalsHomeScreen({ navigation }: Props) {
  const { selectedMember } = useFamily();
  const [tabKey, setTabKey] = useState(VITAL_TABS[0].key);
  const [selectedType, setSelectedType] = useState(VITAL_TABS[0].options[0].type);
  const [rangeDays, setRangeDays] = useState(7);
  const [readings, setReadings] = useState<VitalReading[]>([]);
  const [secondaryReadings, setSecondaryReadings] = useState<VitalReading[]>([]);
  const [isLoading, setIsLoading] = useState(true);

  const tab = VITAL_TABS.find((t) => t.key === tabKey)!;

  function handleSelectTab(key: string) {
    const nextTab = VITAL_TABS.find((t) => t.key === key)!;
    setTabKey(key);
    setSelectedType(nextTab.options[0].type);
  }

  const load = useCallback(async () => {
    if (!selectedMember) {
      setReadings([]);
      setIsLoading(false);
      return;
    }
    setIsLoading(true);
    try {
      const from = new Date(Date.now() - rangeDays * 24 * 60 * 60 * 1000).toISOString();
      const primary = await vitalsApi.list({ member_id: selectedMember.id, vital_type: selectedType, from });
      setReadings(primary);

      if (tab.isBloodPressure) {
        const other = tab.options.find((o) => o.type !== selectedType)!.type;
        setSecondaryReadings(await vitalsApi.list({ member_id: selectedMember.id, vital_type: other, from }));
      } else {
        setSecondaryReadings([]);
      }
    } finally {
      setIsLoading(false);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [selectedMember, selectedType, rangeDays]);

  useFocusEffect(
    useCallback(() => {
      load();
    }, [load]),
  );

  const latest = readings[0];
  const latestOther = secondaryReadings[0];

  const combinedZone: VitalZone | null = useMemo(() => {
    if (!latest) return null;
    if (!tab.isBloodPressure || !latestOther) return latest.zone;
    return ZONE_SEVERITY[latest.zone] >= ZONE_SEVERITY[latestOther.zone] ? latest.zone : latestOther.zone;
  }, [latest, latestOther, tab.isBloodPressure]);

  const chartPoints = [...readings]
    .reverse()
    .map((r) => ({ value: r.value, label: new Date(r.recorded_at).toLocaleDateString(undefined, { month: "short", day: "numeric" }) }));

  if (isLoading && readings.length === 0) {
    return (
      <View style={styles.centered}>
        <ActivityIndicator />
      </View>
    );
  }

  return (
    <FlatList
      data={readings}
      keyExtractor={(item) => item.id}
      contentContainerStyle={styles.container}
      ListHeaderComponent={
        <View style={styles.headerSection}>
          <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.tabRow}>
            {VITAL_TABS.map((t) => (
              <TouchableOpacity
                key={t.key}
                style={[styles.tab, tabKey === t.key && styles.tabSelected]}
                onPress={() => handleSelectTab(t.key)}
              >
                <Text style={[styles.tabText, tabKey === t.key && styles.tabTextSelected]}>{t.label}</Text>
              </TouchableOpacity>
            ))}
          </ScrollView>

          {tab.options.length > 1 && !tab.isBloodPressure && (
            <View style={styles.typeRow}>
              {tab.options.map((option) => (
                <TouchableOpacity
                  key={option.type}
                  style={[styles.typeChip, selectedType === option.type && styles.typeChipSelected]}
                  onPress={() => setSelectedType(option.type)}
                >
                  <Text style={[styles.typeChipText, selectedType === option.type && styles.typeChipTextSelected]}>
                    {option.label}
                  </Text>
                </TouchableOpacity>
              ))}
            </View>
          )}

          <View style={styles.summaryCard}>
            <Text style={styles.summaryLabel}>Latest reading</Text>
            {latest ? (
              <View style={styles.summaryValueRow}>
                <Text style={styles.summaryValue}>
                  {tab.isBloodPressure ? `${latest.value}/${latestOther?.value ?? "—"}` : latest.value}
                </Text>
                <Text style={styles.summaryUnit}>{latest.unit}</Text>
                {combinedZone && combinedZone !== "normal" && (
                  <View style={[styles.zoneBadge, { backgroundColor: ZONE_COLORS[combinedZone].bg }]}>
                    <Text style={[styles.zoneBadgeText, { color: ZONE_COLORS[combinedZone].fg }]}>
                      {combinedZone === "abnormal" ? "Abnormal" : "Borderline"}
                    </Text>
                  </View>
                )}
              </View>
            ) : (
              <Text style={styles.noReading}>No readings logged yet.</Text>
            )}
            <TouchableOpacity style={styles.logButton} onPress={() => navigation.navigate("LogVital", { tabKey })}>
              <Text style={styles.logButtonText}>Log new reading</Text>
            </TouchableOpacity>
          </View>

          <View style={styles.rangeRow}>
            {RANGE_OPTIONS.map((option) => (
              <TouchableOpacity
                key={option.label}
                style={[styles.rangeChip, rangeDays === option.days && styles.rangeChipSelected]}
                onPress={() => setRangeDays(option.days)}
              >
                <Text style={[styles.rangeChipText, rangeDays === option.days && styles.rangeChipTextSelected]}>
                  {option.label}
                </Text>
              </TouchableOpacity>
            ))}
          </View>

          <View style={styles.chartCard}>
            <TrendChart points={chartPoints} normalRange={NORMAL_RANGES[selectedType]} />
          </View>

          <Text style={styles.recentLabel}>Recent readings</Text>
        </View>
      }
      renderItem={({ item }) => (
        <View style={styles.readingRow}>
          <Text style={styles.readingValue}>
            {item.value} {item.unit}
            {item.reading_context ? `  ·  ${item.reading_context}` : ""}
          </Text>
          <Text style={styles.readingDate}>{new Date(item.recorded_at).toLocaleDateString()}</Text>
        </View>
      )}
      ListEmptyComponent={
        <View style={styles.centered}>
          <Text style={styles.noReading}>No readings in this range.</Text>
        </View>
      }
    />
  );
}

const styles = StyleSheet.create({
  container: { padding: 20, gap: 10, flexGrow: 1 },
  centered: { flex: 1, alignItems: "center", justifyContent: "center", padding: 24 },
  headerSection: { gap: 10 },
  tabRow: { gap: 8, paddingBottom: 4 },
  tab: { borderRadius: 20, paddingVertical: 6, paddingHorizontal: 14, backgroundColor: "#fff" },
  tabSelected: { backgroundColor: "#0f766e" },
  tabText: { fontSize: 13, color: "#333", fontWeight: "600" },
  tabTextSelected: { color: "#fff" },
  typeRow: { flexDirection: "row", flexWrap: "wrap", gap: 8 },
  typeChip: { borderWidth: 1, borderColor: "#ddd", borderRadius: 20, paddingVertical: 6, paddingHorizontal: 14 },
  typeChipSelected: { backgroundColor: "#0f766e", borderColor: "#0f766e" },
  typeChipText: { fontSize: 12, color: "#333" },
  typeChipTextSelected: { color: "#fff" },
  summaryCard: { backgroundColor: "#fff", borderRadius: 12, padding: 14, gap: 8 },
  summaryLabel: { fontSize: 11, color: "#888", textTransform: "uppercase" },
  summaryValueRow: { flexDirection: "row", alignItems: "center", gap: 8 },
  summaryValue: { fontSize: 24, fontWeight: "700" },
  summaryUnit: { fontSize: 13, color: "#666" },
  zoneBadge: { borderRadius: 12, paddingVertical: 3, paddingHorizontal: 10 },
  zoneBadgeText: { fontSize: 12, fontWeight: "600" },
  noReading: { color: "#888", fontSize: 13 },
  logButton: { backgroundColor: "#0f766e", borderRadius: 8, paddingVertical: 10, alignItems: "center" },
  logButtonText: { color: "#fff", fontWeight: "600", fontSize: 15 },
  rangeRow: { flexDirection: "row", gap: 8 },
  rangeChip: { borderRadius: 20, paddingVertical: 5, paddingHorizontal: 12, backgroundColor: "#fff" },
  rangeChipSelected: { backgroundColor: "#0f766e" },
  rangeChipText: { fontSize: 11, color: "#666", fontWeight: "600" },
  rangeChipTextSelected: { color: "#fff" },
  chartCard: { backgroundColor: "#fff", borderRadius: 12, padding: 12, alignItems: "center" },
  recentLabel: { fontSize: 15, fontWeight: "700", marginTop: 4 },
  readingRow: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    backgroundColor: "#fff",
    borderRadius: 10,
    paddingVertical: 10,
    paddingHorizontal: 14,
    marginBottom: 8,
  },
  readingValue: { fontSize: 13, fontWeight: "600" },
  readingDate: { fontSize: 11, color: "#888" },
});
