import { summarizeUsage } from "@job/core";
import { requestUser } from "../../../lib/auth.js";
import { repositories } from "../../../lib/services.js";

interface UsageTotalsJson {
  completion_tokens: number;
  cost_complete: boolean;
  cost_pence: number;
  prompt_tokens: number;
  total_tokens: number;
}

/** Convert internal camel-case aggregates to the established response field names. */
function totalJson(total: ReturnType<typeof summarizeUsage>["currentMonth"]): UsageTotalsJson {
  return { completion_tokens: total.completionTokens, cost_complete: total.costComplete, cost_pence: total.costPence, prompt_tokens: total.promptTokens, total_tokens: total.totalTokens };
}

/** Return the established per-run, totals, and monthly usage JSON contract. */
export async function GET(request: Request): Promise<Response> {
  const user = await requestUser(request);
  if (user === null) { return Response.json({ error: "Authentication required." }, { status: 401 }); }
  const records = await repositories().usage.listForUser(user.id);
  const summary = summarizeUsage(records);
  return Response.json({
    monthly: summary.monthly.map((month) => ({ ...totalJson(month), month: month.month })),
    per_run: records.map((record) => ({ completion_tokens: record.completionTokens, cost_available: record.costAvailable, cost_pence: record.costPence, created_at: record.createdAt.toISOString(), endpoint: record.endpoint, id: record.id.toString(), model: record.model, prompt_tokens: record.promptTokens, provider: record.provider, total_tokens: record.totalTokens })),
    totals: { current_month: totalJson(summary.currentMonth), lifetime: totalJson(summary.lifetime) },
  });
}
