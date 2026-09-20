import { useCallback, useState } from "react";
import { ActivityIndicator, FlatList, StyleSheet, Text, View } from "react-native";
import { useFocusEffect } from "@react-navigation/native";

import { complianceApi } from "../../api/complianceApi";
import type { AuditLogEntry } from "../../api/types";

const EVENT_LABELS: Record<string, string> = {
  "login.otp": "Signed in",
  "auth.logout": "Signed out",
  "session.revoke": "Revoked a device",
  "record.upload": "Uploaded a record",
  "record.view": "Viewed a record",
  "record.update": "Updated a record",
  "record.delete": "Deleted a record",
  "record.restore": "Restored a record",
  "link.create": "Created a shared link",
  "member.invite": "Invited a family member",
  "data_export.requested": "Requested a data export",
  "account_deletion.requested": "Requested account deletion",
  "account_deletion.cancelled": "Cancelled account deletion",
};

export default function AuditLogScreen() {
  const [events, setEvents] = useState<AuditLogEntry[]>([]);
  const [isLoading, setIsLoading] = useState(true);

  const load = useCallback(async () => {
    setIsLoading(true);
    try {
      setEvents(await complianceApi.auditLog());
    } finally {
      setIsLoading(false);
    }
  }, []);

  useFocusEffect(
    useCallback(() => {
      load();
    }, [load]),
  );

  if (isLoading) {
    return (
      <View style={styles.centered}>
        <ActivityIndicator />
      </View>
    );
  }

  return (
    <FlatList
      data={events}
      keyExtractor={(item) => String(item.id)}
      contentContainerStyle={styles.list}
      ListEmptyComponent={
        <View style={styles.centered}>
          <Text style={styles.emptyText}>No activity yet.</Text>
        </View>
      }
      renderItem={({ item }) => (
        <View style={styles.row}>
          <Text style={styles.eventLabel}>{EVENT_LABELS[item.event_type] ?? item.event_type}</Text>
          <Text style={styles.meta}>
            {new Date(item.created_at).toLocaleString()}
            {item.ip_address ? ` · ${item.ip_address}` : ""}
          </Text>
        </View>
      )}
    />
  );
}

const styles = StyleSheet.create({
  centered: { flex: 1, alignItems: "center", justifyContent: "center", padding: 24 },
  emptyText: { color: "#888" },
  list: { padding: 20, gap: 10, flexGrow: 1 },
  row: { backgroundColor: "#fff", borderRadius: 12, padding: 14, gap: 4, marginBottom: 10 },
  eventLabel: { fontSize: 14, fontWeight: "600", color: "#0f172a" },
  meta: { fontSize: 12, color: "#666" },
});
