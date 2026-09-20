import { useCallback, useState } from "react";
import { ActivityIndicator, FlatList, StyleSheet, Text, TouchableOpacity, View } from "react-native";
import { useFocusEffect } from "@react-navigation/native";

import { recordsApi } from "../../api/recordsApi";
import type { HealthRecord } from "../../api/types";
import { CATEGORY_LABELS } from "../../records/categories";

export default function RecycleBinScreen() {
  const [records, setRecords] = useState<HealthRecord[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [restoringId, setRestoringId] = useState<string | null>(null);

  const load = useCallback(async () => {
    setIsLoading(true);
    try {
      setRecords(await recordsApi.recycleBin());
    } finally {
      setIsLoading(false);
    }
  }, []);

  useFocusEffect(
    useCallback(() => {
      load();
    }, [load]),
  );

  async function handleRestore(id: string) {
    setRestoringId(id);
    try {
      await recordsApi.restore(id);
      await load();
    } finally {
      setRestoringId(null);
    }
  }

  if (isLoading) {
    return (
      <View style={styles.centered}>
        <ActivityIndicator />
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <Text style={styles.subtitle}>Records are permanently deleted after 30 days.</Text>
      <FlatList
        data={records}
        keyExtractor={(item) => item.id}
        contentContainerStyle={styles.list}
        ListEmptyComponent={
          <View style={styles.centered}>
            <Text style={styles.emptyText}>Recycle bin is empty.</Text>
          </View>
        }
        renderItem={({ item }) => (
          <View style={styles.card}>
            <View style={styles.textCol}>
              <View style={styles.categoryPill}>
                <Text style={styles.categoryPillText}>{CATEGORY_LABELS[item.category]}</Text>
              </View>
              <Text style={styles.title}>{item.title || "Untitled record"}</Text>
              <Text style={styles.deletedAt}>Deleted {new Date(item.deleted_at!).toLocaleDateString()}</Text>
            </View>
            <TouchableOpacity style={styles.restoreButton} onPress={() => handleRestore(item.id)} disabled={restoringId === item.id}>
              {restoringId === item.id ? (
                <ActivityIndicator size="small" />
              ) : (
                <Text style={styles.restoreText}>Restore</Text>
              )}
            </TouchableOpacity>
          </View>
        )}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1 },
  centered: { flex: 1, alignItems: "center", justifyContent: "center", padding: 24 },
  emptyText: { color: "#888" },
  subtitle: { fontSize: 13, color: "#666", paddingHorizontal: 20, paddingTop: 16 },
  list: { padding: 16, gap: 12, flexGrow: 1 },
  card: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    backgroundColor: "#fff",
    borderRadius: 12,
    padding: 14,
  },
  textCol: { flex: 1, gap: 4 },
  categoryPill: {
    alignSelf: "flex-start",
    borderWidth: 1,
    borderColor: "#ddd",
    borderRadius: 20,
    paddingVertical: 4,
    paddingHorizontal: 12,
  },
  categoryPillText: { fontSize: 12, color: "#333" },
  title: { fontSize: 15, fontWeight: "700" },
  deletedAt: { fontSize: 12, color: "#888" },
  restoreButton: { borderWidth: 1, borderColor: "#ddd", borderRadius: 8, paddingVertical: 8, paddingHorizontal: 14 },
  restoreText: { fontWeight: "600" },
});
