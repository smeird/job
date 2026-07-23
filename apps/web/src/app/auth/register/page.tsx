import Link from "next/link";
import type { ReactNode } from "react";
import { AuthCard } from "../../../components/AuthCard.js";
import { csrfToken } from "../../../lib/csrf.js";

/** Render the new-account challenge request form. */
export default async function RegisterPage({ searchParams }: { searchParams: Promise<Record<string, string | string[] | undefined>> }): Promise<ReactNode> {
  const [token, query] = await Promise.all([csrfToken(), searchParams]);
  const email = typeof query.email === "string" ? query.email : "";
  const error = typeof query.error === "string" ? query.error : undefined;
  return (
    <AuthCard title="Create your account" subtitle="We will create a private authenticator seed; no password is stored." {...(error === undefined ? {} : { error })}>
      <form method="post" action="/auth/register" className="space-y-5">
        <input type="hidden" name="_token" value={token} />
        <label className="block text-sm font-medium text-slate-200">Email address<input className="field mt-2" type="email" name="email" defaultValue={email} autoComplete="email" required /></label>
        <button className="button-primary w-full" type="submit">Create authenticator setup</button>
      </form>
      <p className="mt-6 text-sm text-slate-400">Already registered? <Link className="text-indigo-300 hover:text-indigo-200" href="/auth/login">Sign in</Link></p>
    </AuthCard>
  );
}
