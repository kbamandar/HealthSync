export type RecordCategory =
  | "lab_report"
  | "radiology"
  | "prescription"
  | "discharge_summary"
  | "vaccination"
  | "chronic_condition"
  | "allergy"
  | "vital_signs"
  | "dental"
  | "eye"
  | "insurance"
  | "fitness_lifestyle"
  | "other";

export interface HealthRecord {
  id: string;
  familyGroupId: string;
  memberId: string;
  uploadedById: string;
  category: RecordCategory;
  title: string | null;
  recordDate: string | null;
  doctorName: string | null;
  hospitalClinic: string | null;
  notes: string | null;
  isFavourite: boolean;
  customTags: string[];
  fhirResourceType: string | null;
  fhirResourceId: string | null;
  isDeleted: boolean;
  deletedAt: string | null;
  version: number;
  parentRecordId: string | null;
  createdAt: string;
  updatedAt: string;
}

export type RecordFileType = "pdf" | "jpg" | "png";

export interface OcrTestValue {
  name: string;
  value: number;
  unit: string | null;
}

export interface OcrLabReportFields {
  recordDate: string | null;
  labName: string | null;
  testValues: OcrTestValue[];
}

export interface OcrMedicine {
  name: string;
  frequency: string;
  durationDays: number | null;
}

export interface OcrPrescriptionFields {
  doctorName: string | null;
  prescriptionDate: string | null;
  medicines: OcrMedicine[];
}

export interface OcrData {
  rawText: string | null;
  // Shape depends on the record's category: lab-report-style fields for
  // most categories, prescription fields when category is "prescription".
  fields: OcrLabReportFields | OcrPrescriptionFields;
}

export interface RecordFile {
  id: string;
  recordId: string;
  fileType: RecordFileType;
  s3Key: string;
  fileSizeBytes: number | null;
  mimeType: string | null;
  ocrExtracted: boolean;
  ocrData: OcrData | null;
  createdAt: string;
}
