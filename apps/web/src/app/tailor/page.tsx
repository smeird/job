import { ModelCatalogService, TAILOR_PROMPT } from "@job/core";
import Link from "next/link";
import type { ReactNode } from "react";
import { TailorForm } from "../../components/TailorForm.js";
import { WorkspaceShell } from "../../components/WorkspaceShell.js";
import { requireUser } from "../../lib/auth.js";
import { csrfToken } from "../../lib/csrf.js";
import { appConfig, repositories } from "../../lib/services.js";

/** Render the main evidence-led tailoring workflow and recent results. */
export default async function TailorPage(): Promise<ReactNode> {
  const [user, token] = await Promise.all([requireUser(), csrfToken()]);
  const repository = repositories();
  const config = appConfig();
  const catalog = new ModelCatalogService(repository.settings, config.openai.apiKey, config.openai.baseUrl);
  const [cvs, jobs, generations, models, defaultModel] = await Promise.all([
    repository.documents.listForUser(user.id, "cv"),
    repository.documents.listForUser(user.id, "job_description"),
    repository.generations.listForUser(user.id, 20),
    catalog.models(),
    catalog.draftingModel(config.openai.draftModel),
  ]);
  return (
    <WorkspaceShell current="/tailor" email={user.email} title="Tailor application">
      <div className="space-y-9">
        <header><p className="text-sm font-medium text-indigo-300">Evidence-led drafting</p><h1 className="mt-2 text-4xl font-semibold tracking-tight text-white">Tailor a CV to the role.</h1><p className="mt-3 max-w-3xl text-slate-400">The worker first maps requirements to evidence in your source CV, then drafts without inventing employers, qualifications, dates, or results.</p></header>
        <TailorForm csrf={token} cvDocuments={cvs.map((document) => ({ filename: document.filename, id: document.id.toString() }))} defaultModel={defaultModel} defaultPrompt={TAILOR_PROMPT} jobDocuments={jobs.map((document) => ({ filename: document.filename, id: document.id.toString() }))} models={models} />
        <section className="panel p-6"><div className="flex items-center justify-between"><div><h2 className="text-lg font-semibold text-white">Recent runs</h2><p className="mt-1 text-sm text-slate-500">Progress and permanent downloads for this account.</p></div><Link className="text-sm text-indigo-300" href="/documents">All files</Link></div><div className="mt-5 divide-y divide-slate-800/80">{generations.length === 0 ? <p className="py-7 text-sm text-slate-500">No runs yet.</p> : generations.map((generation) => <article key={generation.id.toString()} className="grid gap-3 py-4 sm:grid-cols-[1fr_auto] sm:items-center"><div><p className="font-medium text-slate-100">{generation.jobFilename}</p><p className="mt-1 text-xs text-slate-500">#{generation.id.toString()} · {generation.model} · {generation.status} · {generation.progressPercent}%</p></div>{generation.status === "completed" ? <div className="flex gap-2"><a className="button-secondary" href={`/generations/${generation.id.toString()}/download?artifact=cv&format=docx`}>CV DOCX</a><a className="button-secondary" href={`/generations/${generation.id.toString()}/download?artifact=cover_letter&format=pdf`}>Letter PDF</a></div> : null}</article>)}</div></section>
      </div>
    </WorkspaceShell>
  );
}
