import Link from "next/link";
import { notFound } from "next/navigation";
import type { ReactNode } from "react";
import { MarkdownView } from "../../../components/MarkdownView.js";
import { WorkspaceShell } from "../../../components/WorkspaceShell.js";
import { requireUser } from "../../../lib/auth.js";
import { extractStoredDocument, storedDocumentKind } from "../../../lib/documents.js";
import { repositories } from "../../../lib/services.js";
import { parsePublicId } from "@job/db";

/** Render an ownership-checked source document preview. */
export default async function DocumentPage({ params }: { params: Promise<{ id: string }> }): Promise<ReactNode> {
  const [user, route] = await Promise.all([requireUser(), params]);
  let id: bigint;
  try { id = parsePublicId(route.id); } catch { notFound(); }
  const document = await repositories().documents.findOwned(id, user.id);
  if (document === null) { notFound(); }
  const text = await extractStoredDocument(document);
  const markdown = storedDocumentKind(document) === "markdown";
  return (
    <WorkspaceShell current="/documents" email={user.email} title="Document preview">
      <div className="space-y-6">
        <Link className="text-sm text-indigo-300 hover:text-indigo-200" href="/documents">← Back to documents</Link>
        <header className="panel p-6"><p className="text-xs uppercase tracking-widest text-slate-500">{document.documentType === "cv" ? "Master CV" : "Job description"}</p><h1 className="mt-3 text-3xl font-semibold text-white">{document.filename}</h1><p className="mt-2 text-sm text-slate-500">{document.mimeType} · {document.sizeBytes.toString()} bytes</p><a className="button-secondary mt-5" href={`/documents/${document.id.toString()}/download`}>Download original</a></header>
        <section className="panel overflow-hidden p-6">{markdown ? <MarkdownView markdown={text} /> : <pre className="whitespace-pre-wrap break-words font-sans text-sm leading-7 text-slate-300">{text}</pre>}</section>
      </div>
    </WorkspaceShell>
  );
}
