import { useState } from "react";
import { ActivityIndicator, ScrollView, StyleSheet, Text, TextInput, View } from "react-native";

import { searchApi } from "../api/searchApi";
import type { SearchResultGroup } from "../api/types";
import { CATEGORY_LABELS } from "../records/categories";

export default function SearchScreen() {
  const [query, setQuery] = useState("");
  const [groups, setGroups] = useState<SearchResultGroup[]>([]);
  const [isSearching, setIsSearching] = useState(false);
  const [hasSearched, setHasSearched] = useState(false);

  async function runSearch(text: string) {
    setQuery(text);
    if (text.trim().length < 2) {
      setGroups([]);
      setHasSearched(false);
      return;
    }
    setIsSearching(true);
    try {
      setGroups(await searchApi.search(text.trim()));
      setHasSearched(true);
    } finally {
      setIsSearching(false);
    }
  }

  return (
    <ScrollView contentContainerStyle={styles.container} keyboardShouldPersistTaps="handled">
      <TextInput
        style={styles.input}
        placeholder="Search your health records"
        value={query}
        onChangeText={runSearch}
        autoFocus
      />

      {isSearching && <ActivityIndicator style={styles.spinner} />}

      {!isSearching && hasSearched && groups.length === 0 && (
        <Text style={styles.emptyText}>No records match "{query}".</Text>
      )}

      {groups.map((group) => (
        <View key={group.category}>
          <Text style={styles.groupLabel}>{CATEGORY_LABELS[group.category]}</Text>
          {group.records.map((record) => (
            <View key={record.id} style={styles.resultRow}>
              <Text style={styles.resultTitle}>{record.title || "Untitled record"}</Text>
              {(record.notes || record.record_date) && (
                <Text style={styles.resultSubtitle} numberOfLines={1}>
                  {[record.notes, record.record_date].filter(Boolean).join(" · ")}
                </Text>
              )}
            </View>
          ))}
        </View>
      ))}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { padding: 20, gap: 10 },
  input: { borderWidth: 1, borderColor: "#ddd", borderRadius: 8, padding: 12, fontSize: 16 },
  spinner: { marginTop: 12 },
  emptyText: { color: "#888", marginTop: 12 },
  groupLabel: { fontSize: 13, fontWeight: "700", color: "#666", marginTop: 8, marginBottom: 8 },
  resultRow: { backgroundColor: "#fff", borderRadius: 10, padding: 12, gap: 4, marginBottom: 8 },
  resultTitle: { fontSize: 14, fontWeight: "700" },
  resultSubtitle: { fontSize: 12, color: "#666" },
});
