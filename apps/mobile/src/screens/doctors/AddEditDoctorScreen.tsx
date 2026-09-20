import { useEffect, useState } from "react";
import { ActivityIndicator, ScrollView, StyleSheet, Text, TextInput, TouchableOpacity, View } from "react-native";
import { useNavigation } from "@react-navigation/native";
import type { NativeStackScreenProps } from "@react-navigation/native-stack";

import { doctorsApi } from "../../api/doctorsApi";
import type { DoctorsStackParamList } from "../../navigation/ProfileStack";

type Props = NativeStackScreenProps<DoctorsStackParamList, "AddEditDoctor">;

export default function AddEditDoctorScreen({ route }: Props) {
  const { id } = route.params;
  const navigation = useNavigation();
  const isEditing = Boolean(id);

  const [name, setName] = useState("");
  const [speciality, setSpeciality] = useState("");
  const [hospitalClinic, setHospitalClinic] = useState("");
  const [phone, setPhone] = useState("");
  const [location, setLocation] = useState("");
  const [isLoading, setIsLoading] = useState(isEditing);
  const [isSaving, setIsSaving] = useState(false);

  useEffect(() => {
    if (!id) return;
    (async () => {
      const doctors = await doctorsApi.list();
      const doctor = doctors.find((d) => d.id === id);
      if (doctor) {
        setName(doctor.name);
        setSpeciality(doctor.speciality ?? "");
        setHospitalClinic(doctor.hospital_clinic ?? "");
        setPhone(doctor.phone ?? "");
        setLocation(doctor.location ?? "");
      }
      setIsLoading(false);
    })();
  }, [id]);

  async function handleSave() {
    setIsSaving(true);
    try {
      const input = {
        name: name.trim(),
        speciality: speciality.trim() || null,
        hospital_clinic: hospitalClinic.trim() || null,
        phone: phone.trim() || null,
        location: location.trim() || null,
      };

      if (id) {
        await doctorsApi.update(id, input);
      } else {
        await doctorsApi.create(input);
      }
      navigation.goBack();
    } finally {
      setIsSaving(false);
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
    <ScrollView contentContainerStyle={styles.container}>
      <View style={styles.field}>
        <Text style={styles.label}>Name</Text>
        <TextInput style={styles.input} value={name} onChangeText={setName} placeholder="Dr. Full name" />
      </View>

      <View style={styles.field}>
        <Text style={styles.label}>Speciality</Text>
        <TextInput style={styles.input} value={speciality} onChangeText={setSpeciality} placeholder="Cardiologist" />
      </View>

      <View style={styles.field}>
        <Text style={styles.label}>Hospital / clinic</Text>
        <TextInput style={styles.input} value={hospitalClinic} onChangeText={setHospitalClinic} />
      </View>

      <View style={styles.field}>
        <Text style={styles.label}>Phone</Text>
        <TextInput style={styles.input} value={phone} onChangeText={setPhone} keyboardType="phone-pad" />
      </View>

      <View style={styles.field}>
        <Text style={styles.label}>Location</Text>
        <TextInput style={styles.input} value={location} onChangeText={setLocation} />
      </View>

      <TouchableOpacity style={styles.button} onPress={handleSave} disabled={isSaving || !name.trim()}>
        {isSaving ? (
          <ActivityIndicator color="#fff" />
        ) : (
          <Text style={styles.buttonText}>{isEditing ? "Save changes" : "Add doctor"}</Text>
        )}
      </TouchableOpacity>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { padding: 20, gap: 14 },
  centered: { flex: 1, alignItems: "center", justifyContent: "center" },
  field: { gap: 6 },
  label: { fontSize: 12, color: "#888", textTransform: "uppercase" },
  input: { borderWidth: 1, borderColor: "#ddd", borderRadius: 8, padding: 10, fontSize: 16 },
  button: { backgroundColor: "#0f766e", borderRadius: 8, padding: 14, alignItems: "center", marginTop: 8 },
  buttonText: { color: "#fff", fontWeight: "600", fontSize: 16 },
});
