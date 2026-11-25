import { useCallback, useState } from "react";
import { ActivityIndicator, Alert, ScrollView, StyleSheet, Switch, Text, TouchableOpacity, View } from "react-native";
import { useFocusEffect, useNavigation } from "@react-navigation/native";
import type { NativeStackNavigationProp } from "@react-navigation/native-stack";
import * as LocalAuthentication from "expo-local-authentication";

import { complianceApi } from "../../api/complianceApi";
import { useAuth } from "../../auth/AuthContext";
import type { ProfileStackParamList } from "../../navigation/ProfileStack";
import { AUTO_LOCK_TIMEOUT_OPTIONS, appLockStorage } from "../../security/appLockStorage";
import { useAppLock } from "../../security/AppLockContext";

export default function SettingsScreen() {
  const navigation = useNavigation<NativeStackNavigationProp<ProfileStackParamList>>();
  const { user, logout } = useAuth();
  const { refreshSettings } = useAppLock();

  const [biometricEnabled, setBiometricEnabled] = useState(false);
  const [autoLockSeconds, setAutoLockSeconds] = useState(0);
  const [isExporting, setIsExporting] = useState(false);
  const [isUpdatingDeletion, setIsUpdatingDeletion] = useState(false);

  const load = useCallback(async () => {
    const [biometric, timeout] = await Promise.all([
      appLockStorage.isBiometricEnabled(),
      appLockStorage.getAutoLockTimeoutSeconds(),
    ]);
    setBiometricEnabled(biometric);
    setAutoLockSeconds(timeout);
  }, []);

  useFocusEffect(
    useCallback(() => {
      load();
    }, [load]),
  );

  async function handleToggleBiometric(value: boolean) {
    if (value) {
      const [hardware, enrolled] = await Promise.all([
        LocalAuthentication.hasHardwareAsync(),
        LocalAuthentication.isEnrolledAsync(),
      ]);

      if (!hardware || !enrolled) {
        Alert.alert(
          "Biometrics unavailable",
          "Set up Face ID / fingerprint in your device settings first, or use a PIN instead.",
        );
        return;
      }
    }

    await appLockStorage.setBiometricEnabled(value);
    setBiometricEnabled(value);
    await refreshSettings();
  }

  function handleAutoLockTimeout() {
    Alert.alert(
      "Auto-lock timeout",
      "Lock HealthSync after the app has been in the background for:",
      AUTO_LOCK_TIMEOUT_OPTIONS.map((option) => ({
        text: option.label,
        onPress: async () => {
          await appLockStorage.setAutoLockTimeoutSeconds(option.value);
          setAutoLockSeconds(option.value);
        },
      })),
    );
  }

  async function handleExportData() {
    setIsExporting(true);
    try {
      const result = await complianceApi.requestDataExport();
      Alert.alert("Export requested", result.message);
    } finally {
      setIsExporting(false);
    }
  }

  function handleDeleteAccount() {
    Alert.alert(
      "Delete account?",
      "Your account will be permanently deleted after a 30-day grace period. You can cancel any time before then.",
      [
        { text: "Cancel", style: "cancel" },
        {
          text: "Delete account",
          style: "destructive",
          onPress: async () => {
            setIsUpdatingDeletion(true);
            try {
              await complianceApi.requestAccountDeletion();
              Alert.alert("Deletion requested", "Your account is scheduled for deletion in 30 days.");
            } finally {
              setIsUpdatingDeletion(false);
            }
          },
        },
      ],
    );
  }

  async function handleCancelDeletion() {
    setIsUpdatingDeletion(true);
    try {
      await complianceApi.cancelAccountDeletion();
      Alert.alert("Deletion cancelled", "Your account will not be deleted.");
    } finally {
      setIsUpdatingDeletion(false);
    }
  }

  const autoLockLabel = AUTO_LOCK_TIMEOUT_OPTIONS.find((o) => o.value === autoLockSeconds)?.label ?? "Immediately";
  const deletionPending = Boolean(user?.deletion_requested_at);

  return (
    <ScrollView contentContainerStyle={styles.container}>
      <View style={styles.section}>
        <Text style={styles.sectionLabel}>SECURITY</Text>
        <View style={styles.rows}>
          <View style={styles.row}>
            <View style={styles.textCol}>
              <Text style={styles.rowTitle}>Biometric lock</Text>
              <Text style={styles.rowSubtitle}>Use Face ID / fingerprint to unlock</Text>
            </View>
            <Switch value={biometricEnabled} onValueChange={handleToggleBiometric} />
          </View>

          <TouchableOpacity style={styles.row} onPress={() => navigation.navigate("PinSetup")}>
            <View style={styles.textCol}>
              <Text style={styles.rowTitle}>Change PIN</Text>
              <Text style={styles.rowSubtitle}>6-digit fallback PIN</Text>
            </View>
            <Text style={styles.chevron}>{"›"}</Text>
          </TouchableOpacity>

          <TouchableOpacity style={styles.row} onPress={handleAutoLockTimeout}>
            <View style={styles.textCol}>
              <Text style={styles.rowTitle}>Auto-lock timeout</Text>
              <Text style={styles.rowSubtitle}>{autoLockLabel}</Text>
            </View>
            <Text style={styles.chevron}>{"›"}</Text>
          </TouchableOpacity>

          <TouchableOpacity style={styles.row} onPress={() => navigation.navigate("ManageSessions")}>
            <View style={styles.textCol}>
              <Text style={styles.rowTitle}>Manage devices</Text>
              <Text style={styles.rowSubtitle}>View and revoke signed-in devices</Text>
            </View>
            <Text style={styles.chevron}>{"›"}</Text>
          </TouchableOpacity>
        </View>
      </View>

      <View style={styles.section}>
        <Text style={styles.sectionLabel}>NOTIFICATIONS</Text>
        <View style={styles.rows}>
          <View style={styles.row}>
            <View style={styles.textCol}>
              <Text style={styles.rowTitle}>Reminder notifications</Text>
              <Text style={styles.rowSubtitle}>Sent via email to {user?.email}</Text>
            </View>
          </View>
        </View>
      </View>

      <View style={styles.section}>
        <Text style={styles.sectionLabel}>PRIVACY & DATA</Text>
        <View style={styles.rows}>
          <TouchableOpacity style={styles.row} onPress={() => navigation.navigate("AuditLog")}>
            <View style={styles.textCol}>
              <Text style={styles.rowTitle}>View audit log</Text>
              <Text style={styles.rowSubtitle}>See recent account activity</Text>
            </View>
            <Text style={styles.chevron}>{"›"}</Text>
          </TouchableOpacity>

          <TouchableOpacity style={styles.row} onPress={handleExportData} disabled={isExporting}>
            <View style={styles.textCol}>
              <Text style={styles.rowTitle}>Export my data</Text>
              <Text style={styles.rowSubtitle}>Download a copy of your records as a ZIP</Text>
            </View>
            {isExporting ? <ActivityIndicator size="small" /> : <Text style={styles.chevron}>{"›"}</Text>}
          </TouchableOpacity>

          {deletionPending ? (
            <TouchableOpacity style={styles.row} onPress={handleCancelDeletion} disabled={isUpdatingDeletion}>
              <View style={styles.textCol}>
                <Text style={[styles.rowTitle, styles.danger]}>Cancel account deletion</Text>
                <Text style={styles.rowSubtitle}>Deletion requested — tap to cancel</Text>
              </View>
              {isUpdatingDeletion && <ActivityIndicator size="small" />}
            </TouchableOpacity>
          ) : (
            <TouchableOpacity style={styles.row} onPress={handleDeleteAccount} disabled={isUpdatingDeletion}>
              <View style={styles.textCol}>
                <Text style={[styles.rowTitle, styles.danger]}>Delete account</Text>
                <Text style={styles.rowSubtitle}>30-day grace period before permanent deletion</Text>
              </View>
              {isUpdatingDeletion ? <ActivityIndicator size="small" /> : <Text style={styles.chevron}>{"›"}</Text>}
            </TouchableOpacity>
          )}
        </View>
      </View>

      <TouchableOpacity style={styles.logoutButton} onPress={logout}>
        <Text style={styles.logoutText}>Log out</Text>
      </TouchableOpacity>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { padding: 20, gap: 24 },
  section: { gap: 8 },
  sectionLabel: { fontSize: 12, fontWeight: "700", color: "#64748b", letterSpacing: 0.5 },
  rows: { gap: 1 },
  row: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    backgroundColor: "#fff",
    borderRadius: 12,
    padding: 14,
    marginBottom: 8,
  },
  textCol: { flex: 1, gap: 2, marginRight: 12 },
  rowTitle: { fontSize: 14, fontWeight: "700", color: "#0f172a" },
  rowSubtitle: { fontSize: 12, color: "#64748b" },
  chevron: { fontSize: 18, fontWeight: "700", color: "#64748b" },
  danger: { color: "#dc2626" },
  logoutButton: { alignItems: "center", padding: 14, marginTop: 8 },
  logoutText: { color: "#dc2626", fontSize: 15, fontWeight: "600" },
});
