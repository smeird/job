import type { ReactNode } from "react";
import { AuthCard } from "../../../components/AuthCard.js";
import { optionalUser } from "../../../lib/auth.js";
import { csrfToken } from "../../../lib/csrf.js";

/** Let an authenticated user rotate and reveal one-time recovery codes. */
export default async function BackupCodesPage(): Promise<ReactNode> {
  const user = await optionalUser();
  const token = await csrfToken();
  if (user === null) {
    return (
      <AuthCard title="Backup codes" subtitle="Sign in normally before generating or replacing recovery codes.">
        <a className="button-primary w-full" href="/auth/login">Return to sign in</a>
      </AuthCard>
    );
  }
  return (
    <AuthCard title="Backup codes" subtitle="Generating a new set immediately invalidates every previous backup code.">
      <form method="post" action="/auth/backup-codes">
        <input type="hidden" name="_token" value={token} />
        <button type="submit" className="button-primary w-full">Generate new backup codes</button>
      </form>
      <p className="mt-5 text-xs leading-5 text-slate-500">Save the next screen securely. The codes are never stored in readable form and cannot be shown again.</p>
    </AuthCard>
  );
}
