export type Gender = "male" | "female" | "other" | "prefer_not_to_say";
export type BloodGroup = "A+" | "A-" | "B+" | "B-" | "AB+" | "AB-" | "O+" | "O-" | "unknown";

export interface User {
  id: string;
  mobile: string;
  email: string | null;
  name: string;
  dateOfBirth: string | null;
  gender: Gender | null;
  bloodGroup: BloodGroup | null;
  profilePhoto: string | null;
  abhaId: string | null;
  emergencyContactName: string | null;
  emergencyContactMobile: string | null;
  isActive: boolean;
  createdAt: string;
  updatedAt: string;
}
