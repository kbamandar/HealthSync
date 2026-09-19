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

export interface RecordFile {
  id: string;
  file_type: "pdf" | "jpg" | "png";
  mime_type: string | null;
  file_size_bytes: number | null;
  download_url: string;
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
