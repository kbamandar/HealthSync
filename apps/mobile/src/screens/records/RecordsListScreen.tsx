import { useCallback, useState } from "react";
import {
  ActivityIndicator,
  FlatList,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from "react-native";
import { useFocusEffect } from "@react-navigation/native";
import type { NativeStackScreenProps } from "@react-navigation/native-stack";

import { recordsApi } from "../../api/recordsApi";
import type { HealthRecord, RecordCategory } from "../../api/types";
import { useFamily } from "../../family/FamilyContext";
import { CATEGORIES, CATEGORY_LABELS } from "../../records/categories";
import type { RecordsStackParamList } from "../../navigation/RecordsStack";

type Props = NativeStackScreenProps<RecordsStackParamList, "RecordsList">;

export default function RecordsListScreen({ navigation }: Props) {
  const { selectedMember } = useFamily();
  const [records, setRecords] = useState<HealthRecord[]>([]);
  const [category, setCategory] = useState<RecordCategory | "all">("all");
  const [search, setSearch] = useState("");
  const [isLoading, setIsLoading] = useState(true);

  const load = useCallback(async () => {
    if (!selectedMember) {
      setRecords([]);
      setIsLoading(false);
      return;
    }
    setIsLoading(true);
    try {
      const result = await recordsApi.list({
        member_id: selectedMember.id,
        category: category === "all" ? undefined : category,
      });
      setRecords(result);
    } finally {
      setIsLoading(false);
    }
  }, [selectedMember, category]);

  useFocusEffect(
    useCallback(() => {
      load();
    }, [load]),
  );

  const filtered = search.trim()
    ? records.filter((r) => (r.title ?? "").toLowerCase().includes(search.trim().toLowerCase()))
    : records;

  if (isLoading && records.length === 0) {
    return (
      <View style={styles.centered}>
        <ActivityIndicator />
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <TextInput
        style={styles.search}
        placeholder="Search records"
        value={search}
        onChangeText={setSearch}
      />

      <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.chipRow}>
        {(["all", ...CATEGORIES] as const).map((option) => (
          <TouchableOpacity
            key={option}
            style={[styles.chip, category === option && styles.chipSelected]}
            onPress={() => setCategory(option)}
          >
            <Text style={[styles.chipText, category === option && styles.chipTextSelected]}>
              {option === "all" ? "All" : CATEGORY_LABELS[option]}
            </Text>
          </TouchableOpacity>
        ))}
      </ScrollView>

      <FlatList
        data={filtered}
        keyExtractor={(item) => item.id}
        contentContainerStyle={styles.list}
        ListEmptyComponent={
          <View style={styles.centered}>
            <Text style={styles.emptyText}>No records yet. Upload your first one below.</Text>
          </View>
        }
        renderItem={({ item }) => (
          <TouchableOpacity
            style={styles.card}
            onPress={() => navigation.navigate("RecordDetail", { id: item.id })}
          >
            <View style={styles.cardTopRow}>
              <View style={styles.categoryPill}>
                <Text style={styles.categoryPillText}>{CATEGORY_LABELS[item.category]}</Text>
              </View>
              <Text style={styles.star}>{item.is_favourite ? "★" : "☆"}</Text>
            </View>
            <Text style={styles.cardTitle}>{item.title || "Untitled record"}</Text>
            <Text style={styles.cardSubtitle}>
              {[item.doctor_name, item.hospital_clinic].filter(Boolean).join(" • ")}
              {item.record_date ? ` · ${item.record_date}` : ""}
            </Text>
          </TouchableOpacity>
        )}
      />

      <TouchableOpacity style={styles.uploadButton} onPress={() => navigation.navigate("UploadRecord")}>
        <Text style={styles.uploadButtonText}>+ Upload record</Text>
      </TouchableOpacity>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1 },
  centered: { flex: 1, alignItems: "center", justifyContent: "center", padding: 24 },
  emptyText: { color: "#888", textAlign: "center" },
  search: {
    marginHorizontal: 16,
    marginTop: 12,
    borderWidth: 1,
    borderColor: "#ddd",
    borderRadius: 8,
    padding: 12,
    fontSize: 16,
  },
  chipRow: { paddingHorizontal: 16, paddingVertical: 12, gap: 8 },
  chip: { borderWidth: 1, borderColor: "#ddd", borderRadius: 20, paddingVertical: 8, paddingHorizontal: 16 },
  chipSelected: { backgroundColor: "#0f766e", borderColor: "#0f766e" },
  chipText: { color: "#333" },
  chipTextSelected: { color: "#fff" },
  list: { paddingHorizontal: 16, paddingBottom: 16, gap: 12, flexGrow: 1 },
  card: { backgroundColor: "#fff", borderRadius: 12, padding: 14, gap: 6 },
  cardTopRow: { flexDirection: "row", justifyContent: "space-between", alignItems: "center" },
  categoryPill: {
    borderWidth: 1,
    borderColor: "#ddd",
    borderRadius: 20,
    paddingVertical: 4,
    paddingHorizontal: 12,
  },
  categoryPillText: { fontSize: 12, color: "#333" },
  star: { fontSize: 18, color: "#d97706" },
  cardTitle: { fontSize: 16, fontWeight: "700" },
  cardSubtitle: { fontSize: 13, color: "#666" },
  uploadButton: {
    margin: 16,
    backgroundColor: "#0f766e",
    borderRadius: 8,
    padding: 14,
    alignItems: "center",
  },
  uploadButtonText: { color: "#fff", fontWeight: "600", fontSize: 16 },
});
