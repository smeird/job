import { parsePublicId } from "@job/db";
import Link from "next/link";
import { notFound } from "next/navigation";
import type { ReactNode } from "react";
import { MarkdownView } from "../../../../../../components/MarkdownView.js";
import { WorkspaceShell } from "../../../../../../components/WorkspaceShell.js";
import { requireUser } from "../../../../../../lib/auth.js";
import { repositories } from "../../../../../../lib/services.js";

/** Normalize the legacy URL segment to the artifact key stored in generation_outputs. */
function artifactKey(value: string): "cv" | "cover_letter" | null {
  const normalized = value.replace(/-/g, "_");
  return normalized === "cv" || normalized === "cover_letter" ? normalized : null;
}

/** Render an ownership-checked generated Markdown document through the restricted tree. */
export default async function TailoredMarkdownPage({ params }: { params: Promise<{ artifact: string; id: string }> }): Promise<ReactNode> {
  const [user, route] = await Promise.all([requireUser(), params]);
  let id: bigint;
  try { id = parsePublicId(route.id); } catch { notFound(); }
  const artifact = artifactKey(route.artifact);
  if (artifact === null) { notFound(); }
  const [generation, output] = await Promise.all([
    repositories().generations.findOwned(id, user.id),
    repositories().generations.findOutputOwned(id, user.id, artifact, "text/markdown"),
  ]);
  if (generation === null || output?.outputText === null || output === null) { notFound(); }
  const label = artifact === "cv" ? "Tailored CV" : "Cover letter";
  return (
    <WorkspaceShell current="/documents" email={user.email} title={`${label} preview`}>
      <div className="space-y-6">
        <Link className="text-sm text-indigo-300 hover:text-indigo-200" href="/documents">← Back to documents</Link>
        <header className="panel p-6"><p className="text-xs uppercase tracking-widest text-slate-500">Generation #{id.toString()}</p><h1 className="mt-3 text-3xl font-semibold text-white">{label}</h1><p className="mt-2 text-sm text-slate-500">{generation.model} · {generation.createdAt.toLocaleString("en-GB")}</p><div className="mt-5 flex flex-wrap gap-2">{["md", "docx", "pdf"].map((format) => <a key={format} className="button-secondary" href={`/generations/${id.toString()}/download?artifact=${artifact}&format=${format}`}>Download {format.toUpperCase()}</a>)}</div></header>
        <article className="panel p-7 sm:p-10"><MarkdownView markdown={output.outputText} /></article>
      </div>
    </WorkspaceShell>
  );
}
