<?php
/** @var string $title */
/** @var string $subtitle */
/** @var string $email */
/** @var array<int, array<string, mixed>> $outstandingApplications */
/** @var int $outstandingApplicationsCount */
/** @var array<int, array{href: string, label: string, current: bool}> $navLinks */
/** @var string|null $csrfToken */

$fullWidth = true;
?>
<?php ob_start(); ?>

<div class="space-y-10">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <p class="text-sm uppercase tracking-widest text-indigo-400">Signed in as <?= htmlspecialchars($email, ENT_QUOTES) ?></p>
            <h2 class="mt-2 text-3xl font-semibold tracking-tight text-white">Welcome back</h2>
            <p class="mt-2 text-base text-slate-400">
                Stay on top of your applications, documents, and insights from one place.
            </p>
        </div>
        <div class="flex flex-col gap-3 md:items-end">
            <form method="post" action="/auth/logout" class="md:self-end">
                <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                <button type="submit" class="inline-flex items-center justify-center rounded-lg border border-slate-700 px-4 py-2 text-sm font-medium text-slate-200 transition hover:border-slate-500 hover:bg-slate-800">
                    Sign out
                </button>
            </form>
        </div>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <a
            href="/tailor"
            class="group flex items-center justify-between gap-3 rounded-xl border border-slate-800/80 bg-slate-900/60 px-4 py-3 text-sm font-medium text-slate-200 transition hover:border-indigo-400/60 hover:bg-indigo-500/10 hover:text-indigo-100"
        >
            <span class="inline-flex items-center gap-2">
                <span class="rounded-full bg-indigo-500/20 px-2 py-1 text-xs uppercase tracking-wide text-indigo-200">Tailor</span>
                <span>Open CV workspace</span>
            </span>
            <svg aria-hidden="true" class="h-4 w-4 transition group-hover:translate-x-1" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path d="M5 12h14" stroke-linecap="round" stroke-linejoin="round"></path>
                <path d="M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"></path>
            </svg>
        </a>
        <a href="/documents" class="group flex items-center justify-between gap-3 rounded-xl border border-slate-800/80 bg-slate-900/60 px-4 py-3 text-sm font-medium text-slate-200 transition hover:border-indigo-400/60 hover:bg-indigo-500/10 hover:text-indigo-100">
            <span class="inline-flex items-center gap-2">
                <span class="rounded-full bg-indigo-500/20 px-2 py-1 text-xs uppercase tracking-wide text-indigo-200">Upload</span>
                <span>Manage documents</span>
            </span>
            <svg aria-hidden="true" class="h-4 w-4 transition group-hover:translate-x-1" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path d="M5 12h14" stroke-linecap="round" stroke-linejoin="round"></path>
                <path d="M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"></path>
            </svg>
        </a>
        <a href="/applications" class="group flex items-center justify-between gap-3 rounded-xl border border-slate-800/80 bg-slate-900/60 px-4 py-3 text-sm font-medium text-slate-200 transition hover:border-indigo-400/60 hover:bg-indigo-500/10 hover:text-indigo-100">
            <span class="inline-flex items-center gap-2">
                <span class="rounded-full bg-amber-500/20 px-2 py-1 text-xs uppercase tracking-wide text-amber-200">Track</span>
                <span>Job tracker</span>
            </span>
            <span class="text-xs font-semibold uppercase tracking-wide text-amber-200">
                <?= (int) $outstandingApplicationsCount ?> outstanding
            </span>
        </a>
        <a href="/usage" class="group flex items-center justify-between gap-3 rounded-xl border border-slate-800/80 bg-slate-900/60 px-4 py-3 text-sm font-medium text-slate-200 transition hover:border-indigo-400/60 hover:bg-indigo-500/10 hover:text-indigo-100">
            <span class="inline-flex items-center gap-2">
                <span class="rounded-full bg-emerald-500/20 px-2 py-1 text-xs uppercase tracking-wide text-emerald-200">Insights</span>
                <span>Usage analytics</span>
            </span>
            <svg aria-hidden="true" class="h-4 w-4 transition group-hover:translate-x-1" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path d="M5 12h14" stroke-linecap="round" stroke-linejoin="round"></path>
                <path d="M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"></path>
            </svg>
        </a>
        <a href="/retention" class="group flex items-center justify-between gap-3 rounded-xl border border-slate-800/80 bg-slate-900/60 px-4 py-3 text-sm font-medium text-slate-200 transition hover:border-indigo-400/60 hover:bg-indigo-500/10 hover:text-indigo-100">
            <span class="inline-flex items-center gap-2">
                <span class="rounded-full bg-sky-500/20 px-2 py-1 text-xs uppercase tracking-wide text-sky-200">Policy</span>
                <span>Retention settings</span>
            </span>
            <svg aria-hidden="true" class="h-4 w-4 transition group-hover:translate-x-1" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path d="M5 12h14" stroke-linecap="round" stroke-linejoin="round"></path>
                <path d="M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"></path>
            </svg>
        </a>
    </div>

    <section class="rounded-2xl border border-slate-800/80 bg-slate-900/70 p-6 shadow-xl">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-lg font-semibold text-white">Next up</h3>
                <p class="text-sm text-slate-400">A snapshot of the roles you still plan to apply for.</p>
            </div>
            <a href="/applications" class="inline-flex items-center gap-2 rounded-full border border-slate-700 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-300 transition hover:border-slate-500 hover:text-slate-100">
                Open tracker
            </a>
        </div>
        <div class="mt-4 space-y-4">
            <?php if (empty($outstandingApplications)) : ?>
                <p class="rounded-xl border border-slate-800 bg-slate-950/70 px-4 py-6 text-sm text-slate-400">
                    You have no outstanding postings right now. Capture the next role you find to keep everything organised.
                </p>
            <?php else : ?>
                <?php foreach ($outstandingApplications as $application) : ?>
                    <article class="flex flex-col gap-2 rounded-xl border border-slate-800 bg-slate-950/80 px-4 py-3 text-sm text-slate-200">
                        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                            <p class="font-medium text-white"><?= htmlspecialchars($application['title'] ?? 'Untitled application', ENT_QUOTES) ?></p>
                            <p class="text-xs text-slate-500">Added <?= htmlspecialchars($application['created_at'], ENT_QUOTES) ?></p>
                        </div>
                        <?php if (!empty($application['source_url'])) : ?>
                            <a href="<?= htmlspecialchars($application['source_url'], ENT_QUOTES) ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-2 text-xs text-indigo-300 hover:text-indigo-200">
                                View listing
                                <svg aria-hidden="true" class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path d="M7 17L17 7M7 7h10v10" stroke-linecap="round" stroke-linejoin="round"></path>
                                </svg>
                            </a>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <section class="grid gap-6 lg:grid-cols-[1.2fr,1fr]">
        <article class="rounded-2xl border border-indigo-500/30 bg-indigo-500/10 p-6 text-indigo-100 shadow-xl">
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-lg font-semibold">Tailoring moved home</h3>
                <span class="rounded-full bg-indigo-500/30 px-3 py-1 text-xs font-semibold uppercase tracking-wide">New</span>
            </div>
            <p class="mt-3 text-sm leading-relaxed">
                The CV wizard now lives in its own focused workspace. Open the tailor page to pair a job description with your best CV, adjust parameters, and queue generations without distractions.
            </p>
            <a href="/tailor" class="mt-4 inline-flex items-center gap-2 rounded-lg border border-indigo-400/60 bg-indigo-500/10 px-4 py-2 text-sm font-semibold text-indigo-100 transition hover:border-indigo-300 hover:bg-indigo-400/20">
                Go to tailor workspace
                <svg aria-hidden="true" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path d="M5 12h14" stroke-linecap="round" stroke-linejoin="round"></path>
                    <path d="M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"></path>
                </svg>
            </a>
        </article>
        <article class="rounded-2xl border border-slate-800/80 bg-slate-900/70 p-6 text-slate-200 shadow-xl">
            <h3 class="text-lg font-semibold text-white">Need a reminder?</h3>
            <p class="mt-3 text-sm text-slate-400">
                Upload documents and manage applications any time. Your data stays secure thanks to retention controls and detailed audit trails.
            </p>
            <ul class="mt-4 space-y-2 text-sm">
                <li class="flex items-center gap-2">
                    <span class="inline-flex h-2 w-2 rounded-full bg-indigo-300"></span>
                    Tailor CVs and cover letters from the dedicated workspace.
                </li>
                <li class="flex items-center gap-2">
                    <span class="inline-flex h-2 w-2 rounded-full bg-emerald-300"></span>
                    Track outstanding applications to stay organised.
                </li>
                <li class="flex items-center gap-2">
                    <span class="inline-flex h-2 w-2 rounded-full bg-sky-300"></span>
                    Review retention and usage policies when you need a refresher.
                </li>
            </ul>
        </article>
    </section>
</div>
<?php $body = ob_get_clean(); ?>
<?php include __DIR__ . '/layout.php'; ?>
