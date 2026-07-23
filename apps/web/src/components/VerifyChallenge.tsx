import { buildTotpUri, totpQrDataUrl } from "@job/core";
import Image from "next/image";
import Link from "next/link";
import type { ReactNode } from "react";
import { AuthCard } from "./AuthCard.js";
import { csrfToken } from "../lib/csrf.js";
import { hasAuthChallenge } from "../lib/auth-challenge.js";
import { repositories } from "../lib/services.js";

/** Render a signed pending TOTP challenge and the shared six-digit verification form. */
export async function VerifyChallenge({ action, searchParams }: { action: "login" | "register"; searchParams: Promise<Record<string, string | string[] | undefined>> }): Promise<ReactNode> {
  const query = await searchParams;
  const email = typeof query.email === "string" ? query.email.trim().toLowerCase() : "";
  const error = typeof query.error === "string" ? query.error : undefined;
  const [token, ownsChallenge] = await Promise.all([csrfToken(), hasAuthChallenge(action, email)]);
  const pending = ownsChallenge && email !== "" ? await repositories().auth.findPendingPasscode(email, action) : null;
  const qr = pending?.totpSecret === null || pending === null
    ? null
    : await totpQrDataUrl(buildTotpUri(email, pending.totpSecret, pending.periodSeconds, pending.digits));
  const isRegistration = action === "register";
  return (
    <AuthCard
      title={isRegistration ? "Finish account setup" : "Verify and sign in"}
      subtitle={qr === null ? "Enter the six-digit code from your authenticator." : "Scan the QR code, then enter the current six-digit code."}
      {...(error === undefined ? {} : { error })}
    >
      {qr === null ? null : (
        <div className="mb-7 rounded-xl bg-white p-4 text-center">
          <Image className="mx-auto" src={qr} alt="Authenticator setup QR code" height={256} width={256} unoptimized />
          <p className="mt-3 break-all font-mono text-xs text-slate-700">Secret: {pending?.totpSecret}</p>
        </div>
      )}
      <form method="post" action={`/auth/${action}/verify`} className="space-y-5">
        <input type="hidden" name="_token" value={token} />
        <label className="block text-sm font-medium text-slate-200">Email address<input className="field mt-2" type="email" name="email" defaultValue={email} autoComplete="email" required /></label>
        <label className="block text-sm font-medium text-slate-200">Six-digit code<input className="field mt-2 font-mono tracking-[0.32em]" type="text" name="code" inputMode="numeric" pattern="[0-9 ]{6,9}" autoComplete="one-time-code" required /></label>
        <button className="button-primary w-full" type="submit">{isRegistration ? "Create account" : "Sign in"}</button>
      </form>
      <p className="mt-6 text-sm text-slate-400"><Link className="text-indigo-300 hover:text-indigo-200" href={`/auth/${action}?email=${encodeURIComponent(email)}`}>Request a new QR code</Link></p>
    </AuthCard>
  );
}
