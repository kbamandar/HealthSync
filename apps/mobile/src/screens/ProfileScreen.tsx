import { useState } from "react";
import { ActivityIndicator, ScrollView, StyleSheet, Text, TextInput, TouchableOpacity, View } from "react-native";
import { useNavigation } from "@react-navigation/native";
import type { NativeStackNavigationProp } from "@react-navigation/native-stack";

import { useAuth } from "../auth/AuthContext";
import type { ProfileStackParamList } from "../navigation/ProfileStack";

export default function ProfileScreen() {
  const navigation = useNavigation<NativeStackNavigationProp<ProfileStackParamList>>();
  const { user, completeProfile, logout } = useAuth();
  const [isEditing, setIsEditing] = useState(false);
  const [name, setName] = useState(user?.name ?? "");
  const [emergencyContactName, setEmergencyContactName] = useState(user?.emergency_contact_name ?? "");
  const [emergencyContactMobile, setEmergencyContactMobile] = useState(user?.emergency_contact_mobile ?? "");
  const [isSaving, setIsSaving] = useState(false);

  if (!user) {
    return (
      <View style={styles.centered}>
        <ActivityIndicator />
      </View>
    );
  }

  async function handleSave() {
    setIsSaving(true);
    try {
      await completeProfile({
        name: name.trim(),
        emergency_contact_name: emergencyContactName.trim() || undefined,
        emergency_contact_mobile: emergencyContactMobile.trim() || undefined,
      });
      setIsEditing(false);
    } finally {
      setIsSaving(false);
    }
  }

  return (
    <ScrollView contentContainerStyle={styles.container}>
      <View style={styles.field}>
        <Text style={styles.label}>Name</Text>
        {isEditing ? (
          <TextInput style={styles.input} value={name} onChangeText={setName} />
        ) : (
          <Text style={styles.value}>{user.name}</Text>
        )}
      </View>

      <View style={styles.field}>
        <Text style={styles.label}>Email</Text>
        <Text style={styles.value}>{user.email}</Text>
      </View>

      <View style={styles.field}>
        <Text style={styles.label}>Mobile</Text>
        <Text style={styles.value}>{user.mobile}</Text>
      </View>

      <View style={styles.field}>
        <Text style={styles.label}>Gender</Text>
        <Text style={styles.value}>{user.gender ?? "—"}</Text>
      </View>

      <View style={styles.field}>
        <Text style={styles.label}>Blood group</Text>
        <Text style={styles.value}>{user.blood_group ?? "—"}</Text>
      </View>

      <View style={styles.field}>
        <Text style={styles.label}>Emergency contact</Text>
        {isEditing ? (
          <>
            <TextInput
              style={styles.input}
              placeholder="Name"
              value={emergencyContactName}
              onChangeText={setEmergencyContactName}
            />
            <TextInput
              style={[styles.input, { marginTop: 8 }]}
              placeholder="Mobile"
              value={emergencyContactMobile}
              onChangeText={setEmergencyContactMobile}
            />
          </>
        ) : (
          <Text style={styles.value}>
            {user.emergency_contact_name ?? "—"} {user.emergency_contact_mobile ? `(${user.emergency_contact_mobile})` : ""}
          </Text>
        )}
      </View>

      {isEditing ? (
        <TouchableOpacity style={styles.button} onPress={handleSave} disabled={isSaving}>
          {isSaving ? <ActivityIndicator color="#fff" /> : <Text style={styles.buttonText}>Save</Text>}
        </TouchableOpacity>
      ) : (
        <TouchableOpacity style={styles.button} onPress={() => setIsEditing(true)}>
          <Text style={styles.buttonText}>Edit profile</Text>
        </TouchableOpacity>
      )}

      <TouchableOpacity style={styles.secondaryButton} onPress={() => navigation.navigate("Doctors")}>
        <Text style={styles.secondaryButtonText}>Doctors</Text>
      </TouchableOpacity>

      <TouchableOpacity style={styles.logoutButton} onPress={logout}>
        <Text style={styles.logoutText}>Log out</Text>
      </TouchableOpacity>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { padding: 24, gap: 16 },
  centered: { flex: 1, alignItems: "center", justifyContent: "center" },
  field: { gap: 4 },
  label: { fontSize: 12, color: "#888", textTransform: "uppercase" },
  value: { fontSize: 16, color: "#111" },
  input: {
    borderWidth: 1,
    borderColor: "#ddd",
    borderRadius: 8,
    padding: 10,
    fontSize: 16,
  },
  button: {
    backgroundColor: "#0f766e",
    borderRadius: 8,
    padding: 14,
    alignItems: "center",
    marginTop: 8,
  },
  buttonText: { color: "#fff", fontWeight: "600", fontSize: 16 },
  secondaryButton: {
    borderWidth: 1,
    borderColor: "#ddd",
    borderRadius: 8,
    padding: 14,
    alignItems: "center",
    marginTop: 8,
  },
  secondaryButtonText: { fontWeight: "600", fontSize: 16, color: "#0f172a" },
  logoutButton: { alignItems: "center", padding: 14 },
  logoutText: { color: "#dc2626", fontSize: 15 },
});
