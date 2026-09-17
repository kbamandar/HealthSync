export type VitalType =
  | "bp_systolic"
  | "bp_diastolic"
  | "sugar_fasting"
  | "sugar_pp"
  | "sugar_random"
  | "hba1c"
  | "weight"
  | "bmi"
  | "heart_rate"
  | "spo2"
  | "temperature";

export interface VitalReading {
  id: string;
  familyGroupId: string;
  memberId: string;
  loggedById: string;
  vitalType: VitalType;
  value: number;
  unit: string;
  readingContext: string | null;
  notes: string | null;
  recordedAt: string;
  isAbnormal: boolean;
  alertSent: boolean;
  createdAt: string;
}
