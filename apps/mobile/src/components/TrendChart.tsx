import { StyleSheet, Text, View } from "react-native";
import Svg, { Circle, Polyline, Rect } from "react-native-svg";

interface TrendChartProps {
  points: { value: number; label: string }[];
  height?: number;
  normalRange?: [number, number];
}

const WIDTH = 311;

export default function TrendChart({ points, height = 100, normalRange }: TrendChartProps) {
  if (points.length === 0) {
    return (
      <View style={[styles.empty, { height }]}>
        <Text style={styles.emptyText}>No readings in this range yet.</Text>
      </View>
    );
  }

  const values = points.map((p) => p.value);
  const dataMin = Math.min(...values);
  const dataMax = Math.max(...values);
  const min = normalRange ? Math.min(dataMin, normalRange[0]) : dataMin;
  const max = normalRange ? Math.max(dataMax, normalRange[1]) : dataMax;
  const span = max - min || 1;
  const padding = 10;
  const plotHeight = height - padding * 2;

  function yFor(value: number) {
    return padding + plotHeight - ((value - min) / span) * plotHeight;
  }

  const step = points.length > 1 ? (WIDTH - 20) / (points.length - 1) : 0;
  const coords = points.map((p, i) => ({ x: 10 + i * step, y: yFor(p.value) }));

  return (
    <View>
      <Svg width={WIDTH} height={height}>
        {normalRange && (
          <Rect
            x={0}
            y={yFor(normalRange[1])}
            width={WIDTH}
            height={yFor(normalRange[0]) - yFor(normalRange[1])}
            fill="#dcfce7"
          />
        )}
        <Polyline
          points={coords.map((c) => `${c.x},${c.y}`).join(" ")}
          fill="none"
          stroke="#0f766e"
          strokeWidth={2.5}
        />
        {coords.map((c, i) => (
          <Circle key={i} cx={c.x} cy={c.y} r={3.5} fill="#0f766e" />
        ))}
      </Svg>
      <View style={styles.axisRow}>
        <Text style={styles.axisLabel}>{points[0].label}</Text>
        {points.length > 1 && <Text style={styles.axisLabel}>{points[points.length - 1].label}</Text>}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  empty: { alignItems: "center", justifyContent: "center" },
  emptyText: { color: "#888", fontSize: 13 },
  axisRow: { flexDirection: "row", justifyContent: "space-between", marginTop: 4 },
  axisLabel: { fontSize: 11, color: "#888" },
});
