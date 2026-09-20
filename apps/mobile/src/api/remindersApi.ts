import { apiRequest } from "./client";
import type { NotifyChannel, RecurrenceType, Reminder, ReminderType } from "./types";

export interface ReminderFilters {
  member_id?: string;
  upcoming?: boolean;
}

export interface CreateReminderInput {
  member_id: string;
  reminder_type: ReminderType;
  title: string;
  description?: string;
  due_at: string;
  recurrence?: RecurrenceType;
  notify_via?: NotifyChannel[];
  linked_record_id?: string;
}

export interface UpdateReminderInput {
  title?: string;
  description?: string | null;
  due_at?: string;
  recurrence?: RecurrenceType | null;
  notify_via?: NotifyChannel[];
  is_active?: boolean;
}

function toQueryString(filters: ReminderFilters): string {
  const params = Object.entries(filters).filter(([, value]) => value !== undefined);
  if (params.length === 0) return "";
  return "?" + new URLSearchParams(params.map(([k, v]) => [k, String(v)])).toString();
}

export const remindersApi = {
  list(filters: ReminderFilters = {}) {
    return apiRequest<Reminder[]>(`/reminders${toQueryString(filters)}`);
  },

  create(input: CreateReminderInput) {
    return apiRequest<Reminder>("/reminders", { method: "POST", body: input });
  },

  update(id: string, input: UpdateReminderInput) {
    return apiRequest<Reminder>(`/reminders/${id}`, { method: "PUT", body: input });
  },

  remove(id: string) {
    return apiRequest<null>(`/reminders/${id}`, { method: "DELETE" });
  },
};
