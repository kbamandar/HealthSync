import { useCallback, useState } from "react";
import { ActivityIndicator, FlatList, Linking, StyleSheet, Text, TextInput, TouchableOpacity, View } from "react-native";
import { useFocusEffect, useNavigation } from "@react-navigation/native";
import type { NativeStackNavigationProp } from "@react-navigation/native-stack";

import { doctorsApi } from "../../api/doctorsApi";
import type { Doctor } from "../../api/types";
import Avatar from "../../components/Avatar";
import type { DoctorsStackParamList } from "../../navigation/ProfileStack";

type Props = NativeStackNavigationProp<DoctorsStackParamList, "DoctorDirectory">;

export default function DoctorDirectoryScreen() {
  const navigation = useNavigation<Props>();
  const [doctors, setDoctors] = useState<Doctor[]>([]);
  const [query, setQuery] = useState("");
  const [isLoading, setIsLoading] = useState(true);

  const load = useCallback(async (q?: string) => {
    setIsLoading(true);
    try {
      setDoctors(await doctorsApi.list(q));
    } finally {
      setIsLoading(false);
    }
  }, []);

  useFocusEffect(
    useCallback(() => {
      load(query || undefined);
      // Reload the full list whenever this screen regains focus (e.g. after
      // adding/editing a doctor); the search box itself triggers via onSubmitEditing.
      // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [load]),
  );

  return (
    <View style={styles.container}>
      <View style={styles.headerRow}>
        <TextInput
          style={styles.search}
          placeholder="Search doctors, speciality, clinic"
          value={query}
          onChangeText={setQuery}
          onSubmitEditing={() => load(query || undefined)}
        />
        <TouchableOpacity style={styles.addButton} onPress={() => navigation.navigate("AddEditDoctor", {})}>
          <Text style={styles.addButtonText}>+ Add</Text>
        </TouchableOpacity>
      </View>

      {isLoading ? (
        <View style={styles.centered}>
          <ActivityIndicator />
        </View>
      ) : (
        <FlatList
          data={doctors}
          keyExtractor={(item) => item.id}
          contentContainerStyle={styles.list}
          ListEmptyComponent={
            <View style={styles.centered}>
              <Text style={styles.emptyText}>No doctors added yet.</Text>
            </View>
          }
          renderItem={({ item }) => (
            <TouchableOpacity
              style={styles.card}
              onPress={() => navigation.navigate("DoctorDetail", { id: item.id })}
            >
              <Avatar name={item.name} size={48} />
              <View style={styles.textCol}>
                <Text style={styles.name}>{item.name}</Text>
                {item.speciality && <Text style={styles.meta}>{item.speciality}</Text>}
                {item.hospital_clinic && <Text style={styles.meta}>{item.hospital_clinic}</Text>}
              </View>
              <View style={styles.actions}>
                {item.phone && (
                  <TouchableOpacity style={styles.actionIcon} onPress={() => Linking.openURL(`tel:${item.phone}`)}>
                    <Text style={styles.actionIconText}>{"☎"}</Text>
                  </TouchableOpacity>
                )}
                {item.phone && (
                  <TouchableOpacity
                    style={styles.actionIcon}
                    onPress={() => Linking.openURL(`https://wa.me/${item.phone!.replace(/[^0-9]/g, "")}`)}
                  >
                    <Text style={styles.actionIconText}>{"\u{1F4AC}"}</Text>
                  </TouchableOpacity>
                )}
              </View>
            </TouchableOpacity>
          )}
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: "#f8fafc" },
  centered: { flex: 1, alignItems: "center", justifyContent: "center", padding: 24 },
  emptyText: { color: "#888" },
  headerRow: { flexDirection: "row", gap: 10, padding: 20, paddingBottom: 12 },
  search: {
    flex: 1,
    borderWidth: 1,
    borderColor: "#cbd5e1",
    borderRadius: 10,
    paddingVertical: 10,
    paddingHorizontal: 14,
    fontSize: 13,
    backgroundColor: "#fff",
  },
  addButton: { backgroundColor: "#0f766e", borderRadius: 8, paddingHorizontal: 16, justifyContent: "center" },
  addButtonText: { color: "#fff", fontWeight: "600", fontSize: 13 },
  list: { paddingHorizontal: 20, paddingBottom: 20, gap: 10 },
  card: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    backgroundColor: "#fff",
    borderRadius: 12,
    padding: 14,
    marginBottom: 10,
  },
  textCol: { flex: 1, gap: 2 },
  name: { fontSize: 14, fontWeight: "700" },
  meta: { fontSize: 12, color: "#64748b" },
  actions: { flexDirection: "row", gap: 8 },
  actionIcon: {
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: "#f0fdfa",
    alignItems: "center",
    justifyContent: "center",
  },
  actionIconText: { fontSize: 16 },
});
