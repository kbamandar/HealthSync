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
