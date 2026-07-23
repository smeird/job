import Link from "next/link";
import type { ReactNode } from "react";
import { AuthCard } from "../../../components/AuthCard.js";
import { csrfToken } from "../../../lib/csrf.js";

/** Render the login challenge request form. */
export default async function LoginPage({ searchParams }: { searchParams: Promise<Record<string, string | string[] | undefined>> }): Promise<ReactNode> {
  const [token, query] = await Promise.all([csrfToken(), searchParams]);
  const email = typeof query.email === "string" ? query.email : "";
  const error = typeof query.error === "string" ? query.error : undefined;
  return (
    <AuthCard title="Sign in" subtitle="Use the authenticator already linked to your account." {...(error === undefined ? {} : { error })}>
      <form method="post" action="/auth/login" className="space-y-5">
        <input type="hidden" name="_token" value={token} />
        <label className="block text-sm font-medium text-slate-200">Email address<input className="field mt-2" type="email" name="email" defaultValue={email} autoComplete="email" required /></label>
        <button className="button-primary w-full" type="submit">Continue with authenticator</button>
      </form>
      <div className="mt-6 flex justify-between text-sm"><Link className="text-slate-400 hover:text-white" href="/auth/backup-code">Use a backup code</Link><Link className="text-indigo-300 hover:text-indigo-200" href="/auth/register">Create account</Link></div>
    </AuthCard>
  );
}
