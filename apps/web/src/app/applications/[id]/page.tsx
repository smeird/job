import { ModelCatalogService, TAILOR_PROMPT } from "@job/core";
import { parsePublicId } from "@job/db";
import Link from "next/link";
import { notFound } from "next/navigation";
import type { ReactNode } from "react";
import { Alert } from "../../../components/Alert.js";
import { ApplicationForm } from "../../../components/ApplicationForm.js";
import { WorkspaceShell } from "../../../components/WorkspaceShell.js";
import { applicationStatuses, failureReasons } from "../../../lib/applications.js";
import { requireUser } from "../../../lib/auth.js";
import { csrfToken } from "../../../lib/csrf.js";
import { appConfig, repositories } from "../../../lib/services.js";

/** Render editing, tailoring, status, and output-link controls for one owned application. */
export default async function EditApplicationPage({ params, searchParams }: { params: Promise<{ id: string }>; searchParams: Promise<Record<string, string | string[] | undefined>> }): Promise<ReactNode> {
  const [user, token, route, query] = await Promise.all([requireUser(), csrfToken(), params, searchParams]);
  let applicationId: bigint;
  try { applicationId = parsePublicId(route.id, "application id"); } catch { notFound(); }
  const repository = repositories();
  const config = appConfig();
  const catalog = new ModelCatalogService(repository.settings, config.openai.apiKey, config.openai.baseUrl);
  const [application, cvs, generations, models, defaultModel] = await Promise.all([
    repository.applications.findOwned(applicationId, user.id),
    repository.documents.listForUser(user.id, "cv"),
    repository.generations.listForUser(user.id, 500),
    catalog.models(),
    catalog.draftingModel(config.openai.draftModel),
  ]);
  if (application === null) { notFound(); }
  const status = typeof query.status === "string" ? query.status : undefined;
  return (
    <WorkspaceShell current="/applications" email={user.email} title={application.title}>
      <div className="space-y-8">
        {status === undefined ? null : <Alert>{status}</Alert>}
        <header className="flex items-end justify-between gap-5"><div><p className="text-sm font-medium text-indigo-300">Saved opportunity</p><h1 className="mt-2 text-4xl font-semibold tracking-tight text-white">{application.title}</h1><p className="mt-3 text-slate-400">Last updated {application.updatedAt.toLocaleString("en-GB")}</p></div><Link className="button-secondary" href="/applications">Back to tracker</Link></header>
        <div className="grid gap-6 xl:grid-cols-[minmax(0,42rem)_1fr]">
          <div className="space-y-6"><ApplicationForm action={`/applications/${application.id.toString()}`} csrf={token} initial={{ description: application.description, sourceUrl: application.sourceUrl ?? "", title: application.title }} /><form action={`/applications/${application.id.toString()}/status`} className="panel space-y-4 p-6" method="post"><input name="_token" type="hidden" value={token} /><h2 className="font-semibold text-white">Pipeline status</h2><select className="field" defaultValue={application.status} name="status">{applicationStatuses.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}</select><select className="field" defaultValue={application.reasonCode ?? ""} name="reason_code"><option value="">Rejection reason (only used for Learning)</option>{Object.entries(failureReasons).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select><button className="button-secondary" type="submit">Update status</button></form></div>
          <div className="space-y-6"><form action={`/applications/${application.id.toString()}/tailor`} className="panel space-y-4 p-6" method="post"><input name="_token" type="hidden" value={token} /><div><h2 className="font-semibold text-white">Tailor for this role</h2><p className="mt-2 text-sm text-slate-500">The evidence-first TypeScript worker will draft a CV and cover letter.</p></div><label className="block text-sm text-slate-300">Master CV<select className="field mt-2" name="cv_document_id" required><option value="">Select a CV</option>{cvs.map((document) => <option key={document.id.toString()} value={document.id.toString()}>{document.filename}</option>)}</select></label><label className="block text-sm text-slate-300">Drafting model<select className="field mt-2" defaultValue={defaultModel} name="model">{models.map((model) => <option key={model.value} value={model.value}>{model.label}</option>)}</select></label><label className="block text-sm text-slate-300">Analysis depth<select className="field mt-2" defaultValue="30" name="thinking_time"><option value="15">Focused</option><option value="30">Balanced</option><option value="50">Thorough</option></select></label><details><summary className="cursor-pointer text-sm text-slate-400">Advanced prompt</summary><textarea className="field mt-3 min-h-48" defaultValue={TAILOR_PROMPT} name="prompt" /></details><button className="button-primary" disabled={cvs.length === 0} type="submit">Queue tailored documents</button></form><form action={`/applications/${application.id.toString()}/generation`} className="panel space-y-4 p-6" method="post"><input name="_token" type="hidden" value={token} /><h2 className="font-semibold text-white">Linked tailored run</h2><select className="field" defaultValue={application.generationId?.toString() ?? ""} name="generation_id"><option value="">No linked run</option>{generations.map((generation) => <option key={generation.id.toString()} value={generation.id.toString()}>#{generation.id.toString()} · {generation.jobFilename ?? "Role"} · {generation.status}</option>)}</select><button className="button-secondary" type="submit">Save link</button>{application.generationId === null ? null : <div className="flex gap-2"><a className="button-secondary" href={`/generations/${application.generationId.toString()}/download?artifact=cv&format=pdf`}>CV PDF</a><a className="button-secondary" href={`/generations/${application.generationId.toString()}/download?artifact=cover_letter&format=pdf`}>Letter PDF</a></div>}</form><form action={`/applications/${application.id.toString()}/delete`} className="panel p-6" method="post"><input name="_token" type="hidden" value={token} /><button className="button-danger" type="submit">Delete application</button></form></div>
        </div>
      </div>
    </WorkspaceShell>
  );
}
