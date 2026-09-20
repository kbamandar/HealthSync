import { useCallback, useState } from "react";
import { ActivityIndicator, Alert, Linking, ScrollView, StyleSheet, Text, TouchableOpacity, View } from "react-native";
import { useFocusEffect, useNavigation } from "@react-navigation/native";
import type { NativeStackNavigationProp, NativeStackScreenProps } from "@react-navigation/native-stack";

import { doctorsApi } from "../../api/doctorsApi";
import type { Doctor, DoctorVisit } from "../../api/types";
import Avatar from "../../components/Avatar";
import { CATEGORY_LABELS } from "../../records/categories";
import type { DoctorsStackParamList } from "../../navigation/ProfileStack";

type Props = NativeStackScreenProps<DoctorsStackParamList, "DoctorDetail">;

export default function DoctorDetailScreen({ route }: Props) {
  const { id } = route.params;
  const navigation = useNavigation<NativeStackNavigationProp<DoctorsStackParamList>>();
  const [doctor, setDoctor] = useState<Doctor | null>(null);
  const [visits, setVisits] = useState<DoctorVisit[]>([]);
  const [isLoading, setIsLoading] = useState(true);

  const load = useCallback(async () => {
    setIsLoading(true);
    try {
      const [doctors, doctorVisits] = await Promise.all([doctorsApi.list(), doctorsApi.visits(id)]);
      setDoctor(doctors.find((d) => d.id === id) ?? null);
      setVisits(doctorVisits);
    } finally {
      setIsLoading(false);
    }
  }, [id]);

  useFocusEffect(
    useCallback(() => {
      load();
    }, [load]),
  );

  function confirmRemove() {
    Alert.alert("Remove this doctor?", "This won't affect any linked health records.", [
      { text: "Cancel", style: "cancel" },
      {
        text: "Remove",
        style: "destructive",
        onPress: async () => {
          await doctorsApi.remove(id);
          navigation.goBack();
        },
      },
    ]);
  }

  if (isLoading || !doctor) {
    return (
      <View style={styles.centered}>
        <ActivityIndicator />
      </View>
    );
  }

  return (
    <ScrollView contentContainerStyle={styles.container}>
      <View style={styles.headerCard}>
        <Avatar name={doctor.name} size={64} />
        <Text style={styles.name}>{doctor.name}</Text>
        {(doctor.speciality || doctor.hospital_clinic) && (
          <Text style={styles.subtitle}>
            {[doctor.speciality, doctor.hospital_clinic].filter(Boolean).join(" · ")}
          </Text>
        )}

        <View style={styles.actionsRow}>
          {doctor.phone && (
            <TouchableOpacity style={styles.actionButton} onPress={() => Linking.openURL(`tel:${doctor.phone}`)}>
              <View style={styles.actionIcon}>
                <Text style={styles.actionIconText}>{"☎"}</Text>
              </View>
              <Text style={styles.actionLabel}>Call</Text>
            </TouchableOpacity>
          )}
          {doctor.phone && (
            <TouchableOpacity
              style={styles.actionButton}
              onPress={() => Linking.openURL(`https://wa.me/${doctor.phone!.replace(/[^0-9]/g, "")}`)}
            >
              <View style={styles.actionIcon}>
                <Text style={styles.actionIconText}>{"\u{1F4AC}"}</Text>
              </View>
              <Text style={styles.actionLabel}>WhatsApp</Text>
            </TouchableOpacity>
          )}
          <TouchableOpacity
            style={styles.actionButton}
            onPress={() => navigation.navigate("AddEditDoctor", { id: doctor.id })}
          >
            <View style={styles.actionIcon}>
              <Text style={styles.actionIconText}>{"✎"}</Text>
            </View>
            <Text style={styles.actionLabel}>Edit</Text>
          </TouchableOpacity>
        </View>
      </View>

      {visits.length > 0 && (
        <View style={styles.section}>
          <Text style={styles.sectionLabel}>VISIT HISTORY</Text>
          <View style={styles.visitCard}>
            {visits.map((visit) => (
              <View key={visit.record_id} style={styles.visitRow}>
                <View style={styles.dot} />
                <View style={styles.visitTextCol}>
                  <Text style={styles.visitDate}>{visit.record_date ?? "Undated"}</Text>
                  <Text style={styles.visitTitle}>
                    {visit.title || CATEGORY_LABELS[visit.category]}
                  </Text>
                </View>
              </View>
            ))}
          </View>
        </View>
      )}

      <TouchableOpacity style={styles.removeButton} onPress={confirmRemove}>
        <Text style={styles.removeText}>Remove doctor</Text>
      </TouchableOpacity>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { padding: 20, gap: 20 },
  centered: { flex: 1, alignItems: "center", justifyContent: "center" },
  headerCard: { backgroundColor: "#fff", borderRadius: 16, padding: 20, alignItems: "center", gap: 10 },
  name: { fontSize: 18, fontWeight: "700" },
  subtitle: { fontSize: 13, color: "#64748b", textAlign: "center" },
  actionsRow: { flexDirection: "row", gap: 12, marginTop: 4 },
  actionButton: { alignItems: "center", gap: 6 },
  actionIcon: {
    width: 32,
    height: 32,
    borderRadius: 16,
    backgroundColor: "#f0fdfa",
    alignItems: "center",
    justifyContent: "center",
  },
  actionIconText: { fontSize: 14 },
  actionLabel: { fontSize: 11, fontWeight: "600" },
  section: { gap: 8 },
  sectionLabel: { fontSize: 12, fontWeight: "700", color: "#64748b", letterSpacing: 0.5 },
  visitCard: { backgroundColor: "#fff", borderRadius: 12, padding: 16, gap: 14 },
  visitRow: { flexDirection: "row", gap: 12, alignItems: "flex-start" },
  dot: { width: 8, height: 8, borderRadius: 4, backgroundColor: "#0f766e", marginTop: 4 },
  visitTextCol: { flex: 1, gap: 2 },
  visitDate: { fontSize: 12, fontWeight: "700", color: "#64748b" },
  visitTitle: { fontSize: 13, color: "#0f172a" },
  removeButton: { alignItems: "center", padding: 14 },
  removeText: { color: "#dc2626", fontSize: 15 },
});
