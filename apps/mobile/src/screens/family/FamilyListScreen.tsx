import { useState } from "react";
import { ActivityIndicator, FlatList, RefreshControl, StyleSheet, Text, TouchableOpacity, View } from "react-native";
import type { NativeStackScreenProps } from "@react-navigation/native-stack";

import Avatar from "../../components/Avatar";
import Badge from "../../components/Badge";
import { useFamily } from "../../family/FamilyContext";
import type { FamilyStackParamList } from "../../navigation/FamilyStack";
import type { FamilyMember } from "../../api/types";

const ACCESS_LABELS: Record<string, string> = {
  full_access: "Full access",
  self_only: "Self only",
  view_only: "View only",
};

function StatusBadges({ member }: { member: FamilyMember }) {
  return (
    <View style={styles.badgeRow}>
      <Badge label={ACCESS_LABELS[member.access_level] ?? member.access_level} />
      {member.is_guardian_managed && <Badge label="Guardian-managed" tone="neutral" />}
      {member.invite_status === "pending" && <Badge label="Invite pending" tone="warning" />}
      {member.invite_status === "declined" && <Badge label="Declined" tone="warning" />}
    </View>
  );
}

type Props = NativeStackScreenProps<FamilyStackParamList, "FamilyList">;

export default function FamilyListScreen({ navigation }: Props) {
  const { members, isLoading, refresh } = useFamily();
  const [isRefreshing, setIsRefreshing] = useState(false);

  async function handleRefresh() {
    setIsRefreshing(true);
    try {
      await refresh();
    } finally {
      setIsRefreshing(false);
    }
  }

  if (isLoading && members.length === 0) {
    return (
      <View style={styles.centered}>
        <ActivityIndicator />
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <FlatList
        data={members}
        keyExtractor={(item) => item.id}
        contentContainerStyle={styles.list}
        refreshControl={<RefreshControl refreshing={isRefreshing} onRefresh={handleRefresh} />}
        renderItem={({ item }) => (
          <TouchableOpacity style={styles.row} onPress={() => navigation.navigate("MemberDetail", { id: item.id })}>
            <Avatar name={item.display_name} />
            <View style={styles.rowText}>
              <Text style={styles.name}>
                {item.display_name}
                {item.relationship === "self" ? " (you)" : ""}
              </Text>
              <Text style={styles.relationship}>{item.custom_label ?? item.relationship}</Text>
              <StatusBadges member={item} />
            </View>
          </TouchableOpacity>
        )}
      />

      <TouchableOpacity style={styles.addButton} onPress={() => navigation.navigate("AddMember")}>
        <Text style={styles.addButtonText}>+ Add family member</Text>
      </TouchableOpacity>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1 },
  centered: { flex: 1, alignItems: "center", justifyContent: "center" },
  list: { padding: 16, gap: 12 },
  row: { flexDirection: "row", gap: 12, alignItems: "center", padding: 12, backgroundColor: "#fff", borderRadius: 12 },
  rowText: { flex: 1, gap: 4 },
  name: { fontSize: 16, fontWeight: "600" },
  relationship: { fontSize: 13, color: "#666", textTransform: "capitalize" },
  badgeRow: { flexDirection: "row", gap: 6, flexWrap: "wrap", marginTop: 2 },
  addButton: {
    margin: 16,
    backgroundColor: "#0f766e",
    borderRadius: 8,
    padding: 14,
    alignItems: "center",
  },
  addButtonText: { color: "#fff", fontWeight: "600", fontSize: 16 },
});
