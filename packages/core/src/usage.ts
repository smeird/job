import type { UsageRecord } from "@job/db";

export interface UsageTotals {
  completionTokens: number;
  costComplete: boolean;
  costPence: number;
  promptTokens: number;
  totalTokens: number;
}

export interface MonthlyUsage extends UsageTotals {
  month: string;
}

/** Create an empty totals accumulator with explicit pricing completeness. */
function emptyTotals(): UsageTotals {
  return { completionTokens: 0, costComplete: true, costPence: 0, promptTokens: 0, totalTokens: 0 };
}

/** Add one normalized usage record to an aggregate without treating unknown prices as free. */
function addRecord(total: UsageTotals, record: UsageRecord): void {
  total.promptTokens += record.promptTokens;
  total.completionTokens += record.completionTokens;
  total.totalTokens += record.totalTokens;
  if (record.costPence !== null) {
    total.costPence += record.costPence;
  }
  total.costComplete &&= record.costAvailable;
}

/** Build current-month, lifetime, and monthly aggregates from ownership-scoped usage rows. */
export function summarizeUsage(records: readonly UsageRecord[], now = new Date()): { currentMonth: UsageTotals; lifetime: UsageTotals; monthly: MonthlyUsage[] } {
  const currentMonth = emptyTotals();
  const lifetime = emptyTotals();
  const monthStart = new Date(now.getFullYear(), now.getMonth(), 1);
  const monthly = new Map<string, MonthlyUsage>();
  for (const record of records) {
    addRecord(lifetime, record);
    if (record.createdAt >= monthStart) {
      addRecord(currentMonth, record);
    }
    const month = `${record.createdAt.getFullYear()}-${String(record.createdAt.getMonth() + 1).padStart(2, "0")}-01`;
    const aggregate = monthly.get(month) ?? { ...emptyTotals(), month };
    addRecord(aggregate, record);
    monthly.set(month, aggregate);
  }
  return { currentMonth, lifetime, monthly: [...monthly.values()].sort((left, right) => left.month.localeCompare(right.month)) };
}
