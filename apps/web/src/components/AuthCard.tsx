import Link from "next/link";
import type { ReactNode } from "react";
import { Alert } from "./Alert.js";

/** Render the focused authentication shell shared by registration and login. */
export function AuthCard({ children, error, subtitle, title }: { children: ReactNode; error?: string; subtitle: string; title: string }): ReactNode {
  return (
    <main className="mx-auto flex min-h-screen w-full max-w-md items-center px-5 py-12">
      <section className="panel w-full p-7 sm:p-9">
        <Link href="/" className="text-sm font-semibold tracking-wide text-indigo-300">JOB TUNE</Link>
        <h1 className="mt-5 text-3xl font-semibold tracking-tight text-white">{title}</h1>
        <p className="mt-2 text-sm leading-6 text-slate-400">{subtitle}</p>
        {error === undefined || error === "" ? null : <div className="mt-5"><Alert kind="error">{error}</Alert></div>}
        <div className="mt-7">{children}</div>
      </section>
    </main>
  );
}
