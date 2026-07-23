"use client";

import { useState, type ReactNode } from "react";

/** Render the shared application editor with a safe server-side URL importer. */
export function ApplicationForm({ action, csrf, initial }: {
  action: string;
  csrf: string;
  initial: { description: string; sourceUrl: string; title: string };
}): ReactNode {
  const [description, setDescription] = useState(initial.description);
  const [sourceUrl, setSourceUrl] = useState(initial.sourceUrl);
  const [title, setTitle] = useState(initial.title);
  const [importState, setImportState] = useState<string>("");

  /** Import the advert through the authenticated SSRF-protected route. */
  async function importPosting(): Promise<void> {
    setImportState("Importing…");
    const body = new FormData();
    body.set("_token", csrf);
    body.set("source_url", sourceUrl);
    try {
      const response = await fetch("/applications/fetch-description", { body, method: "POST" });
      const payload = await response.json() as { data?: { description?: string; source_url?: string; title?: string }; message?: string; status?: string };
      if (!response.ok || payload.status !== "ok" || payload.data === undefined) {
        throw new Error(payload.message ?? "Unable to import that posting.");
      }
      setDescription(payload.data.description ?? "");
      setSourceUrl(payload.data.source_url ?? sourceUrl);
      if (title.trim() === "") {
        setTitle(payload.data.title ?? "");
      }
      setImportState("Imported. Review the text before saving.");
    } catch (error) {
      setImportState(error instanceof Error ? error.message : "Unable to import that posting.");
    }
  }

  return (
    <form action={action} className="panel space-y-5 p-6" method="post">
      <input name="_token" type="hidden" value={csrf} />
      <label className="block text-sm font-medium text-slate-200">Role title<input className="field mt-2" name="title" onChange={(event) => setTitle(event.target.value)} placeholder="e.g. Senior Automation Engineer" value={title} /></label>
      <label className="block text-sm font-medium text-slate-200">Source URL<span className="mt-2 flex gap-2"><input className="field" name="source_url" onChange={(event) => setSourceUrl(event.target.value)} placeholder="https://" type="url" value={sourceUrl} /><button className="button-secondary shrink-0" onClick={() => void importPosting()} type="button">Import</button></span>{importState === "" ? null : <span aria-live="polite" className="mt-2 block text-xs text-indigo-200">{importState}</span>}</label>
      <label className="block text-sm font-medium text-slate-200">Job description<textarea className="field mt-2 min-h-80 resize-y" name="description" onChange={(event) => setDescription(event.target.value)} required value={description} /></label>
      <button className="button-primary" type="submit">Save posting</button>
    </form>
  );
}
