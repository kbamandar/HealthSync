export interface Doctor {
  id: string;
  familyGroupId: string;
  name: string;
  speciality: string | null;
  hospitalClinic: string | null;
  phone: string | null;
  location: string | null;
  notes: string | null;
  createdAt: string;
  updatedAt: string;
}
