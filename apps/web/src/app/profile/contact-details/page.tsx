import type { ReactNode } from "react";
import { Alert } from "../../../components/Alert.js";
import { WorkspaceShell } from "../../../components/WorkspaceShell.js";
import { requireUser } from "../../../lib/auth.js";
import { csrfToken } from "../../../lib/csrf.js";
import { repositories } from "../../../lib/services.js";

/** Render reusable cover-letter address, phone, and email fields. */
export default async function ContactDetailsPage({ searchParams }: { searchParams: Promise<Record<string, string | string[] | undefined>> }): Promise<ReactNode> {
  const [user, token, query] = await Promise.all([requireUser(), csrfToken(), searchParams]);
  const details = await repositories().contactDetails.findForUser(user.id);
  const status = typeof query.status === "string" ? query.status : undefined;
  const error = typeof query.error === "string" ? query.error : undefined;
  return <WorkspaceShell current="" email={user.email} title="Contact details"><div className="mx-auto max-w-3xl space-y-8">{status === undefined ? null : <Alert>{status}</Alert>}{error === undefined ? null : <Alert kind="error">{error}</Alert>}<header><p className="text-sm font-medium text-indigo-300">Profile</p><h1 className="mt-2 text-4xl font-semibold text-white">Cover-letter contact details</h1><p className="mt-3 text-slate-400">These details are optional and are used only when producing your cover letter.</p></header><form method="post" action="/profile/contact-details" className="panel space-y-5 p-6"><input type="hidden" name="_token" value={token} /><label className="block text-sm font-medium text-slate-200">Postal address<textarea className="field mt-2 min-h-32" name="address" defaultValue={details?.address ?? ""} required /></label><div className="grid gap-5 sm:grid-cols-2"><label className="text-sm font-medium text-slate-200">Phone<input className="field mt-2" name="phone" type="tel" defaultValue={details?.phone ?? ""} /></label><label className="text-sm font-medium text-slate-200">Email<input className="field mt-2" name="email" type="email" defaultValue={details?.email ?? ""} /></label></div><button className="button-primary" type="submit">Save contact details</button></form></div></WorkspaceShell>;
}
