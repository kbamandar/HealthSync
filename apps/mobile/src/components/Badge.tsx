import { StyleSheet, Text, View } from "react-native";

interface BadgeProps {
  label: string;
  tone?: "neutral" | "warning" | "success";
}

const TONES = {
  neutral: { bg: "#f1f5f9", fg: "#475569" },
  warning: { bg: "#fef3c7", fg: "#92400e" },
  success: { bg: "#dcfce7", fg: "#166534" },
};

export default function Badge({ label, tone = "neutral" }: BadgeProps) {
  const colors = TONES[tone];

  return (
    <View style={[styles.badge, { backgroundColor: colors.bg }]}>
      <Text style={[styles.text, { color: colors.fg }]}>{label}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  badge: { borderRadius: 12, paddingVertical: 3, paddingHorizontal: 10 },
  text: { fontSize: 12, fontWeight: "600" },
});
