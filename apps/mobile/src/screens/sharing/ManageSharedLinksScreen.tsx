import { useCallback, useState } from "react";
import { ActivityIndicator, Alert, FlatList, StyleSheet, Text, TouchableOpacity, View } from "react-native";
import { useFocusEffect } from "@react-navigation/native";

import { sharingApi } from "../../api/sharingApi";
import type { SharedLink } from "../../api/types";
import Badge from "../../components/Badge";

function isExpiringSoon(expiresAt: string): boolean {
  const daysLeft = (new Date(expiresAt).getTime() - Date.now()) / (1000 * 60 * 60 * 24);
  return daysLeft <= 2 && daysLeft >= 0;
}

export default function ManageSharedLinksScreen() {
  const [links, setLinks] = useState<SharedLink[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [revokingId, setRevokingId] = useState<string | null>(null);

  const load = useCallback(async () => {
    setIsLoading(true);
    try {
      setLinks(await sharingApi.list());
    } finally {
      setIsLoading(false);
    }
  }, []);

  useFocusEffect(
    useCallback(() => {
      load();
    }, [load]),
  );

  function confirmRevoke(link: SharedLink) {
    Alert.alert("Revoke this link?", "Anyone with the link will no longer be able to access it.", [
      { text: "Cancel", style: "cancel" },
      {
        text: "Revoke",
        style: "destructive",
        onPress: async () => {
          setRevokingId(link.id);
          try {
            await sharingApi.revoke(link.id);
            await load();
          } finally {
            setRevokingId(null);
          }
        },
      },
    ]);
  }

  const activeLinks = links.filter((l) => !l.is_revoked);

  if (isLoading) {
    return (
      <View style={styles.centered}>
        <ActivityIndicator />
      </View>
    );
  }

  return (
    <FlatList
      data={activeLinks}
      keyExtractor={(item) => item.id}
      contentContainerStyle={styles.list}
      ListEmptyComponent={
        <View style={styles.centered}>
          <Text style={styles.emptyText}>No active shared links.</Text>
        </View>
      }
      renderItem={({ item }) => (
        <View style={styles.row}>
          <View style={styles.textCol}>
            <Text style={styles.label}>{item.label}</Text>
            <Text style={styles.meta}>
              Viewed {item.access_count} {item.access_count === 1 ? "time" : "times"} &middot; expires{" "}
              {new Date(item.expires_at).toLocaleDateString()}
            </Text>
            {isExpiringSoon(item.expires_at) && (
              <View style={styles.badgeRow}>
                <Badge label="Expires soon" tone="warning" />
              </View>
            )}
          </View>
          <TouchableOpacity
            style={styles.revokeButton}
            onPress={() => confirmRevoke(item)}
            disabled={revokingId === item.id}
          >
            {revokingId === item.id ? (
              <ActivityIndicator size="small" />
            ) : (
              <Text style={styles.revokeText}>Revoke</Text>
            )}
          </TouchableOpacity>
        </View>
      )}
    />
  );
}

const styles = StyleSheet.create({
  centered: { flex: 1, alignItems: "center", justifyContent: "center", padding: 24 },
  emptyText: { color: "#888" },
  list: { padding: 20, gap: 12, flexGrow: 1 },
  row: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    backgroundColor: "#fff",
    borderRadius: 12,
    padding: 14,
    marginBottom: 12,
  },
  textCol: { flex: 1, gap: 4 },
  label: { fontSize: 14, fontWeight: "700" },
  meta: { fontSize: 12, color: "#666" },
  badgeRow: { marginTop: 2 },
  revokeButton: { borderWidth: 1, borderColor: "#ddd", borderRadius: 8, paddingVertical: 8, paddingHorizontal: 14 },
  revokeText: { fontWeight: "600" },
});
