<?php
/** @var string $title */
/** @var string $subtitle */
/** @var array<int, array{href: string, label: string, current: bool}> $navLinks */
/** @var array<int, array<string, mixed>> $outstanding */
/** @var array<int, array<string, mixed>> $applied */
/** @var array<int, array<string, mixed>> $interviewing */
/** @var array<int, array<string, mixed>> $contracting */
/** @var array<int, array<string, mixed>> $failed */
/** @var string|null $status */
?>
<?php
$additionalHead = '<script src="/assets/js/applications.js" defer></script>';
?>
<?php ob_start(); ?>
<div class="space-y-8">
    <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div class="space-y-3">
            <p class="text-sm uppercase tracking-[0.35em] text-indigo-400 theme-light:text-indigo-600">Job application tracker</p>
            <h2 class="text-3xl font-semibold text-white theme-light:text-slate-900">Paste postings and plan your follow-up</h2>
            <p class="max-w-2xl text-sm text-slate-400 theme-light:text-slate-600">
                Capture descriptions directly from job boards, keep the source URL handy, and mark each opportunity once you have
                submitted an application.
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="/applications/create" class="inline-flex items-center gap-2 rounded-lg bg-indigo-500 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-400">
                + Add posting
            </a>
            <a href="/" class="inline-flex items-center gap-2 rounded-lg border border-slate-700 px-4 py-2 text-sm font-medium text-slate-200 transition hover:border-slate-500 hover:bg-slate-800/60 theme-light:border-slate-300 theme-light:text-slate-600 theme-light:hover:border-slate-400 theme-light:hover:bg-slate-100">
                ← Back to dashboard
            </a>
        </div>
    </header>

    <section class="relative overflow-hidden rounded-3xl border border-slate-800/70 bg-slate-900/60 p-6 shadow-2xl theme-light:border-slate-200 theme-light:bg-white/85 theme-light:shadow-soft">
        <div class="pointer-events-none absolute inset-0 bg-gradient-to-br from-indigo-950/70 via-slate-900/40 to-slate-950/70 opacity-90 theme-light:from-white/80 theme-light:via-indigo-100/40 theme-light:to-transparent theme-light:opacity-80"></div>
        <div class="pointer-events-none absolute -top-24 right-10 h-56 w-56 rounded-full bg-indigo-500/20 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-20 left-0 h-64 w-64 rounded-full bg-emerald-500/10 blur-3xl"></div>
        <header class="relative z-10 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div class="space-y-2">
                <p class="text-xs font-semibold uppercase tracking-[0.4em] text-indigo-300 theme-light:text-indigo-500">Pipeline overview</p>
                <h3 class="text-2xl font-semibold text-white theme-light:text-slate-900">Where each application stands today</h3>
                <p class="max-w-2xl text-sm text-slate-300 theme-light:text-slate-600">
                    Glance across every stage of your search. Drag-inspired swimlanes spotlight roles you are nurturing,
                    recently submitted, or learning from after a response.
                </p>
            </div>
            <div class="flex items-center gap-2 rounded-full border border-indigo-500/50 bg-indigo-500/15 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-indigo-100 shadow-lg shadow-indigo-500/10 theme-light:border-indigo-200 theme-light:bg-indigo-50 theme-light:text-indigo-600 theme-light:shadow-indigo-200/60">
                <span class="inline-flex h-2 w-2 rounded-full bg-emerald-400"></span>
                <?= count($outstanding) + count($applied) + count($interviewing) + count($contracting) + count($failed) ?> opportunities tracked
            </div>
        </header>
        <?php
        $kanbanColumns = [
            [
                'title' => 'Queued',
                'description' => 'Roles waiting on materials or next steps.',
                'badge' => count($outstanding) . ' queued',
                'badge_class' => 'border-amber-400/40 bg-amber-500/10 text-amber-200 theme-light:border-amber-200 theme-light:bg-amber-50 theme-light:text-amber-700',
                'items' => $outstanding,
                'empty' => 'Nothing is queued right now. Add a posting to keep the momentum going.',
                'accent' => 'from-amber-500/30 via-amber-500/5 to-transparent',
                'border_class' => 'border-amber-500/40'
            ],
            [
                'title' => 'Submitted',
                'description' => 'Applications that are out the door and awaiting replies.',
                'badge' => count($applied) . ' sent',
                'badge_class' => 'border-indigo-400/40 bg-indigo-500/10 text-indigo-200 theme-light:border-indigo-200 theme-light:bg-indigo-50 theme-light:text-indigo-600',
                'items' => $applied,
                'empty' => 'Once you mark an application as applied it will appear here.',
                'accent' => 'from-indigo-500/30 via-indigo-500/5 to-transparent',
                'border_class' => 'border-indigo-500/40'
            ],
            [
                'title' => 'Interviewing',
                'description' => 'Opportunities with active interviews, screens, or recruiter touchpoints.',
                'badge' => count($interviewing) . ' in motion',
                'badge_class' => 'border-emerald-400/40 bg-emerald-500/10 text-emerald-200 theme-light:border-emerald-200 theme-light:bg-emerald-50 theme-light:text-emerald-700',
                'items' => $interviewing,
                'empty' => 'Move roles here once you are scheduling conversations or meeting the team.',
                'accent' => 'from-emerald-500/30 via-emerald-500/5 to-transparent',
                'border_class' => 'border-emerald-500/40'
            ],
            [
                'title' => 'Contracting',
                'description' => 'Offers under review and contract discussions that need timely attention.',
                'badge' => count($contracting) . ' negotiating',
                'badge_class' => 'border-cyan-400/40 bg-cyan-500/10 text-cyan-200 theme-light:border-cyan-200 theme-light:bg-cyan-50 theme-light:text-cyan-700',
                'items' => $contracting,
                'empty' => 'Once an offer is on the table, track paperwork and deadlines in this lane.',
                'accent' => 'from-cyan-500/30 via-cyan-500/5 to-transparent',
                'border_class' => 'border-cyan-500/40'
            ],
            [
                'title' => 'Learnings',
                'description' => 'Rejections captured with the reason so you can refine your approach.',
                'badge' => count($failed) . ' recorded',
                'badge_class' => 'border-rose-400/40 bg-rose-500/10 text-rose-200 theme-light:border-rose-200 theme-light:bg-rose-50 theme-light:text-rose-700',
                'items' => $failed,
                'empty' => 'Celebrate the wins—no rejections logged yet.',
                'accent' => 'from-rose-500/30 via-rose-500/5 to-transparent',
                'border_class' => 'border-rose-500/40'
            ]
        ];
        ?>
        <div class="relative z-10 mt-6 overflow-x-auto pb-2">
            <div class="grid min-w-full snap-x gap-6 grid-flow-col auto-cols-[minmax(280px,1fr)] sm:auto-cols-[minmax(300px,1fr)] xl:auto-cols-[minmax(320px,1fr)]">
                <?php foreach ($kanbanColumns as $column) : ?>
                    <section class="group relative min-w-[280px] snap-start overflow-hidden rounded-2xl border <?= htmlspecialchars($column['border_class'], ENT_QUOTES) ?> bg-slate-900/70 p-5 shadow-xl shadow-indigo-900/30 backdrop-blur theme-light:border-slate-200 theme-light:bg-white/90 theme-light:shadow-soft">
                        <div class="pointer-events-none absolute inset-0 rounded-2xl bg-gradient-to-br <?= htmlspecialchars($column['accent'], ENT_QUOTES) ?> opacity-70 transition duration-500 group-hover:opacity-100 theme-light:opacity-80"></div>
                        <header class="relative z-10 flex flex-col gap-3">
                            <div class="flex items-center justify-between gap-3">
                                <h4 class="text-lg font-semibold text-white theme-light:text-slate-900">
                                    <?= htmlspecialchars($column['title'], ENT_QUOTES) ?>
                                </h4>
                                <span class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-[0.7rem] font-semibold uppercase tracking-wide <?= htmlspecialchars($column['badge_class'], ENT_QUOTES) ?>">
                                    <?= htmlspecialchars($column['badge'], ENT_QUOTES) ?>
                                </span>
                            </div>
                            <p class="text-xs text-slate-400 theme-light:text-slate-600">
                                <?= htmlspecialchars($column['description'], ENT_QUOTES) ?>
                            </p>
                        </header>
                        <div class="relative z-10 mt-4 space-y-3">
                            <?php if (empty($column['items'])) : ?>
                                <p class="rounded-xl border border-dashed border-slate-800 bg-slate-900/60 px-4 py-6 text-center text-xs text-slate-400 theme-light:border-slate-300 theme-light:bg-slate-50/80 theme-light:text-slate-600">
                                    <?= htmlspecialchars($column['empty'], ENT_QUOTES) ?>
                                </p>
                            <?php else : ?>
                                <?php foreach ($column['items'] as $kanbanItem) : ?>
                                    <?php
                                    $generation = isset($kanbanItem['generation']) && is_array($kanbanItem['generation'])
                                        ? $kanbanItem['generation']
                                        : null;
                                    $applicationIdValue = isset($kanbanItem['id']) ? (string) $kanbanItem['id'] : '';
                                    $researchPanelId = 'application_research_' . ($applicationIdValue !== ''
                                        ? preg_replace('/[^a-zA-Z0-9_-]/', '_', $applicationIdValue)
                                        : uniqid('app_', false));
                                    $applicationTitleValue = isset($kanbanItem['title']) && is_string($kanbanItem['title'])
                                        ? $kanbanItem['title']
                                        : 'Untitled role';
                                    $submittedAt = isset($kanbanItem['applied_at']) && $kanbanItem['applied_at'] !== null && $kanbanItem['applied_at'] !== ''
                                        ? 'Submitted ' . $kanbanItem['applied_at']
                                        : 'Not submitted yet';
                                    ?>
                                    <article class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 text-xs text-slate-100 shadow-inner shadow-indigo-900/20 theme-light:border-slate-200 theme-light:bg-white theme-light:text-slate-700 theme-light:shadow-soft">
                                        <div class="flex flex-col gap-3">
                                            <div class="flex items-start justify-between gap-3">
                                                <h5 class="text-sm font-semibold text-white theme-light:text-slate-900">
                                                    <a
                                                        href="/applications/<?= urlencode($applicationIdValue) ?>"
                                                        class="inline-flex items-center gap-2 text-left text-white underline-offset-2 transition hover:text-indigo-200 hover:underline theme-light:text-slate-900 theme-light:hover:text-indigo-600"
                                                    >
                                                        <?= htmlspecialchars($applicationTitleValue, ENT_QUOTES) ?>
                                                    </a>
                                                </h5>
                                                <div class="flex flex-wrap items-center justify-end gap-2">
                                                    <button
                                                        type="button"
                                                        class="inline-flex items-center gap-2 rounded-full border border-indigo-400/40 bg-indigo-500/10 px-3 py-1 text-[0.68rem] font-semibold uppercase tracking-wide text-indigo-100 transition hover:border-indigo-300 hover:text-indigo-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-400 theme-light:border-indigo-200 theme-light:bg-indigo-50 theme-light:text-indigo-600 theme-light:hover:border-indigo-300 theme-light:hover:text-indigo-700"
                                                        data-research-trigger
                                                        data-application-id="<?= htmlspecialchars($applicationIdValue, ENT_QUOTES) ?>"
                                                        data-application-title="<?= htmlspecialchars($applicationTitleValue, ENT_QUOTES) ?>"
                                                        aria-controls="<?= htmlspecialchars($researchPanelId, ENT_QUOTES) ?>"
                                                        aria-expanded="false"
                                                    >
                                                        <svg aria-hidden="true" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                                            <path d="M21 21l-4.35-4.35"></path>
                                                            <circle cx="10.5" cy="10.5" r="6.5"></circle>
                                                        </svg>
                                                        <span>Research company</span>
                                                    </button>
                                                    <?php if ($generation !== null) : ?>
                                                        <a
                                                            href="/generations/<?= urlencode((string) ($generation['id'] ?? '')) ?>/download?artifact=cv&amp;format=pdf"
                                                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-indigo-400/40 bg-indigo-500/10 text-indigo-200 transition hover:border-indigo-300 hover:text-indigo-50 theme-light:border-indigo-200 theme-light:bg-indigo-50 theme-light:text-indigo-600 theme-light:hover:border-indigo-300 theme-light:hover:text-indigo-700"
                                                            title="Download CV<?= isset($generation['cv_filename']) ? ': ' . htmlspecialchars((string) $generation['cv_filename'], ENT_QUOTES) : '' ?>"
                                                            aria-label="Download CV"
                                                        >
                                                            <svg aria-hidden="true" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                                                <path d="M9 12h6m-6 4h6M9 8h6m3-5H6a2 2 0 00-2 2v14a2 2 0 002 2h12a2 2 0 002-2V7l-5-5z" stroke-linecap="round" stroke-linejoin="round"></path>
                                                            </svg>
                                                        </a>
                                                        <a
                                                            href="/generations/<?= urlencode((string) ($generation['id'] ?? '')) ?>/download?artifact=cover_letter&amp;format=pdf"
                                                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-emerald-400/40 bg-emerald-500/10 text-emerald-200 transition hover:border-emerald-300 hover:text-emerald-50 theme-light:border-emerald-200 theme-light:bg-emerald-50 theme-light:text-emerald-600 theme-light:hover:border-emerald-300 theme-light:hover:text-emerald-700"
                                                            title="Download cover letter"
                                                            aria-label="Download cover letter"
                                                        >
                                                            <svg aria-hidden="true" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                                                <path d="M3 8l9 6 9-6" stroke-linecap="round" stroke-linejoin="round"></path>
                                                                <path d="M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" stroke-linecap="round" stroke-linejoin="round"></path>
                                                            </svg>
                                                        </a>
                                                    <?php else : ?>
                                                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-700 bg-slate-800/70 text-slate-500 theme-light:border-slate-200 theme-light:bg-slate-100 theme-light:text-slate-500" title="No tailored CV linked" aria-hidden="true">
                                                            <svg aria-hidden="true" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                                                <path d="M9 12h6m-6 4h6M9 8h6m3-5H6a2 2 0 00-2 2v14a2 2 0 002 2h12a2 2 0 002-2V7l-5-5z" stroke-linecap="round" stroke-linejoin="round"></path>
                                                            </svg>
                                                        </span>
                                                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-700 bg-slate-800/70 text-slate-500 theme-light:border-slate-200 theme-light:bg-slate-100 theme-light:text-slate-500" title="No cover letter linked" aria-hidden="true">
                                                            <svg aria-hidden="true" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                                                <path d="M3 8l9 6 9-6" stroke-linecap="round" stroke-linejoin="round"></path>
                                                                <path d="M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" stroke-linecap="round" stroke-linejoin="round"></path>
                                                            </svg>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div
                                                id="<?= htmlspecialchars($researchPanelId, ENT_QUOTES) ?>"
                                                class="hidden rounded-xl border border-indigo-400/30 bg-slate-950/80 p-3 text-left text-[0.72rem] text-slate-200 shadow-inner shadow-indigo-900/20 focus:outline-none theme-light:border-indigo-200 theme-light:bg-indigo-50 theme-light:text-slate-700"
                                                data-research-output
                                                data-application-id="<?= htmlspecialchars($applicationIdValue, ENT_QUOTES) ?>"
                                                role="region"
                                                aria-label="Research insights for <?= htmlspecialchars($applicationTitleValue, ENT_QUOTES) ?>"
                                                aria-live="polite"
                                                aria-busy="false"
                                                aria-hidden="true"
                                                tabindex="-1"
                                            >
                                                <div class="flex items-center gap-2 text-indigo-200 theme-light:text-indigo-600" data-research-loading hidden role="status" aria-live="polite">
                                                    <svg aria-hidden="true" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                                        <circle cx="12" cy="12" r="9" opacity="0.25"></circle>
                                                        <path d="M21 12a9 9 0 01-9 9" stroke-linecap="round"></path>
                                                    </svg>
                                                    <span class="font-medium">Gathering company research…</span>
                                                </div>
                                                <div class="prose prose-invert max-w-none text-left text-sm leading-relaxed theme-light:prose-slate" data-research-content hidden></div>
                                                <div class="space-y-1" data-research-error hidden role="alert">
                                                    <p class="text-sm font-semibold text-rose-200 theme-light:text-rose-600" data-research-error-heading>Unable to load insights.</p>
                                                    <p class="text-xs text-rose-200/80 theme-light:text-rose-600/80" data-research-error-body>Please try again in a moment.</p>
                                                </div>
                                                <p class="mt-3 text-[0.65rem] text-slate-400 theme-light:text-slate-600" data-research-meta hidden></p>
                                            </div>
                                            <div class="flex flex-col gap-2 text-[0.7rem] text-slate-400 theme-light:text-slate-600">
                                                <?php if (!empty($kanbanItem['source_url'])) : ?>
                                                    <a href="<?= htmlspecialchars($kanbanItem['source_url'], ENT_QUOTES) ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-1 font-medium text-indigo-300 transition hover:text-indigo-200 theme-light:text-indigo-600 theme-light:hover:text-indigo-500">
                                                        <svg aria-hidden="true" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                                            <path d="M14 3h7v7" stroke-linecap="round" stroke-linejoin="round"></path>
                                                            <path d="M10 14L21 3" stroke-linecap="round" stroke-linejoin="round"></path>
                                                            <path d="M5 10v11h11" stroke-linecap="round" stroke-linejoin="round"></path>
                                                        </svg>
                                                        <span>Job listing</span>
                                                    </a>
                                                <?php endif; ?>
                                                <span class="flex items-center gap-2 text-slate-400 theme-light:text-slate-600">
                                                    <svg aria-hidden="true" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                                        <path d="M8 7V3m8 4V3M5 11h14M7 21h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v12a2 2 0 002 2z" stroke-linecap="round" stroke-linejoin="round"></path>
                                                    </svg>
                                                    <span><?= htmlspecialchars($submittedAt, ENT_QUOTES) ?></span>
                                                </span>
                                            </div>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            </div>
        </div>
    </section>


    <?php if (!empty($status)) : ?>
        <div class="rounded-xl border border-emerald-500/40 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100 theme-light:border-emerald-200 theme-light:bg-emerald-50 theme-light:text-emerald-700">
            <?= htmlspecialchars($status, ENT_QUOTES) ?>
        </div>
    <?php endif; ?>

    <section class="rounded-2xl border border-slate-800/80 bg-slate-900/70 p-6 shadow-xl theme-light:border-slate-200 theme-light:bg-white/90 theme-light:shadow-soft">
        <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="space-y-1">
                <h3 class="text-lg font-semibold text-white theme-light:text-slate-900">How to keep everything updated</h3>
                <p class="text-sm text-slate-400 theme-light:text-slate-600">
                    Log each opportunity on the dedicated Add posting page. Once saved, return here to monitor progress and click any card to jump to the full edit screen for status changes or deeper updates.
                </p>
            </div>
            <a href="/applications/create" class="inline-flex items-center gap-2 rounded-lg border border-indigo-500/40 bg-indigo-500/10 px-4 py-2 text-sm font-semibold text-indigo-100 transition hover:border-indigo-400 hover:text-white theme-light:border-indigo-200 theme-light:bg-indigo-50 theme-light:text-indigo-600 theme-light:hover:border-indigo-300 theme-light:hover:text-indigo-700">
                Add another posting
            </a>
        </header>
        <div class="mt-4 grid gap-4 text-sm text-slate-300 theme-light:text-slate-600 sm:grid-cols-3">
            <div class="rounded-xl border border-slate-800 bg-slate-950/80 p-4 theme-light:border-slate-200 theme-light:bg-white">
                <p class="font-semibold text-white theme-light:text-slate-900">Queued</p>
                <p class="mt-1 text-xs">Roles waiting on materials or next steps remain visible in the first column.</p>
            </div>
            <div class="rounded-xl border border-slate-800 bg-slate-950/80 p-4 theme-light:border-slate-200 theme-light:bg-white">
                <p class="font-semibold text-white theme-light:text-slate-900">Submitted</p>
                <p class="mt-1 text-xs">Keep an eye on application dates and revisit descriptions before interviews.</p>
            </div>
            <div class="rounded-xl border border-slate-800 bg-slate-950/80 p-4 theme-light:border-slate-200 theme-light:bg-white">
                <p class="font-semibold text-white theme-light:text-slate-900">Learnings</p>
                <p class="mt-1 text-xs">Track feedback and reasons so you can refine your approach later.</p>
            </div>
        </div>
    </section>
</div>
<?php $body = ob_get_clean(); ?>
<?php include __DIR__ . '/layout.php'; ?>
