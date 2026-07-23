import type { ReactNode } from "react";
import { Alert } from "../../components/Alert.js";
import { WorkspaceShell } from "../../components/WorkspaceShell.js";
import { requireUser } from "../../lib/auth.js";
import { csrfToken } from "../../lib/csrf.js";
import { repositories } from "../../lib/services.js";

const resources = [
  ["documents", "Uploaded documents"],
  ["generation_outputs", "Generation outputs"],
  ["api_usage", "API usage metrics"],
  ["audit_logs", "Audit logs"],
] as const;

/** Render the shared retention policy without creating schema during the request. */
export default async function RetentionPage({ searchParams }: { searchParams: Promise<Record<string, string | string[] | undefined>> }): Promise<ReactNode> {
  const [user, token, query] = await Promise.all([requireUser(), csrfToken(), searchParams]);
  const policy = await repositories().settings.getRetentionSettings() ?? { applyTo: resources.map(([name]) => name), purgeAfterDays: 30 };
  const status = typeof query.status === "string" ? query.status : undefined;
  const error = typeof query.error === "string" ? query.error : undefined;
  return <WorkspaceShell current="" email={user.email} title="Retention"><div className="mx-auto max-w-3xl space-y-8">{status === undefined ? null : <Alert>{status}</Alert>}{error === undefined ? null : <Alert kind="error">{error}</Alert>}<header><p className="text-sm font-medium text-indigo-300">Data governance</p><h1 className="mt-2 text-4xl font-semibold text-white">Retention policy</h1><p className="mt-3 text-slate-400">Choose how long sensitive operational data remains before the scheduled purge command removes it.</p></header><form method="post" action="/retention" className="panel space-y-6 p-6"><input type="hidden" name="_token" value={token} /><label className="block text-sm font-medium text-slate-200">Purge after days<input className="field mt-2 max-w-48" type="number" name="purge_after_days" min="1" max="3650" defaultValue={policy.purgeAfterDays} required /></label><fieldset><legend className="text-sm font-medium text-slate-200">Apply policy to</legend><div className="mt-3 grid gap-3 sm:grid-cols-2">{resources.map(([name, label]) => <label key={name} className="flex items-center gap-3 rounded-xl border border-slate-800 px-4 py-3 text-sm text-slate-300"><input type="checkbox" name="apply_to" value={name} defaultChecked={policy.applyTo.includes(name)} />{label}</label>)}</div></fieldset><button className="button-primary" type="submit">Save retention policy</button></form></div></WorkspaceShell>;
}
