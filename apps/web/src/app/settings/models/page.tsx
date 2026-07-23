import { ModelCatalogService } from "@job/core";
import type { ReactNode } from "react";
import { Alert } from "../../../components/Alert.js";
import { WorkspaceShell } from "../../../components/WorkspaceShell.js";
import { requireUser } from "../../../lib/auth.js";
import { csrfToken } from "../../../lib/csrf.js";
import { appConfig, repositories } from "../../../lib/services.js";

/** Render current account-visible OpenAI models and the two independent defaults. */
export default async function ModelSettingsPage({ searchParams }: { searchParams: Promise<Record<string, string | string[] | undefined>> }): Promise<ReactNode> {
  const [user, token, query] = await Promise.all([requireUser(), csrfToken(), searchParams]);
  const config = appConfig();
  const repository = repositories();
  const catalog = new ModelCatalogService(repository.settings, config.openai.apiKey, config.openai.baseUrl);
  const [models, planModel, draftModel, refreshedAt] = await Promise.all([
    catalog.models(),
    catalog.planningModel(config.openai.planModel),
    catalog.draftingModel(config.openai.draftModel),
    catalog.refreshedAt(),
  ]);
  const status = typeof query.status === "string" ? query.status : undefined;
  const error = typeof query.error === "string" ? query.error : undefined;
  return (
    <WorkspaceShell current="/settings/models" email={user.email} title="AI models">
      <div className="space-y-8">
        {status === undefined ? null : <Alert>{status}</Alert>}{error === undefined ? null : <Alert kind="error">{error}</Alert>}
        <header><p className="text-sm font-medium text-indigo-300">Settings</p><h1 className="mt-2 text-4xl font-semibold tracking-tight text-white">OpenAI models</h1><p className="mt-3 max-w-3xl text-slate-400">Choose one model for evidence analysis and another for final drafting. Refreshing reads the models available to the configured OpenAI project.</p></header>
        <div className="grid gap-6 lg:grid-cols-[1fr_21rem]">
          <form method="post" action="/settings/models" className="panel space-y-6 p-6">
            <input type="hidden" name="_token" value={token} />
            <label className="block text-sm font-medium text-slate-200">Analysis model<span className="mt-1 block font-normal text-slate-500">Maps the role requirements to evidence in the source CV.</span><select className="field mt-3" name="plan_model" defaultValue={planModel}>{models.map((model) => <option key={model.value} value={model.value}>{model.label} — {model.description}</option>)}</select></label>
            <label className="block text-sm font-medium text-slate-200">Default drafting model<span className="mt-1 block font-normal text-slate-500">Writes the tailored CV and cover letter from that evidence map.</span><select className="field mt-3" name="draft_model" defaultValue={draftModel}>{models.map((model) => <option key={model.value} value={model.value}>{model.label} — {model.description}</option>)}</select></label>
            <button className="button-primary" type="submit">Save model defaults</button>
          </form>
          <aside className="panel p-6"><h2 className="font-semibold text-white">Catalogue</h2><dl className="mt-5 space-y-4 text-sm"><div><dt className="text-slate-500">Selectable models</dt><dd className="mt-1 text-slate-200">{models.length}</dd></div><div><dt className="text-slate-500">Last refreshed</dt><dd className="mt-1 break-words text-slate-200">{refreshedAt ?? "Not yet refreshed"}</dd></div></dl><form method="post" action="/settings/models/refresh" className="mt-6"><input type="hidden" name="_token" value={token} /><button className="button-secondary w-full" type="submit">Refresh from OpenAI</button></form></aside>
        </div>
      </div>
    </WorkspaceShell>
  );
}
