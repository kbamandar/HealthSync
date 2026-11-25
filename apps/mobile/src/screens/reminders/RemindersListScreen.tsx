import { useCallback, useState } from "react";
import { ActivityIndicator, SectionList, StyleSheet, Text, TouchableOpacity, View } from "react-native";
import { useFocusEffect } from "@react-navigation/native";
import type { NativeStackScreenProps } from "@react-navigation/native-stack";

import { remindersApi } from "../../api/remindersApi";
import type { Reminder } from "../../api/types";
import { useFamily } from "../../family/FamilyContext";
import { REMINDER_TYPE_LABELS } from "../../reminders/reminderTypes";
import type { HomeStackParamList } from "../../navigation/HomeStack";

type Props = NativeStackScreenProps<HomeStackParamList, "Reminders">;

function sectionFor(dueAt: string): string {
  const due = new Date(dueAt);
  const now = new Date();
  const endOfToday = new Date(now.getFullYear(), now.getMonth(), now.getDate(), 23, 59, 59);
  const endOfWeek = new Date(endOfToday);
  endOfWeek.setDate(endOfWeek.getDate() + (7 - now.getDay()));

  if (due <= endOfToday) return "Today";
  if (due <= endOfWeek) return "This week";
  return "Later";
}

export default function RemindersListScreen({ navigation }: Props) {
  const { members } = useFamily();
  const [reminders, setReminders] = useState<Reminder[]>([]);
  const [isLoading, setIsLoading] = useState(true);

  const load = useCallback(async () => {
    setIsLoading(true);
    try {
      setReminders(await remindersApi.list());
    } finally {
      setIsLoading(false);
    }
  }, []);

  useFocusEffect(
    useCallback(() => {
      load();
    }, [load]),
  );

  function memberName(memberId: string): string {
    return members.find((m) => m.id === memberId)?.display_name ?? "";
  }

  const activeReminders = reminders.filter((r) => r.is_active).sort((a, b) => a.due_at.localeCompare(b.due_at));
  const sections = ["Today", "This week", "Later"]
    .map((title) => ({ title, data: activeReminders.filter((r) => sectionFor(r.due_at) === title) }))
    .filter((section) => section.data.length > 0);

  if (isLoading && reminders.length === 0) {
    return (
      <View style={styles.centered}>
        <ActivityIndicator />
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <SectionList
        sections={sections}
        keyExtractor={(item) => item.id}
        contentContainerStyle={styles.list}
        renderSectionHeader={({ section }) => <Text style={styles.sectionLabel}>{section.title}</Text>}
        renderItem={({ item }) => (
          <View style={styles.row}>
            <View style={styles.dot} />
            <View style={styles.textCol}>
              <View style={styles.typePill}>
                <Text style={styles.typePillText}>{REMINDER_TYPE_LABELS[item.reminder_type]}</Text>
              </View>
              <Text style={styles.title}>{item.title}</Text>
              <Text style={styles.member}>{memberName(item.member_id)}</Text>
            </View>
            <Text style={styles.when}>
              {new Date(item.due_at).toLocaleString(undefined, {
                weekday: "short",
                hour: "numeric",
                minute: "2-digit",
              })}
            </Text>
          </View>
        )}
        ListEmptyComponent={
          <View style={styles.centered}>
            <Text style={styles.emptyText}>No reminders yet.</Text>
          </View>
        }
      />

      <TouchableOpacity style={styles.addButton} onPress={() => navigation.navigate("CreateReminder")}>
        <Text style={styles.addButtonText}>+ Add reminder</Text>
      </TouchableOpacity>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1 },
  centered: { flex: 1, alignItems: "center", justifyContent: "center", padding: 24 },
  emptyText: { color: "#888" },
  list: { padding: 20, paddingBottom: 90, gap: 8 },
  sectionLabel: { fontSize: 13, fontWeight: "700", color: "#666", marginTop: 12, marginBottom: 8 },
  row: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    backgroundColor: "#fff",
    borderRadius: 12,
    padding: 14,
    marginBottom: 10,
  },
  dot: { width: 10, height: 10, borderRadius: 5, backgroundColor: "#0f766e" },
  textCol: { flex: 1, gap: 4 },
  typePill: {
    alignSelf: "flex-start",
    borderWidth: 1,
    borderColor: "#ddd",
    borderRadius: 20,
    paddingVertical: 3,
    paddingHorizontal: 10,
  },
  typePillText: { fontSize: 11, color: "#333" },
  title: { fontSize: 14, fontWeight: "700" },
  member: { fontSize: 12, color: "#666" },
  when: { fontSize: 12, color: "#666", fontWeight: "600" },
  addButton: {
    position: "absolute",
    left: 16,
    right: 16,
    bottom: 16,
    backgroundColor: "#0f766e",
    borderRadius: 8,
    padding: 14,
    alignItems: "center",
  },
  addButtonText: { color: "#fff", fontWeight: "600", fontSize: 16 },
});
