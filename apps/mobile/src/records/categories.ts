import type { RecordCategory } from "../api/types";

export const CATEGORIES: RecordCategory[] = [
  "lab_report",
  "radiology",
  "prescription",
  "discharge_summary",
  "vaccination",
  "chronic_condition",
  "allergy",
  "vital_signs",
  "dental",
  "eye",
  "insurance",
  "fitness_lifestyle",
  "other",
];

export const CATEGORY_LABELS: Record<RecordCategory, string> = {
  lab_report: "Lab report",
  radiology: "Radiology",
  prescription: "Prescription",
  discharge_summary: "Discharge summary",
  vaccination: "Vaccination",
  chronic_condition: "Chronic condition",
  allergy: "Allergy",
  vital_signs: "Vital signs",
  dental: "Dental",
  eye: "Eye",
  insurance: "Insurance",
  fitness_lifestyle: "Fitness & lifestyle",
  other: "Other",
};
