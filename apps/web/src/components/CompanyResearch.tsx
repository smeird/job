"use client";

import { useState, type ReactNode } from "react";

interface ResearchPayload {
  data?: { generated_at: string; search_results: Array<{ title: string; url: string }>; status: string; summary: string };
  message?: string;
  status: string;
}

/** Request and reveal cached or current company research for an owned application. */
export function CompanyResearch({ applicationId, csrf }: { applicationId: string; csrf: string }): ReactNode {
  const [result, setResult] = useState<ResearchPayload["data"]>();
  const [error, setError] = useState<string>();
  const [loading, setLoading] = useState(false);

  /** Call the existing research URL and keep provider errors out of the rendered page. */
  async function research(): Promise<void> {
    setLoading(true);
    setError(undefined);
    try {
      const response = await fetch(`/applications/${applicationId}/research`, { headers: { "x-csrf-token": csrf }, method: "POST" });
      const payload = await response.json() as ResearchPayload;
      if (!response.ok || payload.data === undefined) {
        throw new Error(payload.message ?? "Unable to complete company research at this time.");
      }
      setResult(payload.data);
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : "Unable to complete company research at this time.");
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="mt-4">
      <button className="text-xs font-medium text-indigo-300 hover:text-indigo-200" disabled={loading} onClick={() => void research()} type="button">{loading ? "Researching…" : "Research company"}</button>
      {error === undefined ? null : <p className="mt-3 text-xs text-rose-200">{error}</p>}
      {result === undefined ? null : <div className="mt-3 rounded-xl border border-slate-700 bg-slate-950/70 p-4"><p className="whitespace-pre-wrap text-sm leading-6 text-slate-300">{result.summary}</p>{result.search_results.length === 0 ? null : <div className="mt-4 flex flex-wrap gap-2">{result.search_results.map((source) => <a className="rounded-full border border-slate-700 px-3 py-1 text-xs text-slate-400 hover:text-white" href={source.url} key={source.url} rel="noopener noreferrer" target="_blank">{source.title}</a>)}</div>}<p className="mt-3 text-[0.68rem] text-slate-600">{result.status} · {new Date(result.generated_at).toLocaleString("en-GB")}</p></div>}
    </div>
  );
}
