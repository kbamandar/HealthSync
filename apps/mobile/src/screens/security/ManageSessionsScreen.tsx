import { useCallback, useState } from "react";
import { ActivityIndicator, Alert, FlatList, StyleSheet, Text, TouchableOpacity, View } from "react-native";
import { useFocusEffect } from "@react-navigation/native";

import { sessionsApi } from "../../api/sessionsApi";
import type { Session } from "../../api/types";
import Badge from "../../components/Badge";

const PLATFORM_LABELS: Record<string, string> = { ios: "iOS", android: "Android", web: "Web" };

export default function ManageSessionsScreen() {
  const [sessions, setSessions] = useState<Session[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [revokingId, setRevokingId] = useState<string | null>(null);

  const load = useCallback(async () => {
    setIsLoading(true);
    try {
      setSessions(await sessionsApi.list());
    } finally {
      setIsLoading(false);
    }
  }, []);

  useFocusEffect(
    useCallback(() => {
      load();
    }, [load]),
  );

  function confirmRevoke(session: Session) {
    Alert.alert("Revoke this device?", "It will be signed out and will need to log in again.", [
      { text: "Cancel", style: "cancel" },
      {
        text: "Revoke",
        style: "destructive",
        onPress: async () => {
          setRevokingId(session.id);
          try {
            await sessionsApi.revoke(session.id);
            await load();
          } finally {
            setRevokingId(null);
          }
        },
      },
    ]);
  }

  if (isLoading) {
    return (
      <View style={styles.centered}>
        <ActivityIndicator />
      </View>
    );
  }

  return (
    <FlatList
      data={sessions}
      keyExtractor={(item) => item.id}
      contentContainerStyle={styles.list}
      ListHeaderComponent={<Text style={styles.helper}>These are the devices currently signed in to your account.</Text>}
      ListEmptyComponent={
        <View style={styles.centered}>
          <Text style={styles.emptyText}>No active sessions.</Text>
        </View>
      }
      renderItem={({ item }) => (
        <View style={styles.row}>
          <View style={styles.textCol}>
            <View style={styles.topRow}>
              <Text style={styles.deviceName}>
                {item.device_name ?? "Unknown device"}
                {item.platform ? ` · ${PLATFORM_LABELS[item.platform] ?? item.platform}` : ""}
              </Text>
              {item.is_current && <Badge label="This device" tone="success" />}
            </View>
            <Text style={styles.meta}>
              {item.is_current ? "Current session" : `Signed in ${new Date(item.created_at).toLocaleDateString()}`}
              {item.ip_address ? ` · ${item.ip_address}` : ""}
            </Text>
          </View>
          {!item.is_current && (
            <TouchableOpacity onPress={() => confirmRevoke(item)} disabled={revokingId === item.id}>
              {revokingId === item.id ? (
                <ActivityIndicator size="small" />
              ) : (
                <Text style={styles.revokeText}>Revoke</Text>
              )}
            </TouchableOpacity>
          )}
        </View>
      )}
    />
  );
}

const styles = StyleSheet.create({
  centered: { flex: 1, alignItems: "center", justifyContent: "center", padding: 24 },
  emptyText: { color: "#888" },
  list: { padding: 20, gap: 12, flexGrow: 1 },
  helper: { fontSize: 13, color: "#64748b", marginBottom: 12 },
  row: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    gap: 10,
    backgroundColor: "#fff",
    borderRadius: 12,
    padding: 14,
    marginBottom: 10,
  },
  textCol: { flex: 1, gap: 4 },
  topRow: { flexDirection: "row", alignItems: "center", gap: 8 },
  deviceName: { fontSize: 14, fontWeight: "700" },
  meta: { fontSize: 12, color: "#666" },
  revokeText: { color: "#dc2626", fontWeight: "600", fontSize: 13 },
});
