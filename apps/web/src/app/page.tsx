import Link from "next/link";
import type { ReactNode } from "react";
import { Alert } from "../components/Alert.js";
import { WorkspaceShell } from "../components/WorkspaceShell.js";
import { optionalUser } from "../lib/auth.js";
import { repositories } from "../lib/services.js";

interface HomePageProps {
  searchParams: Promise<Record<string, string | string[] | undefined>>;
}

/** Return a single query-string value without reflecting arrays into the page. */
function queryValue(value: string | string[] | undefined): string | undefined {
  return typeof value === "string" ? value : undefined;
}

/** Render the public product introduction or the authenticated application dashboard. */
export default async function HomePage({ searchParams }: HomePageProps): Promise<ReactNode> {
  const user = await optionalUser(true);
  if (user === null) {
    return (
      <main className="mx-auto max-w-7xl px-5 py-7 sm:py-10">
        <header className="flex items-center justify-between border-b border-slate-800/70 pb-6">
          <div>
            <p className="text-lg font-semibold text-white">Job Tune</p>
            <p className="text-xs text-slate-500">job.smeird.com</p>
          </div>
          <div className="flex gap-2">
            <Link href="/auth/login" className="button-secondary">Sign in</Link>
            <Link href="/auth/register" className="button-primary">Create account</Link>
          </div>
        </header>
        <section className="grid min-h-[68vh] items-center gap-12 py-16 lg:grid-cols-[1.15fr_0.85fr]">
          <div>
            <p className="text-sm font-semibold uppercase tracking-[0.22em] text-indigo-300">Evidence-led CV tailoring</p>
            <h1 className="mt-5 max-w-3xl text-5xl font-semibold tracking-[-0.045em] text-white sm:text-6xl">A stronger application, grounded in what you have actually done.</h1>
            <p className="mt-7 max-w-2xl text-lg leading-8 text-slate-400">Match each role to the right master CV, prioritise credible evidence, and export a professional CV and cover letter without inventing experience.</p>
            <div className="mt-9 flex flex-wrap gap-3">
              <Link href="/auth/login" className="button-primary">Open your workspace</Link>
              <Link href="/auth/register" className="button-secondary">Create an account</Link>
            </div>
          </div>
          <aside className="panel p-7 sm:p-9">
            <p className="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Built for careful tailoring</p>
            <div className="mt-7 space-y-7">
              {[
                ["Traceable", "Employers, dates, achievements, and qualifications remain anchored to your source CV."],
                ["Role-specific", "The evidence that matters for this job is moved forward without keyword stuffing."],
                ["Submission-ready", "Review and export the tailored CV and cover letter in Markdown, DOCX, or PDF."],
              ].map(([title, body], index) => (
                <div key={title} className="grid grid-cols-[2rem_1fr] gap-4">
                  <span className="font-mono text-sm text-indigo-300">0{index + 1}</span>
                  <div><h2 className="font-semibold text-slate-100">{title}</h2><p className="mt-1 text-sm leading-6 text-slate-400">{body}</p></div>
                </div>
              ))}
            </div>
          </aside>
        </section>
      </main>
    );
  }

  const applications = repositories().applications;
  const [outstanding, outstandingCount] = await Promise.all([
    applications.listForUser(user.id, "outstanding", 5),
    applications.countForUser(user.id, "outstanding"),
  ]);
  const query = await searchParams;
  const status = queryValue(query.status);
  return (
    <WorkspaceShell current="/" email={user.email} title="Dashboard">
      <div className="space-y-9">
        {status === undefined ? null : <Alert>{status}</Alert>}
        <header className="grid gap-5 md:grid-cols-[1fr_auto] md:items-end">
          <div>
            <p className="text-sm font-medium text-indigo-300">Welcome back</p>
            <h1 className="mt-2 text-4xl font-semibold tracking-tight text-white">Keep the next application moving.</h1>
            <p className="mt-3 max-w-2xl text-slate-400">Your documents, tailoring runs, and role tracker remain private to this account.</p>
          </div>
          <Link href="/applications/create" className="button-primary">Add a job listing</Link>
        </header>
        <section className="grid gap-4 md:grid-cols-3">
          <Link href="/tailor" className="panel p-5 transition hover:border-indigo-400/40"><p className="text-xs uppercase tracking-widest text-slate-500">Tailor</p><h2 className="mt-3 text-lg font-semibold text-white">Create application drafts</h2><p className="mt-2 text-sm text-slate-400">Choose a source CV, role, model, and analysis depth.</p></Link>
          <Link href="/documents" className="panel p-5 transition hover:border-indigo-400/40"><p className="text-xs uppercase tracking-widest text-slate-500">Documents</p><h2 className="mt-3 text-lg font-semibold text-white">Manage source material</h2><p className="mt-2 text-sm text-slate-400">Upload and review master CVs and job descriptions.</p></Link>
          <Link href="/applications" className="panel p-5 transition hover:border-indigo-400/40"><p className="text-xs uppercase tracking-widest text-slate-500">Applications</p><h2 className="mt-3 text-lg font-semibold text-white">{outstandingCount} outstanding</h2><p className="mt-2 text-sm text-slate-400">Track status, documents, and follow-up activity.</p></Link>
        </section>
        <section className="panel p-6">
          <div className="flex items-center justify-between"><div><h2 className="text-lg font-semibold text-white">Next up</h2><p className="mt-1 text-sm text-slate-400">The latest roles still awaiting an application.</p></div><Link href="/applications" className="text-sm font-medium text-indigo-300 hover:text-indigo-200">View all</Link></div>
          <div className="mt-5 divide-y divide-slate-800/80">
            {outstanding.length === 0
              ? <p className="py-8 text-sm text-slate-500">No outstanding roles. Add the next listing when you find it.</p>
              : outstanding.map((application) => (
                  <Link key={application.id.toString()} href={`/applications/${application.id.toString()}`} className="grid gap-2 py-4 transition hover:text-white sm:grid-cols-[1fr_auto]">
                    <div><p className="font-medium text-slate-100">{application.title || "Untitled application"}</p><p className="mt-1 line-clamp-1 text-sm text-slate-500">{application.description}</p></div>
                    <time className="text-xs text-slate-500">{application.createdAt.toLocaleDateString("en-GB")}</time>
                  </Link>
                ))}
          </div>
        </section>
      </div>
    </WorkspaceShell>
  );
}
