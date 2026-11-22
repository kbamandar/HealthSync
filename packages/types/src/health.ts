export type HealthScoreStatus = "excellent" | "good" | "fair" | "needs_attention";

export type HealthScoreFactorKey =
  | "annual_checkup"
  | "vitals_trend"
  | "medication_adherence"
  | "records_completeness";

export interface HealthScoreFactor {
  key: HealthScoreFactorKey;
  label: string;
  status: string;
  detail: string;
  weight: number;
}

export interface HealthScore {
  score: number;
  status: HealthScoreStatus;
  summary: string;
  factors: HealthScoreFactor[];
  computedAt: string;
}

export type HealthInsightType = "vital_trend" | "medication_overdue" | "annual_checkup_overdue";
export type HealthInsightSeverity = "info" | "warning";

export interface HealthInsight {
  id: string;
  type: HealthInsightType;
  severity: HealthInsightSeverity;
  title: string;
  message: string;
}
