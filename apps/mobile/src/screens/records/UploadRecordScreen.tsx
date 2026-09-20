import { useState } from "react";
import {
  ActivityIndicator,
  Alert,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from "react-native";
import * as ImagePicker from "expo-image-picker";
import * as DocumentPicker from "expo-document-picker";
import { File } from "expo-file-system";
import type { NativeStackScreenProps } from "@react-navigation/native-stack";

import { recordsApi } from "../../api/recordsApi";
import type { RecordCategory } from "../../api/types";
import { useFamily } from "../../family/FamilyContext";
import { CATEGORIES, CATEGORY_LABELS } from "../../records/categories";
import type { RecordsStackParamList } from "../../navigation/RecordsStack";

const ALLOWED_MIME_TYPES = ["application/pdf", "image/jpeg", "image/png"];

interface PickedFile {
  uri: string;
  name: string;
  mimeType: string;
}

type Props = NativeStackScreenProps<RecordsStackParamList, "UploadRecord">;

export default function UploadRecordScreen({ navigation }: Props) {
  const { selectedMember } = useFamily();
  const [picked, setPicked] = useState<PickedFile | null>(null);
  const [category, setCategory] = useState<RecordCategory | null>(null);
  const [title, setTitle] = useState("");
  const [recordDate, setRecordDate] = useState("");
  const [doctorName, setDoctorName] = useState("");
  const [hospitalClinic, setHospitalClinic] = useState("");
  const [isSaving, setIsSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function handleTakePhoto() {
    const permission = await ImagePicker.requestCameraPermissionsAsync();
    if (!permission.granted) {
      Alert.alert("Camera access needed", "Enable camera access in Settings to photograph a document.");
      return;
    }

    const result = await ImagePicker.launchCameraAsync({ mediaTypes: ["images"], quality: 0.8 });
    if (result.canceled) return;

    const asset = result.assets[0];
    setPicked({
      uri: asset.uri,
      name: asset.fileName ?? "photo.jpg",
      mimeType: asset.mimeType ?? "image/jpeg",
    });
  }

  async function handleChooseFile() {
    const result = await DocumentPicker.getDocumentAsync({ type: ALLOWED_MIME_TYPES });
    if (result.canceled) return;

    const asset = result.assets[0];
    if (!asset.mimeType || !ALLOWED_MIME_TYPES.includes(asset.mimeType)) {
      Alert.alert("Unsupported file", "Only PDF, JPG, and PNG files are supported.");
      return;
    }

    setPicked({ uri: asset.uri, name: asset.name, mimeType: asset.mimeType });
  }

  const canSubmit = picked !== null && category !== null && selectedMember !== null && !isSaving;

  async function handleSave() {
    if (!picked || !category || !selectedMember) return;

    setError(null);
    setIsSaving(true);
    try {
      const record = await recordsApi.create({
        member_id: selectedMember.id,
        category,
        ...(title.trim() ? { title: title.trim() } : {}),
        ...(recordDate.trim() ? { record_date: recordDate.trim() } : {}),
        ...(doctorName.trim() ? { doctor_name: doctorName.trim() } : {}),
        ...(hospitalClinic.trim() ? { hospital_clinic: hospitalClinic.trim() } : {}),
      });

      const { upload_url, s3_key } = await recordsApi.getUploadUrl(record.id, picked.mimeType);
      await new File(picked.uri).upload(upload_url, { httpMethod: "PUT", mimeType: picked.mimeType });
      await recordsApi.registerFile(record.id, { s3_key, mime_type: picked.mimeType });

      navigation.goBack();
    } catch {
      setError("Couldn't save this record. Check your connection and try again.");
    } finally {
      setIsSaving(false);
    }
  }

  return (
    <ScrollView contentContainerStyle={styles.container}>
      <View style={styles.sourceRow}>
        <TouchableOpacity style={styles.sourceCard} onPress={handleTakePhoto}>
          <Text style={styles.sourceCardText}>Take photo</Text>
        </TouchableOpacity>
        <TouchableOpacity style={styles.sourceCard} onPress={handleChooseFile}>
          <Text style={styles.sourceCardText}>Choose file</Text>
        </TouchableOpacity>
      </View>

      {picked && (
        <View style={styles.preview}>
          <View style={styles.previewThumb} />
          <Text style={styles.previewName} numberOfLines={1}>
            {picked.name}
          </Text>
        </View>
      )}

      <Text style={styles.label}>Category</Text>
      <View style={styles.chipRow}>
        {CATEGORIES.map((option) => (
          <TouchableOpacity
            key={option}
            style={[styles.chip, category === option && styles.chipSelected]}
            onPress={() => setCategory(option)}
          >
            <Text style={[styles.chipText, category === option && styles.chipTextSelected]}>
              {CATEGORY_LABELS[option]}
            </Text>
          </TouchableOpacity>
        ))}
      </View>

      <TextInput
        style={styles.input}
        placeholder="Title (e.g. CBC + Lipid Profile)"
        value={title}
        onChangeText={setTitle}
      />
      <TextInput
        style={styles.input}
        placeholder="Record date (YYYY-MM-DD)"
        value={recordDate}
        onChangeText={setRecordDate}
      />
      <TextInput style={styles.input} placeholder="Doctor name" value={doctorName} onChangeText={setDoctorName} />
      <TextInput
        style={styles.input}
        placeholder="Hospital / clinic"
        value={hospitalClinic}
        onChangeText={setHospitalClinic}
      />

      {error && <Text style={styles.error}>{error}</Text>}

      <TouchableOpacity style={[styles.button, !canSubmit && styles.buttonDisabled]} disabled={!canSubmit} onPress={handleSave}>
        {isSaving ? <ActivityIndicator color="#fff" /> : <Text style={styles.buttonText}>Save record</Text>}
      </TouchableOpacity>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { padding: 20, gap: 12 },
  sourceRow: { flexDirection: "row", gap: 10 },
  sourceCard: {
    flex: 1,
    borderWidth: 1,
    borderColor: "#ddd",
    borderRadius: 10,
    paddingVertical: 16,
    alignItems: "center",
  },
  sourceCardText: { fontWeight: "700", fontSize: 14 },
  preview: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    borderWidth: 1,
    borderColor: "#ddd",
    borderRadius: 10,
    padding: 10,
  },
  previewThumb: { width: 36, height: 36, borderRadius: 6, backgroundColor: "#f0fdfa" },
  previewName: { flex: 1, fontSize: 14 },
  label: { fontSize: 13, color: "#666", marginTop: 4 },
  chipRow: { flexDirection: "row", flexWrap: "wrap", gap: 8 },
  chip: { borderWidth: 1, borderColor: "#ddd", borderRadius: 20, paddingVertical: 8, paddingHorizontal: 16 },
  chipSelected: { backgroundColor: "#0f766e", borderColor: "#0f766e" },
  chipText: { color: "#333" },
  chipTextSelected: { color: "#fff" },
  input: { borderWidth: 1, borderColor: "#ddd", borderRadius: 8, padding: 12, fontSize: 16 },
  button: { backgroundColor: "#0f766e", borderRadius: 8, padding: 14, alignItems: "center", marginTop: 8 },
  buttonDisabled: { opacity: 0.5 },
  buttonText: { color: "#fff", fontWeight: "600", fontSize: 16 },
  error: { color: "#dc2626", fontSize: 14 },
});
