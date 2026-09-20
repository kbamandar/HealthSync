import type { VitalType } from "../api/types";

export interface VitalTypeOption {
  type: VitalType;
  label: string;
}

export interface VitalTab {
  key: string;
  label: string;
  isBloodPressure?: boolean;
  options: VitalTypeOption[];
}

export const VITAL_TABS: VitalTab[] = [
  {
    key: "bp",
    label: "Blood Pressure",
    isBloodPressure: true,
    options: [
      { type: "bp_systolic", label: "Systolic" },
      { type: "bp_diastolic", label: "Diastolic" },
    ],
  },
  {
    key: "sugar",
    label: "Sugar",
    options: [
      { type: "sugar_fasting", label: "Fasting" },
      { type: "sugar_pp", label: "Post-meal" },
      { type: "sugar_random", label: "Random" },
      { type: "hba1c", label: "HbA1c" },
    ],
  },
  {
    key: "weight",
    label: "Weight",
    options: [
      { type: "weight", label: "Weight" },
      { type: "bmi", label: "BMI" },
    ],
  },
  {
    key: "heart_rate",
    label: "Heart Rate",
    options: [{ type: "heart_rate", label: "Heart Rate" }],
  },
  {
    key: "spo2",
    label: "SpO2",
    options: [{ type: "spo2", label: "SpO2" }],
  },
  {
    key: "temperature",
    label: "Temp",
    options: [{ type: "temperature", label: "Temperature" }],
  },
];

export const RANGE_OPTIONS = [
  { label: "7d", days: 7 },
  { label: "30d", days: 30 },
  { label: "90d", days: 90 },
  { label: "365d", days: 365 },
];

export const ZONE_COLORS: Record<string, { bg: string; fg: string }> = {
  normal: { bg: "#dcfce7", fg: "#166534" },
  borderline: { bg: "#fef3c7", fg: "#92400e" },
  abnormal: { bg: "#fee2e2", fg: "#991b1b" },
};

// Mirrors VitalThresholdService's "normal" band on the API — used only to
// shade the trend chart client-side. The server's classification in each
// reading's `zone` field is always the source of truth for badges/alerts.
export const NORMAL_RANGES: Partial<Record<VitalType, [number, number]>> = {
  bp_systolic: [90, 120],
  bp_diastolic: [60, 80],
  sugar_fasting: [70, 100],
  sugar_pp: [0, 139],
  sugar_random: [0, 139],
  hba1c: [0, 5.6],
  bmi: [18.5, 24.9],
  heart_rate: [60, 100],
  spo2: [95, 100],
  temperature: [97, 99],
};
