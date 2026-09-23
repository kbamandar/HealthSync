import { useState } from "react";
import { FlatList, Modal, Pressable, StyleSheet, Text, TouchableOpacity, View } from "react-native";

import { useFamily } from "../family/FamilyContext";
import Avatar from "./Avatar";

export default function FamilyMemberSwitcher() {
  const { members, selectedMember, selectMember } = useFamily();
  const [isOpen, setIsOpen] = useState(false);

  if (!selectedMember) return null;

  return (
    <>
      <TouchableOpacity
        style={styles.trigger}
        onPress={() => setIsOpen(true)}
        accessibilityRole="button"
        accessibilityLabel={`Viewing records for ${selectedMember.display_name}. Tap to switch family member.`}
      >
        <Avatar name={selectedMember.display_name} size={28} />
      </TouchableOpacity>

      <Modal visible={isOpen} animationType="slide" transparent onRequestClose={() => setIsOpen(false)}>
        <Pressable style={styles.backdrop} onPress={() => setIsOpen(false)}>
          <View style={styles.sheet}>
            <Text style={styles.title}>Viewing records for</Text>
            <FlatList
              data={members.filter((m) => m.invite_status !== "declined")}
              keyExtractor={(item) => item.id}
              renderItem={({ item }) => (
                <TouchableOpacity
                  style={styles.row}
                  onPress={() => {
                    selectMember(item.id);
                    setIsOpen(false);
                  }}
                >
                  <Avatar name={item.display_name} size={36} />
                  <Text style={styles.rowText}>
                    {item.display_name}
                    {item.id === selectedMember.id ? " ✓" : ""}
                  </Text>
                </TouchableOpacity>
              )}
            />
          </View>
        </Pressable>
      </Modal>
    </>
  );
}

const styles = StyleSheet.create({
  trigger: { marginRight: 16 },
  backdrop: { flex: 1, backgroundColor: "rgba(0,0,0,0.4)", justifyContent: "flex-end" },
  sheet: { backgroundColor: "#fff", borderTopLeftRadius: 16, borderTopRightRadius: 16, padding: 20, maxHeight: "60%" },
  title: { fontSize: 14, color: "#888", marginBottom: 12 },
  row: { flexDirection: "row", alignItems: "center", gap: 12, paddingVertical: 10 },
  rowText: { fontSize: 16 },
});
