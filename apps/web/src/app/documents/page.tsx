import Link from "next/link";
import type { DocumentSummary } from "@job/db";
import type { ReactNode } from "react";
import { Alert } from "../../components/Alert.js";
import { WorkspaceShell } from "../../components/WorkspaceShell.js";
import { requireUser } from "../../lib/auth.js";
import { csrfToken } from "../../lib/csrf.js";
import { repositories } from "../../lib/services.js";

/** Format stored byte counts without converting bigint identifiers or sizes through floating-point arithmetic. */
function formatBytes(bytes: bigint): string {
  if (bytes < 1_024n) {
    return `${bytes.toString()} B`;
  }
  return `${(Number(bytes) / 1_024).toFixed(bytes < 10_240n ? 1 : 0)} KiB`;
}

/** Render a compact source-document list with ownership-scoped actions. */
function DocumentList({ documents, token }: { documents: DocumentSummary[]; token: string }): ReactNode {
  if (documents.length === 0) {
    return <p className="py-7 text-sm text-slate-500">Nothing uploaded yet.</p>;
  }
  return (
    <div className="divide-y divide-slate-800/80">
      {documents.map((document) => (
        <article key={document.id.toString()} className="grid gap-3 py-4 sm:grid-cols-[1fr_auto] sm:items-center">
          <div><Link className="font-medium text-slate-100 hover:text-indigo-200" href={`/documents/${document.id.toString()}`}>{document.filename}</Link><p className="mt-1 text-xs text-slate-500">{formatBytes(document.sizeBytes)} · {document.createdAt.toLocaleString("en-GB")}</p></div>
          <div className="flex flex-wrap gap-2">
            <a className="button-secondary" href={`/documents/${document.id.toString()}/download`}>Download</a>
            <form method="post" action={`/documents/${document.id.toString()}/delete`}><input type="hidden" name="_token" value={token} /><button className="button-danger" type="submit">Delete</button></form>
          </div>
        </article>
      ))}
    </div>
  );
}

/** Render uploaded source files and every tailoring run in one workspace. */
export default async function DocumentsPage({ searchParams }: { searchParams: Promise<Record<string, string | string[] | undefined>> }): Promise<ReactNode> {
  const [user, token, query] = await Promise.all([requireUser(), csrfToken(), searchParams]);
  const repository = repositories();
  const [cvs, jobs, generations] = await Promise.all([
    repository.documents.listForUser(user.id, "cv"),
    repository.documents.listForUser(user.id, "job_description"),
    repository.generations.listForUser(user.id),
  ]);
  const status = typeof query.status === "string" ? query.status : undefined;
  const error = typeof query.error === "string" ? query.error : undefined;
  return (
    <WorkspaceShell current="/documents" email={user.email} title="Documents">
      <div className="space-y-9">
        {status === undefined ? null : <Alert>{status}</Alert>}
        {error === undefined ? null : <Alert kind="error">{error}</Alert>}
        <header><p className="text-sm font-medium text-indigo-300">Source library</p><h1 className="mt-2 text-4xl font-semibold tracking-tight text-white">Documents</h1><p className="mt-3 max-w-2xl text-slate-400">Keep master CVs, job descriptions, and generated application files together. Uploads are limited to 1 MiB.</p></header>
        <section className="panel p-6">
          <h2 className="text-lg font-semibold text-white">Upload a source document</h2>
          <form className="mt-5 grid gap-4 md:grid-cols-[1fr_15rem_auto] md:items-end" method="post" action="/documents/upload" encType="multipart/form-data">
            <input type="hidden" name="_token" value={token} />
            <label className="text-sm font-medium text-slate-200">File<input className="field mt-2 pt-2" type="file" name="document" accept=".docx,.pdf,.md,.txt" required /></label>
            <label className="text-sm font-medium text-slate-200">Document type<select className="field mt-2" name="document_type" defaultValue="cv"><option value="cv">Master CV</option><option value="job_description">Job description</option></select></label>
            <button className="button-primary" type="submit">Upload</button>
          </form>
        </section>
        <div className="grid gap-6 lg:grid-cols-2">
          <section className="panel p-6"><h2 className="text-lg font-semibold text-white">Master CVs</h2><DocumentList documents={cvs} token={token} /></section>
          <section className="panel p-6"><h2 className="text-lg font-semibold text-white">Job descriptions</h2><DocumentList documents={jobs} token={token} /></section>
        </div>
        <section className="panel p-6">
          <div><h2 className="text-lg font-semibold text-white">Tailored application files</h2><p className="mt-1 text-sm text-slate-500">Completed runs include both the CV and cover letter.</p></div>
          <div className="mt-5 divide-y divide-slate-800/80">
            {generations.length === 0 ? <p className="py-7 text-sm text-slate-500">No tailoring runs yet.</p> : generations.map((generation) => (
              <article key={generation.id.toString()} className="grid gap-4 py-5 lg:grid-cols-[1fr_auto] lg:items-center">
                <div><p className="font-medium text-slate-100">{generation.jobFilename ?? "Job description"}</p><p className="mt-1 text-xs text-slate-500">Run #{generation.id.toString()} · {generation.model} · {generation.status} · {generation.createdAt.toLocaleString("en-GB")}</p></div>
                <div className="flex flex-wrap gap-2">
                  {generation.status === "completed" ? <><Link className="button-secondary" href={`/documents/tailored/${generation.id.toString()}/markdown/cv`}>Review CV</Link><a className="button-secondary" href={`/generations/${generation.id.toString()}/download?artifact=cv&format=pdf`}>CV PDF</a><a className="button-secondary" href={`/generations/${generation.id.toString()}/download?artifact=cover_letter&format=pdf`}>Letter PDF</a><form method="post" action={`/documents/tailored/${generation.id.toString()}/promote`}><input type="hidden" name="_token" value={token} /><button className="button-primary" type="submit">Save as master CV</button></form></> : null}
                  <form method="post" action={`/documents/tailored/${generation.id.toString()}/delete`}><input type="hidden" name="_token" value={token} /><button className="button-danger" type="submit">Delete</button></form>
                </div>
              </article>
            ))}
          </div>
        </section>
      </div>
    </WorkspaceShell>
  );
}
