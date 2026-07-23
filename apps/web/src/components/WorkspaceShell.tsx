import Link from "next/link";
import type { ReactNode } from "react";
import { csrfToken } from "../lib/csrf.js";

const navigation = [
  ["/", "Dashboard"],
  ["/tailor", "Tailor"],
  ["/documents", "Documents"],
  ["/applications", "Applications"],
  ["/usage", "Usage"],
  ["/settings/models", "AI models"],
  ["/profile/contact-details", "Contact"],
  ["/retention", "Retention"],
] as const;

/** Frame authenticated screens with a compact professional navigation shell. */
export async function WorkspaceShell({ children, current, email, title }: { children: ReactNode; current: string; email: string; title: string }): Promise<ReactNode> {
  const token = await csrfToken();
  return (
    <div className="min-h-screen">
      <header className="border-b border-slate-800/70 bg-slate-950/75 backdrop-blur-xl">
        <div className="mx-auto flex max-w-7xl flex-col gap-4 px-5 py-5 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <Link href="/" className="text-lg font-semibold tracking-tight text-white">Job Tune</Link>
            <p className="mt-0.5 text-xs text-slate-500">{title} · {email}</p>
          </div>
          <div className="flex flex-col gap-3 lg:flex-row lg:items-center">
            <nav className="flex flex-wrap gap-1.5" aria-label="Workspace">
              {navigation.map(([href, label]) => (
                <Link
                  key={href}
                  href={href}
                  className={href === current
                    ? "rounded-lg border border-indigo-400/30 bg-indigo-500/15 px-3 py-2 text-sm font-medium text-indigo-100"
                    : "rounded-lg px-3 py-2 text-sm font-medium text-slate-400 transition hover:bg-slate-800/70 hover:text-slate-100"}
                >
                  {label}
                </Link>
              ))}
            </nav>
            <form method="post" action="/auth/logout">
              <input type="hidden" name="_token" value={token} />
              <button type="submit" className="button-secondary w-full lg:w-auto">Sign out</button>
            </form>
          </div>
        </div>
      </header>
      <main className="mx-auto max-w-7xl px-5 py-10">{children}</main>
    </div>
  );
}
