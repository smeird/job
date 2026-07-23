import type { ReactNode } from "react";
import { UsageDashboard } from "../../components/UsageDashboard.js";
import { WorkspaceShell } from "../../components/WorkspaceShell.js";
import { requireUser } from "../../lib/auth.js";

/** Render verified token and tariff analytics. */
export default async function UsagePage(): Promise<ReactNode> {
  const user = await requireUser();
  return <WorkspaceShell current="/usage" email={user.email} title="Usage analytics"><div className="space-y-8"><header><p className="text-sm font-medium text-indigo-300">Insights</p><h1 className="mt-2 text-4xl font-semibold tracking-tight text-white">Usage analytics</h1><p className="mt-3 max-w-2xl text-slate-400">Every OpenAI request, its model, token volume, and precise configured cost. Unpriced models are clearly marked.</p></header><UsageDashboard /></div></WorkspaceShell>;
}
