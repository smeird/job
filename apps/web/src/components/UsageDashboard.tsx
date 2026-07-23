"use client";

import type { Options as HighchartsOptions } from "highcharts";
import type { CellComponent, TabulatorFull as TabulatorInstance } from "tabulator-tables";
import { useEffect, useRef, useState, type ReactNode } from "react";

interface UsagePayload {
  monthly: Array<{ cost_complete: boolean; cost_pence: number; month: string; total_tokens: number }>;
  per_run: Array<{ completion_tokens: number; cost_available: boolean; cost_pence: number | null; created_at: string; endpoint: string; id: string; model: string; prompt_tokens: number; provider: string; total_tokens: number }>;
  totals: {
    current_month: { completion_tokens: number; cost_complete: boolean; cost_pence: number; prompt_tokens: number; total_tokens: number };
    lifetime: { completion_tokens: number; cost_complete: boolean; cost_pence: number; prompt_tokens: number; total_tokens: number };
  };
}

/** Format pence as pounds while marking aggregates that contain unpriced models. */
function formatCost(pence: number, complete: boolean): string {
  return `${complete ? "" : "≥ "}£${(pence / 100).toFixed(2)}`;
}

/** Load the verified usage endpoint and render its table with Tabulator and trends with Highcharts. */
export function UsageDashboard(): ReactNode {
  const tableRef = useRef<HTMLDivElement>(null);
  const tokensRef = useRef<HTMLDivElement>(null);
  const costRef = useRef<HTMLDivElement>(null);
  const [payload, setPayload] = useState<UsagePayload | null>(null);
  const [error, setError] = useState(false);
  useEffect(() => {
    let active = true;
    let table: TabulatorInstance | undefined;
    let tokenChart: { destroy(): void } | undefined;
    let costChart: { destroy(): void } | undefined;
    void (async () => {
      try {
        const response = await fetch("/usage/data", { credentials: "same-origin" });
        if (!response.ok) { throw new Error("Unable to load usage."); }
        const data = await response.json() as UsagePayload;
        if (!active) { return; }
        setPayload(data);
        const [{ TabulatorFull }, Highcharts] = await Promise.all([import("tabulator-tables"), import("highcharts")]);
        if (!active || tableRef.current === null || tokensRef.current === null || costRef.current === null) { return; }
        table = new TabulatorFull(tableRef.current, {
          columns: [
            { field: "created_at", formatter: (cell: CellComponent) => new Date(String(cell.getValue())).toLocaleString("en-GB"), title: "Time", width: 165 },
            { field: "model", title: "Model" },
            { field: "endpoint", title: "Stage" },
            { field: "prompt_tokens", hozAlign: "right", title: "Input" },
            { field: "completion_tokens", hozAlign: "right", title: "Output" },
            { field: "total_tokens", hozAlign: "right", title: "Total" },
            { field: "cost_pence", formatter: (cell: CellComponent) => cell.getValue() === null ? "Not priced" : formatCost(Number(cell.getValue()), true), hozAlign: "right", title: "Cost" },
          ],
          data: data.per_run,
          layout: "fitColumns",
          pagination: true,
          paginationSize: 15,
          responsiveLayout: "collapse",
        });
        const common = { accessibility: { enabled: false }, credits: { enabled: false }, legend: { enabled: false }, title: { text: "" }, xAxis: { categories: data.monthly.map((item) => item.month.slice(0, 7)) } } satisfies HighchartsOptions;
        tokenChart = Highcharts.default.chart(tokensRef.current, { ...common, chart: { backgroundColor: "transparent", type: "column" }, series: [{ data: data.monthly.map((item) => item.total_tokens), name: "Tokens", type: "column" }], yAxis: { min: 0, title: { text: "Tokens" } } });
        costChart = Highcharts.default.chart(costRef.current, { ...common, chart: { backgroundColor: "transparent", type: "line" }, series: [{ data: data.monthly.map((item) => item.cost_pence / 100), name: "Cost", type: "line" }], tooltip: { valuePrefix: "£", valueDecimals: 2 }, yAxis: { min: 0, title: { text: "GBP" } } });
      } catch {
        if (active) { setError(true); }
      }
    })();
    return () => {
      active = false;
      table?.destroy();
      tokenChart?.destroy();
      costChart?.destroy();
    };
  }, []);

  if (error) { return <p className="rounded-xl border border-rose-400/20 bg-rose-950/20 p-4 text-sm text-rose-200">Usage could not be loaded.</p>; }
  if (payload === null) { return <p className="text-sm text-slate-500">Loading usage…</p>; }
  return (
    <div className="space-y-7">
      <div className="grid gap-4 md:grid-cols-2">
        {([
          ["This month", payload.totals.current_month],
          ["Lifetime", payload.totals.lifetime],
        ] as const).map(([label, total]) => (
          <article key={label} className="panel p-6">
            <p className="text-xs uppercase tracking-widest text-slate-500">{label}</p>
            <p className="mt-4 text-3xl font-semibold text-white">{formatCost(total.cost_pence, total.cost_complete)}</p>
            <p className="mt-3 text-sm text-slate-400">
              {total.total_tokens.toLocaleString("en-GB")} tokens · {total.prompt_tokens.toLocaleString("en-GB")} input · {total.completion_tokens.toLocaleString("en-GB")} output
            </p>
          </article>
        ))}
      </div>
      <section className="panel p-4"><div ref={tableRef} /></section>
      <div className="grid gap-5 lg:grid-cols-2"><section className="panel p-6"><h2 className="font-semibold text-white">Tokens by month</h2><div className="mt-4 h-72" ref={tokensRef} /></section><section className="panel p-6"><h2 className="font-semibold text-white">Cost by month</h2><div className="mt-4 h-72" ref={costRef} /></section></div>
    </div>
  );
}
