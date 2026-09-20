import { useCallback, useState } from "react";
import { ActivityIndicator, FlatList, ScrollView, StyleSheet, Text, TouchableOpacity, View } from "react-native";
import { useFocusEffect } from "@react-navigation/native";

import { timelineApi } from "../api/timelineApi";
import type { TimelineEntry } from "../api/types";
import { useFamily } from "../family/FamilyContext";
import { CATEGORY_LABELS } from "../records/categories";

function monthLabelFor(dateStr: string): string {
  return new Date(dateStr).toLocaleDateString(undefined, { month: "long", year: "numeric" });
}

function subTypeLabel(entry: TimelineEntry): string {
  if (entry.kind === "record") {
    return CATEGORY_LABELS[entry.sub_type as keyof typeof CATEGORY_LABELS] ?? entry.sub_type;
  }
  return entry.sub_type.replace(/_/g, " ");
}

function entryTitle(entry: TimelineEntry): string {
  if (entry.kind === "record") return entry.title || "Untitled record";
  return `${entry.value}${entry.unit ? ` ${entry.unit}` : ""}`;
}

export default function TimelineScreen() {
  const { members } = useFamily();
  const [memberId, setMemberId] = useState<string | null>(null);
  const [entries, setEntries] = useState<TimelineEntry[]>([]);
  const [isLoading, setIsLoading] = useState(true);

  const load = useCallback(async () => {
    setIsLoading(true);
    try {
      setEntries(await timelineApi.list(memberId ? { member_id: memberId } : {}));
    } finally {
      setIsLoading(false);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [memberId]);

  useFocusEffect(
    useCallback(() => {
      load();
    }, [load]),
  );

  if (isLoading && entries.length === 0) {
    return (
      <View style={styles.centered}>
        <ActivityIndicator />
      </View>
    );
  }

  let lastMonth = "";

  return (
    <View style={styles.container}>
      <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.memberRow}>
        <TouchableOpacity
          style={[styles.chip, memberId === null && styles.chipSelected]}
          onPress={() => setMemberId(null)}
        >
          <Text style={[styles.chipText, memberId === null && styles.chipTextSelected]}>All</Text>
        </TouchableOpacity>
        {members.map((member) => (
          <TouchableOpacity
            key={member.id}
            style={[styles.chip, memberId === member.id && styles.chipSelected]}
            onPress={() => setMemberId(member.id)}
          >
            <Text style={[styles.chipText, memberId === member.id && styles.chipTextSelected]}>
              {member.display_name}
            </Text>
          </TouchableOpacity>
        ))}
      </ScrollView>

      <FlatList
        data={entries}
        keyExtractor={(item) => `${item.kind}-${item.id}`}
        contentContainerStyle={styles.list}
        ListEmptyComponent={
          <View style={styles.centered}>
            <Text style={styles.emptyText}>No health events yet.</Text>
          </View>
        }
        renderItem={({ item }) => {
          const month = monthLabelFor(item.event_date);
          const showMonth = month !== lastMonth;
          lastMonth = month;

          return (
            <View>
              {showMonth && <Text style={styles.monthLabel}>{month}</Text>}
              <View style={styles.row}>
                <View style={[styles.kindBar, item.kind === "vital" && styles.kindBarVital]} />
                <View style={styles.textCol}>
                  <View style={styles.subTypePill}>
                    <Text style={styles.subTypePillText}>{subTypeLabel(item)}</Text>
                  </View>
                  <Text style={styles.title}>{entryTitle(item)}</Text>
                  <Text style={styles.member}>{item.member_name}</Text>
                </View>
                <Text style={styles.date}>
                  {new Date(item.event_date).toLocaleDateString(undefined, { day: "2-digit", month: "short" })}
                </Text>
              </View>
            </View>
          );
        }}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1 },
  centered: { flex: 1, alignItems: "center", justifyContent: "center", padding: 24 },
  emptyText: { color: "#888" },
  memberRow: { paddingHorizontal: 20, paddingVertical: 12, gap: 8 },
  chip: { borderRadius: 20, paddingVertical: 6, paddingHorizontal: 14, backgroundColor: "#fff" },
  chipSelected: { backgroundColor: "#0f766e" },
  chipText: { fontSize: 13, color: "#333", fontWeight: "600" },
  chipTextSelected: { color: "#fff" },
  list: { paddingHorizontal: 20, paddingBottom: 20 },
  monthLabel: { fontSize: 13, fontWeight: "700", color: "#666", marginTop: 8, marginBottom: 8 },
  row: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    backgroundColor: "#fff",
    borderRadius: 12,
    padding: 14,
    marginBottom: 10,
  },
  kindBar: { width: 4, height: 40, borderRadius: 2, backgroundColor: "#0f766e" },
  kindBarVital: { backgroundColor: "#d97706" },
  textCol: { flex: 1, gap: 4 },
  subTypePill: {
    alignSelf: "flex-start",
    borderWidth: 1,
    borderColor: "#ddd",
    borderRadius: 20,
    paddingVertical: 3,
    paddingHorizontal: 10,
  },
  subTypePillText: { fontSize: 11, color: "#333", textTransform: "capitalize" },
  title: { fontSize: 14, fontWeight: "700" },
  member: { fontSize: 12, color: "#666" },
  date: { fontSize: 12, color: "#666", fontWeight: "600" },
});
