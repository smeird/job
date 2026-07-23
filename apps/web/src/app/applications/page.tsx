import Link from "next/link";
import type { ReactNode } from "react";
import { Alert } from "../../components/Alert.js";
import { CompanyResearch } from "../../components/CompanyResearch.js";
import { WorkspaceShell } from "../../components/WorkspaceShell.js";
import { applicationStatuses, failureReasons } from "../../lib/applications.js";
import { requireUser } from "../../lib/auth.js";
import { csrfToken } from "../../lib/csrf.js";
import { repositories } from "../../lib/services.js";

/** Render the application pipeline with compact, professional cards and direct status controls. */
export default async function ApplicationsPage({ searchParams }: { searchParams: Promise<Record<string, string | string[] | undefined>> }): Promise<ReactNode> {
  const [user, token, query] = await Promise.all([requireUser(), csrfToken(), searchParams]);
  const repository = repositories();
  const [applications, generations] = await Promise.all([
    repository.applications.listForUser(user.id),
    repository.generations.listForUser(user.id, 500),
  ]);
  const generationIds = new Set(generations.map((generation) => generation.id.toString()));
  const statusMessage = typeof query.status === "string" ? query.status : undefined;
  return (
    <WorkspaceShell current="/applications" email={user.email} title="Applications">
      <div className="space-y-8">
        {statusMessage === undefined ? null : <Alert>{statusMessage}</Alert>}
        <header className="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between"><div><p className="text-sm font-medium text-indigo-300">Opportunity pipeline</p><h1 className="mt-2 text-4xl font-semibold tracking-tight text-white">Job tracker</h1><p className="mt-3 max-w-2xl text-slate-400">Track each opportunity from saved posting through interview and contract discussions.</p></div><Link className="button-primary" href="/applications/create">Add posting</Link></header>
        <div className="grid gap-5 xl:grid-cols-5">
          {applicationStatuses.map((column) => {
            const items = applications.filter((application) => application.status === column.value);
            return <section className="panel p-4" key={column.value}><header className="border-b border-slate-800 pb-4"><div className="flex items-center justify-between"><h2 className="font-semibold text-white">{column.label}</h2><span className="rounded-full bg-slate-800 px-2.5 py-1 text-xs text-slate-300">{items.length}</span></div><p className="mt-2 text-xs leading-5 text-slate-500">{column.description}</p></header><div className="mt-4 space-y-3">{items.length === 0 ? <p className="rounded-xl border border-dashed border-slate-800 p-5 text-center text-xs text-slate-600">No applications</p> : items.map((application) => <article className="rounded-xl border border-slate-800 bg-slate-950/50 p-4" key={application.id.toString()}><div className="flex items-start justify-between gap-3"><div><Link className="text-sm font-semibold text-slate-100 hover:text-indigo-200" href={`/applications/${application.id.toString()}`}>{application.title}</Link><p className="mt-2 text-xs text-slate-500">{application.appliedAt === null ? "Not submitted" : `Submitted ${application.appliedAt.toLocaleDateString("en-GB")}`}</p></div>{application.generationId !== null && generationIds.has(application.generationId.toString()) ? <a aria-label="Download linked CV" className="text-xs text-indigo-300" href={`/generations/${application.generationId.toString()}/download?artifact=cv&format=pdf`}>CV</a> : null}</div>{application.sourceUrl === null ? null : <a className="mt-3 block truncate text-xs text-slate-400 hover:text-white" href={application.sourceUrl} rel="noopener noreferrer" target="_blank">Original listing ↗</a>}<CompanyResearch applicationId={application.id.toString()} csrf={token} /><form action={`/applications/${application.id.toString()}/status`} className="mt-4 flex gap-2" method="post"><input name="_token" type="hidden" value={token} /><select aria-label="Status" className="field py-1.5 text-xs" defaultValue={application.status} name="status">{applicationStatuses.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}</select>{application.status === "failed" ? <input name="reason_code" type="hidden" value={application.reasonCode ?? "other"} /> : null}<button className="button-secondary px-3 py-1.5 text-xs" type="submit">Move</button></form>{application.status === "failed" && application.reasonCode !== null ? <p className="mt-2 text-xs text-rose-200">{failureReasons[application.reasonCode as keyof typeof failureReasons] ?? application.reasonCode}</p> : null}</article>)}</div></section>;
          })}
        </div>
      </div>
    </WorkspaceShell>
  );
}
