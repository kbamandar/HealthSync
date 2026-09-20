import { StyleSheet, Text, View } from "react-native";

const COLORS = ["#0f766e", "#7c3aed", "#c2410c", "#0369a1", "#be123c", "#4d7c0f"];

function colorFor(name: string): string {
  const index = name.charCodeAt(0) % COLORS.length;
  return COLORS[index];
}

function initialsFor(name: string): string {
  const parts = name.trim().split(/\s+/);
  const first = parts[0]?.[0] ?? "?";
  const last = parts.length > 1 ? parts[parts.length - 1][0] : "";
  return (first + last).toUpperCase();
}

interface AvatarProps {
  name: string;
  size?: number;
}

export default function Avatar({ name, size = 44 }: AvatarProps) {
  return (
    <View
      style={[
        styles.circle,
        { width: size, height: size, borderRadius: size / 2, backgroundColor: colorFor(name) },
      ]}
    >
      <Text style={[styles.text, { fontSize: size * 0.4 }]}>{initialsFor(name)}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  circle: { alignItems: "center", justifyContent: "center" },
  text: { color: "#fff", fontWeight: "700" },
});
