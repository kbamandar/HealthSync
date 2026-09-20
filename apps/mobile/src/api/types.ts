// Wire-format types matching the API's actual JSON (snake_case), as opposed
// to @healthsync/types' camelCase domain model. A mapping layer between the
// two can come later if/when it's worth the indirection.

export interface ApiSuccess<T> {
  success: true;
  data: T;
  meta?: { page?: number; per_page?: number; total?: number };
}

export interface ApiError {
  success: false;
  error: { code: string; message: string; details?: unknown };
}

export type ApiResult<T> = ApiSuccess<T> | ApiError;

export interface AuthTokens {
  access_token: string;
  refresh_token: string;
  expires_in: number;
}

export interface UserProfile {
  id: string;
  mobile: string;
  email: string;
  name: string | null;
  date_of_birth: string | null;
  gender: string | null;
  blood_group: string | null;
  profile_photo: string | null;
  emergency_contact_name: string | null;
  emergency_contact_mobile: string | null;
  profile_complete: boolean;
  deletion_requested_at: string | null;
}

export interface VerifyOtpResponse extends AuthTokens {
  user: UserProfile;
}

export type FamilyRelationship = "self" | "spouse" | "parent" | "child" | "sibling" | "other";
export type AccessLevel = "full_access" | "self_only" | "view_only";
export type InviteStatus = "pending" | "accepted" | "declined";

export interface FamilyMember {
  id: string;
  relationship: FamilyRelationship;
  custom_label: string | null;
  display_name: string;
  date_of_birth: string | null;
  gender: string | null;
  blood_group: string | null;
  is_guardian_managed: boolean;
  access_level: AccessLevel;
  invite_status: InviteStatus;
  invite_email: string | null;
  member_user_id: string | null;
  created_at: string;
}

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

export interface OcrTestValue {
  name: string;
  value: number;
  unit: string | null;
}

export interface OcrData {
  raw_text: string | null;
  fields: {
    record_date: string | null;
    lab_name: string | null;
    test_values: OcrTestValue[];
  };
}

export interface RecordFile {
  id: string;
  file_type: "pdf" | "jpg" | "png";
  mime_type: string | null;
  file_size_bytes: number | null;
  download_url: string;
  ocr_extracted: boolean;
  ocr_data: OcrData | null;
  created_at: string | null;
}

export interface HealthRecord {
  id: string;
  member_id: string;
  category: RecordCategory;
  title: string | null;
  record_date: string | null;
  doctor_name: string | null;
  hospital_clinic: string | null;
  notes: string | null;
  is_favourite: boolean;
  custom_tags: string[];
  deleted_at: string | null;
  created_at: string;
  // Only present on GET /records/:id and PUT /records/:id, not on list/recycle-bin rows.
  files?: RecordFile[];
}

export interface RecordUploadUrl {
  upload_url: string;
  s3_key: string;
  expires_in: number;
}

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

export type VitalZone = "normal" | "borderline" | "abnormal";

export interface VitalReading {
  id: string;
  member_id: string;
  vital_type: VitalType;
  value: number;
  unit: string;
  reading_context: string | null;
  notes: string | null;
  recorded_at: string;
  is_abnormal: boolean;
  zone: VitalZone;
}

export interface DashboardRecentRecord {
  id: string;
  category: RecordCategory;
  title: string | null;
  record_date: string | null;
  created_at: string;
}

export interface DashboardLatestVital {
  vital_type: VitalType;
  value: number;
  unit: string;
  zone: VitalZone;
  recorded_at: string;
}

export interface DashboardFamilySummary {
  member_id: string;
  display_name: string;
  relationship: FamilyRelationship;
  pending_count: number;
}

export interface DashboardUpcomingReminder {
  id: string;
  reminder_type: ReminderType;
  title: string;
  member_id: string;
  due_at: string;
}

export interface Dashboard {
  stats: {
    records_this_month: number;
    vitals_needing_attention: number;
    profile_complete_percent: number;
  };
  recent_records: DashboardRecentRecord[];
  latest_vitals: DashboardLatestVital[];
  family_summary: DashboardFamilySummary[];
  upcoming_reminders: DashboardUpcomingReminder[];
}

export type ReminderType = "medication" | "appointment" | "vaccination" | "annual_checkup" | "lab_repeat";
export type RecurrenceType = "daily" | "weekly" | "monthly" | "custom";
export type NotifyChannel = "push" | "sms" | "whatsapp" | "email";

export interface Reminder {
  id: string;
  member_id: string;
  reminder_type: ReminderType;
  title: string;
  description: string | null;
  due_at: string;
  recurrence: RecurrenceType | null;
  notify_via: NotifyChannel[];
  is_active: boolean;
  last_sent_at: string | null;
  linked_record_id: string | null;
  created_at: string;
}

export interface SearchResultGroup {
  category: RecordCategory;
  records: {
    id: string;
    title: string | null;
    notes: string | null;
    record_date: string | null;
  }[];
}

export interface TimelineEntry {
  id: string;
  kind: "record" | "vital";
  sub_type: string;
  title: string | null;
  value: number | null;
  unit: string | null;
  is_abnormal: boolean | null;
  event_date: string;
  member_id: string;
  member_name: string | null;
}

export type SharedLinkType = "record" | "health_summary";

export interface SharedLink {
  id: string;
  link_type: SharedLinkType;
  label: string;
  url: string;
  expires_at: string;
  access_count: number;
  max_access: number | null;
  is_revoked: boolean;
  created_at: string;
}

export interface SharedLinkWithQr extends SharedLink {
  qr_code_svg: string;
}

export interface PublicSharedRecord {
  title: string | null;
  category: RecordCategory;
  record_date: string | null;
  doctor_name: string | null;
  hospital_clinic: string | null;
  files: { file_type: "pdf" | "jpg" | "png"; download_url: string }[];
}

export type SessionPlatform = "ios" | "android" | "web";

export interface Session {
  id: string;
  device_name: string | null;
  platform: SessionPlatform | null;
  ip_address: string | null;
  is_current: boolean;
  created_at: string;
  expires_at: string;
}

export interface AuditLogEntry {
  id: number;
  event_type: string;
  resource_type: string | null;
  resource_id: string | null;
  ip_address: string | null;
  metadata: Record<string, unknown> | null;
  created_at: string;
}

export interface AccountDeletionStatus {
  deletion_requested_at: string;
  purge_at: string;
}

export interface Doctor {
  id: string;
  name: string;
  speciality: string | null;
  hospital_clinic: string | null;
  phone: string | null;
  location: string | null;
  notes: string | null;
  created_at: string;
}

export interface DoctorVisit {
  record_id: string;
  title: string | null;
  category: RecordCategory;
  record_date: string | null;
}
