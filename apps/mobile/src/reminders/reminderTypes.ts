import type { NotifyChannel, RecurrenceType, ReminderType } from "../api/types";

export const REMINDER_TYPES: { type: ReminderType; label: string }[] = [
  { type: "medication", label: "Medication" },
  { type: "appointment", label: "Appointment" },
  { type: "vaccination", label: "Vaccination" },
  { type: "annual_checkup", label: "Annual checkup" },
  { type: "lab_repeat", label: "Lab repeat" },
];

export const REMINDER_TYPE_LABELS: Record<ReminderType, string> = {
  medication: "Medication",
  appointment: "Appointment",
  vaccination: "Vaccination",
  annual_checkup: "Annual checkup",
  lab_repeat: "Lab repeat",
};

export const RECURRENCE_OPTIONS: { value: RecurrenceType | null; label: string }[] = [
  { value: null, label: "Never" },
  { value: "daily", label: "Daily" },
  { value: "weekly", label: "Weekly" },
  { value: "monthly", label: "Monthly" },
];

export const NOTIFY_CHANNELS: NotifyChannel[] = ["push", "sms", "whatsapp"];
