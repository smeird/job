import { verifyDatabaseSchema } from "@job/db";
import type { ReactNode } from "react";
import { WorkspaceShell } from "../../../components/WorkspaceShell.js";
import { requireUser } from "../../../lib/auth.js";
import { database } from "../../../lib/services.js";

/** Display a read-only production schema verification report. */
export default async function SchemaTestPage(): Promise<ReactNode> {
  const user = await requireUser();
  const result = await verifyDatabaseSchema(database());
  return <WorkspaceShell current="" email={user.email} title="Schema test"><div className="mx-auto max-w-3xl space-y-8"><header><p className="text-sm font-medium text-indigo-300">Operations</p><h1 className="mt-2 text-4xl font-semibold text-white">Database schema</h1><p className="mt-3 text-slate-400">Read-only verification against the TypeScript runtime contract.</p></header><section className="panel p-6"><p className={result.ok ? "text-lg font-semibold text-emerald-300" : "text-lg font-semibold text-rose-300"}>{result.ok ? "Schema is compatible" : "Schema changes are required"}</p>{result.missingTables.length === 0 ? null : <div className="mt-5"><h2 className="font-medium text-white">Missing tables</h2><ul className="mt-2 list-disc pl-5 text-sm text-slate-400">{result.missingTables.map((table) => <li key={table}>{table}</li>)}</ul></div>}{result.missingColumns.length === 0 ? null : <div className="mt-5"><h2 className="font-medium text-white">Missing columns</h2><ul className="mt-2 list-disc pl-5 text-sm text-slate-400">{result.missingColumns.map((column) => <li key={column}>{column}</li>)}</ul></div>}</section></div></WorkspaceShell>;
}
