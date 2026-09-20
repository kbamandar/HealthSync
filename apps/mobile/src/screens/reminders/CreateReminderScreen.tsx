import { useState } from "react";
import { ActivityIndicator, ScrollView, StyleSheet, Text, TextInput, TouchableOpacity, View } from "react-native";
import type { NativeStackScreenProps } from "@react-navigation/native-stack";

import { remindersApi } from "../../api/remindersApi";
import type { NotifyChannel, RecurrenceType, ReminderType } from "../../api/types";
import { useFamily } from "../../family/FamilyContext";
import { NOTIFY_CHANNELS, RECURRENCE_OPTIONS, REMINDER_TYPES } from "../../reminders/reminderTypes";
import type { HomeStackParamList } from "../../navigation/HomeStack";

const CHANNEL_LABELS: Record<NotifyChannel, string> = { push: "Push", sms: "SMS", whatsapp: "WhatsApp", email: "Email" };

type Props = NativeStackScreenProps<HomeStackParamList, "CreateReminder">;

export default function CreateReminderScreen({ navigation }: Props) {
  const { members, selectedMember } = useFamily();
  const [reminderType, setReminderType] = useState<ReminderType>("medication");
  const [memberId, setMemberId] = useState(selectedMember?.id ?? members[0]?.id ?? "");
  const [title, setTitle] = useState("");
  const [date, setDate] = useState("");
  const [time, setTime] = useState("");
  const [recurrence, setRecurrence] = useState<RecurrenceType | null>(null);
  const [notifyVia, setNotifyVia] = useState<NotifyChannel>("push");
  const [isSaving, setIsSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const canSubmit = title.trim().length > 0 && date.trim().length > 0 && time.trim().length > 0 && memberId !== "";

  async function handleSave() {
    if (!canSubmit) return;

    setError(null);
    setIsSaving(true);
    try {
      const dueAt = new Date(`${date.trim()}T${time.trim()}:00`);
      if (isNaN(dueAt.getTime())) {
        setError("Enter a valid date (YYYY-MM-DD) and time (HH:MM).");
        return;
      }

      await remindersApi.create({
        member_id: memberId,
        reminder_type: reminderType,
        title: title.trim(),
        due_at: dueAt.toISOString(),
        ...(recurrence ? { recurrence } : {}),
        notify_via: [notifyVia],
      });

      navigation.goBack();
    } catch {
      setError("Couldn't save this reminder. Try again.");
    } finally {
      setIsSaving(false);
    }
  }

  return (
    <ScrollView contentContainerStyle={styles.container}>
      <Text style={styles.label}>Type</Text>
      <View style={styles.chipRow}>
        {REMINDER_TYPES.map((option) => (
          <TouchableOpacity
            key={option.type}
            style={[styles.chip, reminderType === option.type && styles.chipSelected]}
            onPress={() => setReminderType(option.type)}
          >
            <Text style={[styles.chipText, reminderType === option.type && styles.chipTextSelected]}>
              {option.label}
            </Text>
          </TouchableOpacity>
        ))}
      </View>

      <Text style={styles.label}>For</Text>
      <View style={styles.chipRow}>
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
      </View>

      <TextInput style={styles.input} placeholder="Title (e.g. Metformin 500mg)" value={title} onChangeText={setTitle} />

      <View style={styles.row}>
        <TextInput
          style={[styles.input, styles.flexInput]}
          placeholder="Date (YYYY-MM-DD)"
          value={date}
          onChangeText={setDate}
        />
        <TextInput
          style={[styles.input, styles.flexInput]}
          placeholder="Time (HH:MM)"
          value={time}
          onChangeText={setTime}
        />
      </View>

      <Text style={styles.label}>Repeat</Text>
      <View style={styles.chipRow}>
        {RECURRENCE_OPTIONS.map((option) => (
          <TouchableOpacity
            key={option.label}
            style={[styles.chip, recurrence === option.value && styles.chipSelected]}
            onPress={() => setRecurrence(option.value)}
          >
            <Text style={[styles.chipText, recurrence === option.value && styles.chipTextSelected]}>
              {option.label}
            </Text>
          </TouchableOpacity>
        ))}
      </View>

      <Text style={styles.label}>Notify via</Text>
      <View style={styles.chipRow}>
        {NOTIFY_CHANNELS.map((channel) => (
          <TouchableOpacity
            key={channel}
            style={[styles.chip, notifyVia === channel && styles.chipSelected]}
            onPress={() => setNotifyVia(channel)}
          >
            <Text style={[styles.chipText, notifyVia === channel && styles.chipTextSelected]}>
              {CHANNEL_LABELS[channel]}
            </Text>
          </TouchableOpacity>
        ))}
      </View>

      {error && <Text style={styles.error}>{error}</Text>}

      <TouchableOpacity
        style={[styles.button, !canSubmit && styles.buttonDisabled]}
        disabled={!canSubmit || isSaving}
        onPress={handleSave}
      >
        {isSaving ? <ActivityIndicator color="#fff" /> : <Text style={styles.buttonText}>Save reminder</Text>}
      </TouchableOpacity>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { padding: 20, gap: 12 },
  label: { fontSize: 13, color: "#666" },
  row: { flexDirection: "row", gap: 10 },
  flexInput: { flex: 1 },
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
