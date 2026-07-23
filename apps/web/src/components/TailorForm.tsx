"use client";

import { useEffect, useRef, useState, type FormEvent, type ReactNode } from "react";
import type { ModelOption } from "@job/core";

interface DocumentOption { filename: string; id: string }

/** Run the compatibility generation request and follow its authenticated SSE progress stream. */
export function TailorForm({ csrf, cvDocuments, defaultModel, defaultPrompt, jobDocuments, models }: { csrf: string; cvDocuments: DocumentOption[]; defaultModel: string; defaultPrompt: string; jobDocuments: DocumentOption[]; models: ModelOption[] }): ReactNode {
  const [message, setMessage] = useState("");
  const [percent, setPercent] = useState(0);
  const [submitting, setSubmitting] = useState(false);
  const sourceRef = useRef<EventSource | null>(null);
  useEffect(() => () => sourceRef.current?.close(), []);

  /** Submit a tailoring run and bind the resulting id to its progress event stream. */
  async function submit(event: FormEvent<HTMLFormElement>): Promise<void> {
    event.preventDefault();
    setSubmitting(true);
    setPercent(0);
    setMessage("Queueing tailoring run…");
    const response = await fetch("/generations", { body: new FormData(event.currentTarget), credentials: "same-origin", method: "POST" });
    const payload = await response.json() as { error?: string; id?: string };
    if (!response.ok || payload.id === undefined) {
      setSubmitting(false);
      setMessage(payload.error ?? "The tailoring run could not be created.");
      return;
    }
    const source = new EventSource(`/generations/${encodeURIComponent(payload.id)}/stream`);
    sourceRef.current = source;
    source.addEventListener("status", (statusEvent) => {
      const data = JSON.parse((statusEvent as MessageEvent<string>).data) as { value: string };
      setMessage(data.value === "completed" ? "Your CV and cover letter are ready." : `Status: ${data.value}`);
      if (["completed", "failed", "cancelled"].includes(data.value)) {
        source.close();
        setSubmitting(false);
        if (data.value === "completed") { window.location.reload(); }
      }
    });
    source.addEventListener("progress", (progressEvent) => {
      const data = JSON.parse((progressEvent as MessageEvent<string>).data) as { percent: number };
      setPercent(data.percent);
    });
    source.addEventListener("error", (errorEvent) => {
      if (errorEvent instanceof MessageEvent && typeof errorEvent.data === "string" && errorEvent.data !== "") {
        const data = JSON.parse(errorEvent.data) as { message?: string };
        setMessage(data.message ?? "Generation failed.");
      }
    });
  }

  const unavailable = cvDocuments.length === 0 || jobDocuments.length === 0;
  return (
    <form className="panel space-y-6 p-6 sm:p-7" onSubmit={(event) => { void submit(event); }}>
      <input type="hidden" name="_token" value={csrf} />
      <div className="grid gap-5 md:grid-cols-2">
        <label className="text-sm font-medium text-slate-200">Job description<select className="field mt-2" name="job_document_id" required>{jobDocuments.map((document) => <option key={document.id} value={document.id}>{document.filename}</option>)}</select></label>
        <label className="text-sm font-medium text-slate-200">Master CV<select className="field mt-2" name="cv_document_id" required>{cvDocuments.map((document) => <option key={document.id} value={document.id}>{document.filename}</option>)}</select></label>
      </div>
      <div className="grid gap-5 md:grid-cols-2">
        <label className="text-sm font-medium text-slate-200">Drafting model<select className="field mt-2" name="model" defaultValue={defaultModel}>{models.map((model) => <option key={model.value} value={model.value}>{model.label} — {model.description}</option>)}</select></label>
        <label className="text-sm font-medium text-slate-200">Analysis depth<select className="field mt-2" name="thinking_time" defaultValue="30"><option value="10">Focused</option><option value="30">Thorough</option><option value="60">Deep</option></select></label>
      </div>
      <label className="block text-sm font-medium text-slate-200">Tailoring instructions<textarea className="field mt-2 min-h-48 resize-y leading-6" name="prompt" defaultValue={defaultPrompt} required /></label>
      {unavailable ? <p className="rounded-xl border border-amber-400/20 bg-amber-950/20 px-4 py-3 text-sm text-amber-200">Upload at least one master CV and one job description before starting.</p> : null}
      {message === "" ? null : <div aria-live="polite" className="space-y-2"><div className="h-1.5 overflow-hidden rounded-full bg-slate-800"><div className="h-full bg-indigo-500 transition-all" style={{ width: `${percent}%` }} /></div><p className="text-sm text-slate-400">{message} {percent > 0 ? `${percent}%` : ""}</p></div>}
      <button className="button-primary" type="submit" disabled={unavailable || submitting}>{submitting ? "Tailoring…" : "Tailor CV and cover letter"}</button>
    </form>
  );
}
