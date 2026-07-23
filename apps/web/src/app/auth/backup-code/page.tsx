import Link from "next/link";
import type { ReactNode } from "react";
import { AuthCard } from "../../../components/AuthCard.js";
import { csrfToken } from "../../../lib/csrf.js";

/** Render the one-time recovery-code sign-in form for existing accounts. */
export default async function BackupCodeLoginPage({ searchParams }: { searchParams: Promise<Record<string, string | string[] | undefined>> }): Promise<ReactNode> {
  const [token, query] = await Promise.all([csrfToken(), searchParams]);
  const email = typeof query.email === "string" ? query.email : "";
  const error = typeof query.error === "string" ? query.error : undefined;
  return (
    <AuthCard title="Use a backup code" subtitle="Each recovery code can be used once." {...(error === undefined ? {} : { error })}>
      <form method="post" action="/auth/backup-code" className="space-y-5">
        <input type="hidden" name="_token" value={token} />
        <label className="block text-sm font-medium text-slate-200">Email address<input className="field mt-2" type="email" name="email" defaultValue={email} autoComplete="email" required /></label>
        <label className="block text-sm font-medium text-slate-200">Backup code<input className="field mt-2 font-mono uppercase tracking-wider" type="text" name="code" autoComplete="one-time-code" inputMode="text" required /></label>
        <button className="button-primary w-full" type="submit">Sign in</button>
      </form>
      <p className="mt-6 text-sm"><Link className="text-indigo-300 hover:text-indigo-200" href="/auth/login">Use your authenticator instead</Link></p>
    </AuthCard>
  );
}
