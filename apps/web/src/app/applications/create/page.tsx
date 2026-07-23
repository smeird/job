import Link from "next/link";
import type { ReactNode } from "react";
import { ApplicationForm } from "../../../components/ApplicationForm.js";
import { WorkspaceShell } from "../../../components/WorkspaceShell.js";
import { requireUser } from "../../../lib/auth.js";
import { csrfToken } from "../../../lib/csrf.js";

/** Render the standalone posting capture flow. */
export default async function CreateApplicationPage(): Promise<ReactNode> {
  const [user, token] = await Promise.all([requireUser(), csrfToken()]);
  return (
    <WorkspaceShell current="/applications" email={user.email} title="Add job posting">
      <div className="space-y-8"><header className="flex items-end justify-between gap-5"><div><p className="text-sm font-medium text-indigo-300">New opportunity</p><h1 className="mt-2 text-4xl font-semibold tracking-tight text-white">Capture the original role.</h1><p className="mt-3 max-w-2xl text-slate-400">Import a public advert or paste the description. You can review every field before anything is saved.</p></div><Link className="button-secondary" href="/applications">Back to tracker</Link></header><div className="grid gap-6 lg:grid-cols-[minmax(0,42rem)_1fr]"><ApplicationForm action="/applications" csrf={token} initial={{ description: "", sourceUrl: "", title: "" }} /><aside className="panel h-fit p-6"><h2 className="font-semibold text-white">What gets used</h2><p className="mt-3 text-sm leading-6 text-slate-400">The saved description becomes the role-side evidence for CV tailoring. Raw page HTML is discarded, and only extracted plain text is stored.</p></aside></div></div>
    </WorkspaceShell>
  );
}
