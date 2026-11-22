import { useState } from "react";
import { StyleSheet, Text, TouchableOpacity, View } from "react-native";

const KEYS: (string | null)[][] = [
  ["1", "2", "3"],
  ["4", "5", "6"],
  ["7", "8", "9"],
  [null, "0", "⌫"],
];

const PIN_LENGTH = 6;

interface PinPadScreenProps {
  title: string;
  subtitle: string;
  error?: string | null;
  onComplete: (pin: string) => void;
}

export default function PinPadScreen({ title, subtitle, error, onComplete }: PinPadScreenProps) {
  const [pin, setPin] = useState("");

  function handleKeyPress(key: string | null) {
    if (key === null) return;

    if (key === "⌫") {
      setPin((current) => current.slice(0, -1));
      return;
    }

    const next = (pin + key).slice(0, PIN_LENGTH);
    setPin(next);

    if (next.length === PIN_LENGTH) {
      onComplete(next);
      setPin("");
    }
  }

  return (
    <View style={styles.container}>
      <View style={styles.titleCol}>
        <Text style={styles.title}>{title}</Text>
        <Text style={styles.subtitle}>{error ?? subtitle}</Text>
      </View>

      <View style={styles.dotsRow}>
        {Array.from({ length: PIN_LENGTH }).map((_, index) => (
          <View key={index} style={[styles.dot, index < pin.length && styles.dotFilled]} />
        ))}
      </View>

      <View style={styles.keypad}>
        {KEYS.map((row, rowIndex) => (
          <View key={rowIndex} style={styles.keyRow}>
            {row.map((key, keyIndex) => (
              <TouchableOpacity
                key={keyIndex}
                style={styles.key}
                disabled={key === null}
                onPress={() => handleKeyPress(key)}
              >
                {key && <Text style={styles.keyText}>{key}</Text>}
              </TouchableOpacity>
            ))}
          </View>
        ))}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: "#f8fafc", alignItems: "center", paddingTop: 80, gap: 32 },
  titleCol: { alignItems: "center", gap: 6, paddingHorizontal: 32 },
  title: { fontSize: 18, fontWeight: "700", color: "#0f172a" },
  subtitle: { fontSize: 13, color: "#64748b", textAlign: "center" },
  dotsRow: { flexDirection: "row", gap: 16 },
  dot: { width: 14, height: 14, borderRadius: 7, borderWidth: 1.5, borderColor: "#cbd5e1", backgroundColor: "#fff" },
  dotFilled: { backgroundColor: "#0f766e", borderColor: "#0f766e" },
  keypad: { gap: 20 },
  keyRow: { flexDirection: "row", gap: 28 },
  key: { width: 64, height: 64, borderRadius: 32, alignItems: "center", justifyContent: "center" },
  keyText: { fontSize: 22, fontWeight: "500", color: "#0f172a" },
});
