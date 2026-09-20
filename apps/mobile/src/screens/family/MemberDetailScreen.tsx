import { useEffect, useState } from "react";
import { ActivityIndicator, Alert, ScrollView, StyleSheet, Text, TextInput, TouchableOpacity, View } from "react-native";
import type { NativeStackScreenProps } from "@react-navigation/native-stack";

import Badge from "../../components/Badge";
import ShareOptionsModal from "../../components/ShareOptionsModal";
import { sharingApi } from "../../api/sharingApi";
import { useFamily } from "../../family/FamilyContext";
import type { FamilyStackParamList } from "../../navigation/FamilyStack";
import type { AccessLevel, SharedLinkWithQr } from "../../api/types";

const ACCESS_LEVELS: { value: AccessLevel; label: string }[] = [
  { value: "full_access", label: "Full access" },
  { value: "self_only", label: "Self only" },
  { value: "view_only", label: "View only" },
];

type Props = NativeStackScreenProps<FamilyStackParamList, "MemberDetail">;

export default function MemberDetailScreen({ route, navigation }: Props) {
  const { members, updateMember, removeMember } = useFamily();
  const member = members.find((m) => m.id === route.params.id);

  const [displayName, setDisplayName] = useState(member?.display_name ?? "");
  const [accessLevel, setAccessLevel] = useState<AccessLevel>(member?.access_level ?? "self_only");
  const [isSaving, setIsSaving] = useState(false);
  const [isRemoving, setIsRemoving] = useState(false);
  const [shareLink, setShareLink] = useState<SharedLinkWithQr | null>(null);
  const [isSharing, setIsSharing] = useState(false);

  useEffect(() => {
    if (member) {
      setDisplayName(member.display_name);
      setAccessLevel(member.access_level);
    }
  }, [member]);

  if (!member) {
    return (
      <View style={styles.centered}>
        <Text>This family member is no longer available.</Text>
      </View>
    );
  }

  const currentMember = member;
  const isSelf = currentMember.relationship === "self";

  async function handleSave() {
    setIsSaving(true);
    try {
      await updateMember(currentMember.id, { display_name: displayName.trim(), access_level: accessLevel });
    } finally {
      setIsSaving(false);
    }
  }

  async function handleShare() {
    setIsSharing(true);
    try {
      setShareLink(await sharingApi.shareSummary(currentMember.id));
    } finally {
      setIsSharing(false);
    }
  }

  function confirmRemove() {
    Alert.alert("Remove family member?", `${currentMember.display_name} will lose access to this family group.`, [
      { text: "Cancel", style: "cancel" },
      {
        text: "Remove",
        style: "destructive",
        onPress: async () => {
          setIsRemoving(true);
          try {
            await removeMember(currentMember.id);
            navigation.goBack();
          } finally {
            setIsRemoving(false);
          }
        },
      },
    ]);
  }

  return (
    <ScrollView contentContainerStyle={styles.container}>
      {member.invite_status === "pending" && (
        <Badge label={`Invite pending — sent to ${member.invite_email}`} tone="warning" />
      )}
      {member.invite_status === "declined" && <Badge label="This invite was declined" tone="warning" />}

      <View style={styles.field}>
        <Text style={styles.label}>Name</Text>
        <TextInput style={styles.input} value={displayName} onChangeText={setDisplayName} editable={!isSelf} />
      </View>

      <View style={styles.field}>
        <Text style={styles.label}>Relationship</Text>
        <Text style={styles.value}>{isSelf ? "You" : member.relationship}</Text>
      </View>

      <View style={styles.field}>
        <Text style={styles.label}>Access level</Text>
        <View style={styles.chipRow}>
          {ACCESS_LEVELS.map((option) => (
            <TouchableOpacity
              key={option.value}
              style={[styles.chip, accessLevel === option.value && styles.chipSelected]}
              onPress={() => setAccessLevel(option.value)}
              disabled={isSelf}
            >
              <Text style={[styles.chipText, accessLevel === option.value && styles.chipTextSelected]}>
                {option.label}
              </Text>
            </TouchableOpacity>
          ))}
        </View>
      </View>

      {!isSelf && (
        <TouchableOpacity style={styles.button} onPress={handleSave} disabled={isSaving}>
          {isSaving ? <ActivityIndicator color="#fff" /> : <Text style={styles.buttonText}>Save changes</Text>}
        </TouchableOpacity>
      )}

      {!isSelf && (
        <TouchableOpacity style={styles.removeButton} onPress={confirmRemove} disabled={isRemoving}>
          {isRemoving ? <ActivityIndicator color="#dc2626" /> : <Text style={styles.removeText}>Remove from family</Text>}
        </TouchableOpacity>
      )}

      <TouchableOpacity style={styles.shareButton} onPress={handleShare} disabled={isSharing}>
        {isSharing ? (
          <ActivityIndicator color="#0f766e" />
        ) : (
          <Text style={styles.shareButtonText}>Share health summary</Text>
        )}
      </TouchableOpacity>

      <ShareOptionsModal visible={shareLink !== null} link={shareLink} onClose={() => setShareLink(null)} />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { padding: 24, gap: 16 },
  centered: { flex: 1, alignItems: "center", justifyContent: "center" },
  field: { gap: 6 },
  label: { fontSize: 12, color: "#888", textTransform: "uppercase" },
  value: { fontSize: 16, color: "#111", textTransform: "capitalize" },
  input: { borderWidth: 1, borderColor: "#ddd", borderRadius: 8, padding: 10, fontSize: 16 },
  chipRow: { flexDirection: "row", flexWrap: "wrap", gap: 8 },
  chip: { borderWidth: 1, borderColor: "#ddd", borderRadius: 20, paddingVertical: 8, paddingHorizontal: 16 },
  chipSelected: { backgroundColor: "#0f766e", borderColor: "#0f766e" },
  chipText: { color: "#333" },
  chipTextSelected: { color: "#fff" },
  button: { backgroundColor: "#0f766e", borderRadius: 8, padding: 14, alignItems: "center", marginTop: 8 },
  buttonText: { color: "#fff", fontWeight: "600", fontSize: 16 },
  removeButton: { alignItems: "center", padding: 14 },
  removeText: { color: "#dc2626", fontSize: 15 },
  shareButton: {
    backgroundColor: "#f0fdfa",
    borderRadius: 8,
    padding: 14,
    alignItems: "center",
    marginTop: 8,
  },
  shareButtonText: { fontSize: 15, fontWeight: "600", color: "#0f766e" },
});
