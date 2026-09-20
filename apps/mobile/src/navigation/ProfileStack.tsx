import { Text, TouchableOpacity } from "react-native";
import { createNativeStackNavigator } from "@react-navigation/native-stack";
import type { NativeStackNavigationProp } from "@react-navigation/native-stack";
import { useNavigation } from "@react-navigation/native";

import ProfileScreen from "../screens/ProfileScreen";
import SettingsScreen from "../screens/security/SettingsScreen";
import PinSetupScreen from "../screens/security/PinSetupScreen";
import ManageSessionsScreen from "../screens/security/ManageSessionsScreen";
import AuditLogScreen from "../screens/security/AuditLogScreen";
import DoctorDirectoryScreen from "../screens/doctors/DoctorDirectoryScreen";
import DoctorDetailScreen from "../screens/doctors/DoctorDetailScreen";
import AddEditDoctorScreen from "../screens/doctors/AddEditDoctorScreen";

export type ProfileStackParamList = {
  ProfileHome: undefined;
  Settings: undefined;
  PinSetup: undefined;
  ManageSessions: undefined;
  AuditLog: undefined;
  Doctors: undefined;
};

export type DoctorsStackParamList = {
  DoctorDirectory: undefined;
  DoctorDetail: { id: string };
  AddEditDoctor: { id?: string };
};

const Stack = createNativeStackNavigator<ProfileStackParamList & DoctorsStackParamList>();

function ProfileHeaderRight() {
  const navigation = useNavigation<NativeStackNavigationProp<ProfileStackParamList>>();

  return (
    <TouchableOpacity onPress={() => navigation.navigate("Settings")}>
      <Text style={{ color: "#0f766e", fontSize: 13, fontWeight: "600" }}>Settings</Text>
    </TouchableOpacity>
  );
}

export default function ProfileStack() {
  return (
    <Stack.Navigator>
      <Stack.Screen
        name="ProfileHome"
        component={ProfileScreen}
        options={{ title: "Profile", headerRight: () => <ProfileHeaderRight /> }}
      />
      <Stack.Screen name="Settings" component={SettingsScreen} options={{ title: "Settings" }} />
      <Stack.Screen name="PinSetup" component={PinSetupScreen} options={{ title: "Change PIN", headerShown: false }} />
      <Stack.Screen name="ManageSessions" component={ManageSessionsScreen} options={{ title: "Manage devices" }} />
      <Stack.Screen name="AuditLog" component={AuditLogScreen} options={{ title: "Audit log" }} />
      <Stack.Screen
        name="Doctors"
        component={DoctorDirectoryScreen}
        options={{ title: "Doctors", headerShown: false }}
      />
      <Stack.Screen name="DoctorDetail" component={DoctorDetailScreen} options={{ title: "Doctor" }} />
      <Stack.Screen
        name="AddEditDoctor"
        component={AddEditDoctorScreen}
        options={({ route }) => ({ title: route.params?.id ? "Edit doctor" : "Add doctor" })}
      />
    </Stack.Navigator>
  );
}
