import { StyleSheet, Text, View } from "react-native";
import Svg, { Circle } from "react-native-svg";

import type { HealthScoreStatus } from "../api/types";

interface HealthScoreGaugeProps {
  score: number;
  status: HealthScoreStatus;
  size?: number;
}

const RING_COLORS: Record<HealthScoreStatus, string> = {
  excellent: "#0f766e",
  good: "#0f766e",
  fair: "#d97706",
  needs_attention: "#dc2626",
};

const STROKE_WIDTH = 10;

export default function HealthScoreGauge({ score, status, size = 88 }: HealthScoreGaugeProps) {
  const radius = (size - STROKE_WIDTH) / 2;
  const circumference = 2 * Math.PI * radius;
  const progress = Math.max(0, Math.min(100, score)) / 100;
  const center = size / 2;

  return (
    <View style={{ width: size, height: size }}>
      <Svg width={size} height={size}>
        <Circle
          cx={center}
          cy={center}
          r={radius}
          stroke="#e2e8f0"
          strokeWidth={STROKE_WIDTH}
          fill="none"
        />
        <Circle
          cx={center}
          cy={center}
          r={radius}
          stroke={RING_COLORS[status]}
          strokeWidth={STROKE_WIDTH}
          strokeLinecap="round"
          strokeDasharray={`${circumference} ${circumference}`}
          strokeDashoffset={circumference * (1 - progress)}
          fill="none"
          // Rotate so the arc starts at 12 o'clock instead of SVG's default 3 o'clock.
          rotation={-90}
          origin={`${center}, ${center}`}
        />
      </Svg>
      <View style={[styles.scoreOverlay, { width: size, height: size }]}>
        <Text style={styles.scoreText}>{Math.round(score)}</Text>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  scoreOverlay: {
    position: "absolute",
    top: 0,
    left: 0,
    alignItems: "center",
    justifyContent: "center",
  },
  scoreText: { fontSize: 22, fontWeight: "700", color: "#0f172a" },
});
