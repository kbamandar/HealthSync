import { useCallback, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  Image,
  Linking,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from "react-native";
import { useFocusEffect } from "@react-navigation/native";
import type { NativeStackScreenProps } from "@react-navigation/native-stack";

import Badge from "../../components/Badge";
import { recordsApi } from "../../api/recordsApi";
import type { HealthRecord } from "../../api/types";
import { CATEGORY_LABELS } from "../../records/categories";
import type { RecordsStackParamList } from "../../navigation/RecordsStack";

type Props = NativeStackScreenProps<RecordsStackParamList, "RecordDetail">;

export default function RecordDetailScreen({ route, navigation }: Props) {
  const { id } = route.params;
  const [record, setRecord] = useState<HealthRecord | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isAddingTag, setIsAddingTag] = useState(false);
  const [newTag, setNewTag] = useState("");

  const load = useCallback(async () => {
    setIsLoading(true);
    try {
      const result = await recordsApi.get(id);
      setRecord(result);
    } finally {
      setIsLoading(false);
    }
  }, [id]);

  useFocusEffect(
    useCallback(() => {
      load();
    }, [load]),
  );

  async function toggleFavourite() {
    if (!record) return;
    const updated = await recordsApi.update(record.id, { is_favourite: !record.is_favourite });
    setRecord((current) => (current ? { ...current, is_favourite: updated.is_favourite } : current));
  }

  async function addTag() {
    if (!record || !newTag.trim()) {
      setIsAddingTag(false);
      return;
    }
    const updated = await recordsApi.update(record.id, { custom_tags: [...record.custom_tags, newTag.trim()] });
    setRecord((current) => (current ? { ...current, custom_tags: updated.custom_tags } : current));
    setNewTag("");
    setIsAddingTag(false);
  }

  function confirmDelete() {
    if (!record) return;
    Alert.alert("Delete this record?", "You can restore it from the recycle bin for 30 days.", [
      { text: "Cancel", style: "cancel" },
      {
        text: "Delete",
        style: "destructive",
        onPress: async () => {
          await recordsApi.remove(record.id);
          navigation.goBack();
        },
      },
    ]);
  }

  if (isLoading || !record) {
    return (
      <View style={styles.centered}>
        <ActivityIndicator />
      </View>
    );
  }

  const file = record.files?.[0];
  const isImage = file?.mime_type?.startsWith("image/");

  return (
    <ScrollView contentContainerStyle={styles.container}>
      {file ? (
        isImage ? (
          <Image source={{ uri: file.download_url }} style={styles.imagePreview} resizeMode="contain" />
        ) : (
          <TouchableOpacity style={styles.filePreview} onPress={() => Linking.openURL(file.download_url)}>
            <Text style={styles.filePreviewText}>Open document</Text>
          </TouchableOpacity>
        )
      ) : (
        <View style={styles.filePreview}>
          <Text style={styles.filePreviewText}>No file attached</Text>
        </View>
      )}

      <View style={styles.headerRow}>
        <View style={styles.categoryPill}>
          <Text style={styles.categoryPillText}>{CATEGORY_LABELS[record.category]}</Text>
        </View>
        <TouchableOpacity onPress={toggleFavourite}>
          <Text style={styles.star}>{record.is_favourite ? "★" : "☆"}</Text>
        </TouchableOpacity>
      </View>

      <Text style={styles.title}>{record.title || "Untitled record"}</Text>

      {record.record_date && (
        <View style={styles.field}>
          <Text style={styles.label}>Record date</Text>
          <Text style={styles.value}>{record.record_date}</Text>
        </View>
      )}
      {record.doctor_name && (
        <View style={styles.field}>
          <Text style={styles.label}>Doctor</Text>
          <Text style={styles.value}>{record.doctor_name}</Text>
        </View>
      )}
      {record.hospital_clinic && (
        <View style={styles.field}>
          <Text style={styles.label}>Hospital / clinic</Text>
          <Text style={styles.value}>{record.hospital_clinic}</Text>
        </View>
      )}
      {record.notes && (
        <View style={styles.field}>
          <Text style={styles.label}>Notes</Text>
          <Text style={styles.value}>{record.notes}</Text>
        </View>
      )}

      <View style={styles.field}>
        <Text style={styles.label}>Tags</Text>
        <View style={styles.tagRow}>
          {record.custom_tags.map((tag) => (
            <Badge key={tag} label={tag} />
          ))}
          {isAddingTag ? (
            <TextInput
              style={styles.tagInput}
              autoFocus
              value={newTag}
              onChangeText={setNewTag}
              onSubmitEditing={addTag}
              onBlur={addTag}
              placeholder="Tag name"
            />
          ) : (
            <TouchableOpacity onPress={() => setIsAddingTag(true)}>
              <Badge label="+ Add tag" />
            </TouchableOpacity>
          )}
        </View>
      </View>

      <TouchableOpacity style={styles.editButton} onPress={() => navigation.navigate("EditRecord", { id: record.id })}>
        <Text style={styles.editButtonText}>Edit record</Text>
      </TouchableOpacity>

      <TouchableOpacity style={styles.deleteButton} onPress={confirmDelete}>
        <Text style={styles.deleteText}>Delete record</Text>
      </TouchableOpacity>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { padding: 20, gap: 14 },
  centered: { flex: 1, alignItems: "center", justifyContent: "center" },
  imagePreview: { width: "100%", height: 220, borderRadius: 12, backgroundColor: "#f0fdfa" },
  filePreview: {
    height: 140,
    borderRadius: 12,
    backgroundColor: "#f0fdfa",
    alignItems: "center",
    justifyContent: "center",
  },
  filePreviewText: { color: "#0f766e", fontWeight: "600" },
  headerRow: { flexDirection: "row", justifyContent: "space-between", alignItems: "center" },
  categoryPill: { borderWidth: 1, borderColor: "#ddd", borderRadius: 20, paddingVertical: 4, paddingHorizontal: 12 },
  categoryPillText: { fontSize: 12, color: "#333" },
  star: { fontSize: 24, color: "#d97706" },
  title: { fontSize: 22, fontWeight: "700" },
  field: { gap: 4 },
  label: { fontSize: 12, color: "#888", textTransform: "uppercase" },
  value: { fontSize: 16, color: "#111" },
  tagRow: { flexDirection: "row", flexWrap: "wrap", gap: 8, alignItems: "center" },
  tagInput: {
    borderWidth: 1,
    borderColor: "#ddd",
    borderRadius: 12,
    paddingVertical: 3,
    paddingHorizontal: 10,
    fontSize: 12,
    minWidth: 100,
  },
  editButton: { borderWidth: 1, borderColor: "#ddd", borderRadius: 8, padding: 14, alignItems: "center", marginTop: 8 },
  editButtonText: { fontSize: 16, fontWeight: "600" },
  deleteButton: { alignItems: "center", padding: 14 },
  deleteText: { color: "#dc2626", fontSize: 15 },
});
