export type ReminderType =
  | "medication"
  | "appointment"
  | "vaccination"
  | "annual_checkup"
  | "lab_repeat";

export type RecurrenceType = "daily" | "weekly" | "monthly" | "custom";
export type NotifyChannel = "push" | "sms" | "whatsapp";

export interface Reminder {
  id: string;
  familyGroupId: string;
  memberId: string;
  createdById: string;
  reminderType: ReminderType;
  title: string;
  description: string | null;
  dueAt: string;
  recurrence: RecurrenceType | null;
  recurrenceRule: Record<string, unknown> | null;
  notifyVia: NotifyChannel[];
  isActive: boolean;
  lastSentAt: string | null;
  linkedRecordId: string | null;
  createdAt: string;
  updatedAt: string;
}
