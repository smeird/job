<?php
/** @var string $title */
/** @var string $subtitle */
/** @var array<int, array{href: string, label: string, current: bool}> $navLinks */
/** @var array<int, array<string, mixed>> $outstanding */
/** @var array<int, array<string, mixed>> $applied */
/** @var array<int, array<string, mixed>> $failed */
/** @var array<int, array{id: int, label: string}> $generationOptions */
/** @var string|null $status */
/** @var array<string, string> $failureReasons */
/** @var string|null $csrfToken */
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
                <?= count($outstanding) + count($applied) + count($failed) ?> opportunities tracked
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
            <div class="flex min-w-full snap-x gap-6">
                <?php foreach ($kanbanColumns as $column) : ?>
                    <section class="group relative min-w-[280px] flex-1 snap-start overflow-hidden rounded-2xl border <?= htmlspecialchars($column['border_class'], ENT_QUOTES) ?> bg-slate-900/70 p-5 shadow-xl shadow-indigo-900/30 backdrop-blur theme-light:border-slate-200 theme-light:bg-white/90 theme-light:shadow-soft">
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

    <section class="space-y-6">
        <section class="rounded-2xl border border-slate-800/80 bg-slate-900/70 p-6 shadow-xl theme-light:border-slate-200 theme-light:bg-white/90 theme-light:shadow-soft">
                <header class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-white theme-light:text-slate-900">Outstanding opportunities</h3>
                        <p class="text-sm text-slate-400 theme-light:text-slate-600">Everything you still plan to apply for appears here.</p>
                    </div>
                    <span class="rounded-full border border-amber-400/40 bg-amber-500/10 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-amber-200 theme-light:border-amber-200 theme-light:bg-amber-50 theme-light:text-amber-600">
                        <?= count($outstanding) ?> queued
                    </span>
                </header>
                <div class="mt-4 space-y-4">
                    <?php if (empty($outstanding)) : ?>
                        <p class="rounded-xl border border-slate-800 bg-slate-900/70 px-4 py-6 text-sm text-slate-400 theme-light:border-slate-200 theme-light:bg-slate-50/80 theme-light:text-slate-600">
                            Nothing queued yet. Paste the next role you are targeting to get started.
                        </p>
                    <?php else : ?>
                        <?php foreach ($outstanding as $item) : ?>
                            <article class="rounded-xl border border-slate-800 bg-slate-950/80 p-4 text-sm text-slate-200 shadow-inner theme-light:border-slate-200 theme-light:bg-white theme-light:text-slate-700 theme-light:shadow-soft">
                                <header class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <h4 class="text-base font-semibold text-white theme-light:text-slate-900">
                                            <?= htmlspecialchars($item['title'] ?? 'Untitled application', ENT_QUOTES) ?>
                                        </h4>
                                        <p class="text-xs text-slate-500 theme-light:text-slate-500">Added <?= htmlspecialchars($item['created_at'], ENT_QUOTES) ?></p>
                                    </div>
                                    <div class="flex flex-col gap-2 sm:items-end">
                                        <form method="post" action="/applications/<?= urlencode((string) $item['id']) ?>/status" class="self-start sm:self-auto">
                                            <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken, ENT_QUOTES) ?>">
                                            <input type="hidden" name="status" value="applied">
                                            <button type="submit" class="inline-flex items-center gap-2 rounded-full border border-emerald-400/40 bg-emerald-500/10 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-emerald-200 transition hover:border-emerald-300 hover:text-emerald-100 theme-light:border-emerald-200 theme-light:bg-emerald-50 theme-light:text-emerald-600 theme-light:hover:border-emerald-300 theme-light:hover:text-emerald-700">
                                                Mark applied
                                            </button>
                                        </form>
                                        <?php $reasonFieldId = 'failure_reason_' . ($item['id'] ?? '0') . '_outstanding'; ?>
                                        <form method="post" action="/applications/<?= urlencode((string) $item['id']) ?>/status" class="flex flex-col gap-2 rounded-xl border border-rose-500/40 bg-rose-500/10 p-3 text-xs text-rose-100 sm:flex-row sm:items-center sm:gap-3 theme-light:border-rose-200 theme-light:bg-rose-50 theme-light:text-rose-600">
                                            <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken, ENT_QUOTES) ?>">
                                            <input type="hidden" name="status" value="failed">
                                            <label for="<?= htmlspecialchars($reasonFieldId, ENT_QUOTES) ?>" class="font-medium text-rose-100 theme-light:text-rose-700">Rejection reason</label>
                                            <select
                                                id="<?= htmlspecialchars($reasonFieldId, ENT_QUOTES) ?>"
                                                name="reason_code"
                                                required
                                                class="w-full rounded-lg border border-rose-400/40 bg-rose-500/10 px-2 py-1 text-rose-100 focus:border-rose-200 focus:outline-none focus:ring-rose-200 sm:max-w-xs theme-light:border-rose-200 theme-light:bg-white theme-light:text-rose-700 theme-light:focus:border-rose-300 theme-light:focus:ring-rose-200"
                                            >
                                                <option value="" disabled selected>Select reason</option>
                                                <?php foreach ($failureReasons as $code => $label) : ?>
                                                    <option value="<?= htmlspecialchars($code, ENT_QUOTES) ?>">
                                                        <?= htmlspecialchars($label, ENT_QUOTES) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-full border border-rose-400/40 bg-rose-500/30 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-rose-100 transition hover:border-rose-200 hover:text-rose-50 theme-light:border-rose-200 theme-light:bg-rose-100 theme-light:text-rose-700 theme-light:hover:border-rose-300 theme-light:hover:text-rose-800">
                                                Mark failed
                                            </button>
                                        </form>
                                        <form method="post" action="/applications/<?= urlencode((string) $item['id']) ?>/delete" class="self-start sm:self-auto">
                                            <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken, ENT_QUOTES) ?>">
                                            <button type="submit" class="inline-flex items-center gap-2 rounded-full border border-rose-500/60 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-rose-100 transition hover:border-rose-300 hover:text-rose-50 theme-light:border-rose-300 theme-light:text-rose-700 theme-light:hover:border-rose-400 theme-light:hover:text-rose-800">
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </header>
                                <?php $generationFieldId = 'generation_' . ($item['id'] ?? '0') . '_outstanding'; ?>
                                <div class="mt-3 rounded-xl border border-slate-800 bg-slate-900/60 p-4 theme-light:border-slate-200 theme-light:bg-slate-50/80">
                                    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                                        <div class="space-y-1">
                                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 theme-light:text-slate-600">Tailored CV link</p>
                                            <?php if (!empty($item['generation'])) : ?>
                                                <p class="text-sm text-slate-200 theme-light:text-slate-700">
                                                    <?= htmlspecialchars($item['generation']['cv_filename'] ?? 'CV draft', ENT_QUOTES) ?>
                                                    <span class="text-slate-500">→</span>
                                                    <?= htmlspecialchars($item['generation']['job_filename'] ?? 'Job description', ENT_QUOTES) ?>
                                                </p>
                                                <?php if (!empty($item['generation']['created_at'])) : ?>
                                                    <p class="text-xs text-slate-500 theme-light:text-slate-500">
                                                        Generated <?= htmlspecialchars($item['generation']['created_at'], ENT_QUOTES) ?>
                                                    </p>
                                                <?php endif; ?>
                                            <?php else : ?>
                                                <p class="text-sm text-slate-400 theme-light:text-slate-600">No tailored CV linked yet.</p>
                                            <?php endif; ?>
                                        </div>
                                        <form method="post" action="/applications/<?= urlencode((string) $item['id']) ?>/generation" class="flex flex-col gap-2 md:flex-row md:items-center md:gap-3">
                                            <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken, ENT_QUOTES) ?>">
                                            <label for="<?= htmlspecialchars($generationFieldId, ENT_QUOTES) ?>" class="sr-only">Select tailored CV</label>
                                            <select
                                                id="<?= htmlspecialchars($generationFieldId, ENT_QUOTES) ?>"
                                                name="generation_id"
                                                class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-400 md:min-w-[220px] theme-light:border-slate-300 theme-light:bg-white theme-light:text-slate-700 theme-light:focus:border-indigo-400 theme-light:focus:ring-indigo-200"
                                            >
                                                <option value="">No tailored CV</option>
                                                <?php foreach ($generationOptions as $option) : ?>
                                                    <?php $optionId = (int) $option['id']; ?>
                                                    <option value="<?= htmlspecialchars((string) $optionId, ENT_QUOTES) ?>" <?= (isset($item['generation_id']) && (int) $item['generation_id'] === $optionId) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($option['label'], ENT_QUOTES) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-slate-100 transition hover:bg-slate-700 theme-light:bg-slate-900/10 theme-light:text-slate-700 theme-light:hover:bg-slate-200">
                                                Update link
                                            </button>
                                        </form>
                                    </div>
                                    <?php if (empty($generationOptions)) : ?>
                                        <p class="mt-3 text-xs text-slate-500 theme-light:text-slate-500">
                                            Generate a tailored CV from the Tailor page to link it with this application.
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($item['source_url'])) : ?>
                                    <a href="<?= htmlspecialchars($item['source_url'], ENT_QUOTES) ?>" target="_blank" rel="noopener" class="mt-2 inline-flex items-center gap-2 text-xs text-indigo-300 hover:text-indigo-200 theme-light:text-indigo-600 theme-light:hover:text-indigo-500">
                                        View listing
                                        <svg aria-hidden="true" class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                            <path d="M7 17L17 7M7 7h10v10" stroke-linecap="round" stroke-linejoin="round"></path>
                                        </svg>
                                    </a>
                                <?php endif; ?>
                                <p class="mt-3 text-sm text-slate-300 theme-light:text-slate-600">
                                    <?= nl2br(htmlspecialchars($item['description_preview'], ENT_QUOTES)) ?>
                                </p>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
        </section>

        <section class="rounded-2xl border border-slate-800/80 bg-slate-900/70 p-6 shadow-xl theme-light:border-slate-200 theme-light:bg-white/90 theme-light:shadow-soft">
                <header class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-white theme-light:text-slate-900">Submitted applications</h3>
                        <p class="text-sm text-slate-400 theme-light:text-slate-600">Keep a record of where you have already applied.</p>
                    </div>
                    <span class="rounded-full border border-indigo-400/40 bg-indigo-500/10 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-indigo-200 theme-light:border-indigo-200 theme-light:bg-indigo-50 theme-light:text-indigo-600">
                        <?= count($applied) ?> sent
                    </span>
                </header>
                <div class="mt-4 space-y-4">
                    <?php if (empty($applied)) : ?>
                        <p class="rounded-xl border border-slate-800 bg-slate-900/70 px-4 py-6 text-sm text-slate-400 theme-light:border-slate-200 theme-light:bg-slate-50/80 theme-light:text-slate-600">
                            Once you submit an application it will be archived here for quick reference.
                        </p>
                    <?php else : ?>
                        <?php foreach ($applied as $item) : ?>
                            <article class="rounded-xl border border-slate-800/80 bg-slate-950/70 p-3 text-sm text-slate-200 shadow-inner sm:p-4 theme-light:border-slate-200 theme-light:bg-white theme-light:text-slate-700 theme-light:shadow-soft">
                                <header class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-start">
                                    <div class="space-y-1">
                                        <h4 class="text-base font-semibold text-white theme-light:text-slate-900">
                                            <?= htmlspecialchars($item['title'] ?? 'Untitled application', ENT_QUOTES) ?>
                                        </h4>
                                        <p class="flex flex-wrap items-center gap-2 text-xs text-slate-500">
                                            <span class="font-medium text-slate-300 theme-light:text-slate-600">Applied</span>
                                            <span><?= htmlspecialchars($item['applied_at'] ?? $item['created_at'], ENT_QUOTES) ?></span>
                                        </p>
                                    </div>
                                    <div class="flex flex-wrap items-center justify-end gap-2">
                                        <form method="post" action="/applications/<?= urlencode((string) $item['id']) ?>/status" class="inline-flex">
                                            <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken, ENT_QUOTES) ?>">
                                            <input type="hidden" name="status" value="outstanding">
                                            <button type="submit" class="inline-flex items-center gap-2 rounded-full border border-slate-700 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-300 transition hover:border-slate-500 hover:text-slate-100 theme-light:border-slate-300 theme-light:text-slate-600 theme-light:hover:border-slate-400 theme-light:hover:text-slate-700">
                                                Move back
                                            </button>
                                        </form>
                                        <form method="post" action="/applications/<?= urlencode((string) $item['id']) ?>/delete" class="inline-flex">
                                            <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken, ENT_QUOTES) ?>">
                                            <button type="submit" class="inline-flex items-center gap-2 rounded-full border border-rose-500/60 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-rose-100 transition hover:border-rose-300 hover:text-rose-50 theme-light:border-rose-300 theme-light:text-rose-700 theme-light:hover:border-rose-400 theme-light:hover:text-rose-800">
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </header>
                                <?php $appliedReasonFieldId = 'failure_reason_' . ($item['id'] ?? '0') . '_applied'; ?>
                                <form method="post" action="/applications/<?= urlencode((string) $item['id']) ?>/status" class="mt-3 flex flex-wrap items-center gap-2 rounded-lg border border-rose-500/40 bg-rose-500/10 px-3 py-2 text-xs text-rose-100 sm:gap-3 theme-light:border-rose-200 theme-light:bg-rose-50 theme-light:text-rose-600">
                                    <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken, ENT_QUOTES) ?>">
                                    <input type="hidden" name="status" value="failed">
                                    <label for="<?= htmlspecialchars($appliedReasonFieldId, ENT_QUOTES) ?>" class="font-medium text-rose-100 theme-light:text-rose-700">Rejection reason</label>
                                    <select
                                        id="<?= htmlspecialchars($appliedReasonFieldId, ENT_QUOTES) ?>"
                                        name="reason_code"
                                        required
                                        class="w-full rounded-lg border border-rose-400/40 bg-rose-500/10 px-2 py-1 text-rose-100 focus:border-rose-200 focus:outline-none focus:ring-rose-200 sm:max-w-xs theme-light:border-rose-200 theme-light:bg-white theme-light:text-rose-700 theme-light:focus:border-rose-300 theme-light:focus:ring-rose-200"
                                    >
                                        <option value="" disabled selected>Select reason</option>
                                        <?php foreach ($failureReasons as $code => $label) : ?>
                                            <option value="<?= htmlspecialchars($code, ENT_QUOTES) ?>">
                                                <?= htmlspecialchars($label, ENT_QUOTES) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-full border border-rose-400/40 bg-rose-500/30 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-rose-100 transition hover:border-rose-200 hover:text-rose-50 theme-light:border-rose-200 theme-light:bg-rose-100 theme-light:text-rose-700 theme-light:hover:border-rose-300 theme-light:hover:text-rose-800">
                                        Mark failed
                                    </button>
                                </form>
                                <?php $appliedGenerationFieldId = 'generation_' . ($item['id'] ?? '0') . '_applied'; ?>
                                <div class="mt-3 rounded-2xl border border-indigo-500/30 bg-slate-900/70 p-4 shadow-inner shadow-indigo-900/20">
                                    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                                        <div class="flex flex-col gap-3">
                                            <div class="flex items-start gap-3">
                                                <span class="flex h-11 w-11 items-center justify-center rounded-xl border border-indigo-400/40 bg-indigo-500/10 text-indigo-200">
                                                    <svg aria-hidden="true" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                                        <path d="M9 12h6m-6 4h6M9 8h6m3-5H6a2 2 0 00-2 2v14a2 2 0 002 2h12a2 2 0 002-2V7l-5-5z" stroke-linecap="round" stroke-linejoin="round"></path>
                                                    </svg>
                                                </span>
                                                <div class="space-y-1">
                                                    <p class="text-xs font-semibold uppercase tracking-wide text-indigo-200/80">Tailored CV link</p>
                                                    <p class="text-sm text-slate-300">Connect this application with the right generated materials for quick downloads.</p>
                                                </div>
                                            </div>
                                            <?php if (!empty($item['generation'])) : ?>
                                                <div class="flex flex-wrap items-center gap-2 rounded-lg border border-indigo-400/30 bg-indigo-500/10 px-3 py-2 text-xs text-indigo-100">
                                                    <span class="inline-flex items-center gap-2 rounded-md bg-slate-900/60 px-2 py-1 text-[0.7rem] font-medium text-indigo-100">
                                                        <svg aria-hidden="true" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                                            <path d="M7 3h10l4 4v12a2 2 0 01-2 2H7a2 2 0 01-2-2V5a2 2 0 012-2z" stroke-linecap="round" stroke-linejoin="round"></path>
                                                        </svg>
                                                        <?= htmlspecialchars($item['generation']['cv_filename'] ?? 'CV draft', ENT_QUOTES) ?>
                                                    </span>
                                                    <span class="text-indigo-200/70">tailored for</span>
                                                    <span class="inline-flex items-center gap-2 rounded-md bg-slate-900/60 px-2 py-1 text-[0.7rem] font-medium text-indigo-100">
                                                        <svg aria-hidden="true" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                                            <path d="M3 7h18M3 12h18M3 17h18" stroke-linecap="round" stroke-linejoin="round"></path>
                                                        </svg>
                                                        <?= htmlspecialchars($item['generation']['job_filename'] ?? 'Job description', ENT_QUOTES) ?>
                                                    </span>
                                                </div>
                                                <?php if (!empty($item['generation']['created_at'])) : ?>
                                                    <p class="text-xs text-slate-500">Generated <?= htmlspecialchars($item['generation']['created_at'], ENT_QUOTES) ?></p>
                                                <?php endif; ?>
                                            <?php else : ?>
                                                <p class="text-sm text-slate-400">No tailored CV linked yet—select one to keep everything aligned.</p>
                                            <?php endif; ?>
                                        </div>
                                        <form method="post" action="/applications/<?= urlencode((string) $item['id']) ?>/generation" class="flex flex-col gap-2 rounded-xl border border-slate-800/60 bg-slate-950/80 p-3 text-sm text-slate-200 lg:min-w-[320px]">
                                            <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken, ENT_QUOTES) ?>">
                                            <label for="<?= htmlspecialchars($appliedGenerationFieldId, ENT_QUOTES) ?>" class="text-xs font-semibold uppercase tracking-wide text-slate-400">Select tailored CV</label>
                                            <select
                                                id="<?= htmlspecialchars($appliedGenerationFieldId, ENT_QUOTES) ?>"
                                                name="generation_id"
                                                class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-slate-100 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-400"
                                            >
                                                <option value="">No tailored CV</option>
                                                <?php foreach ($generationOptions as $option) : ?>
                                                    <?php $optionId = (int) $option['id']; ?>
                                                    <option value="<?= htmlspecialchars((string) $optionId, ENT_QUOTES) ?>" <?= (isset($item['generation_id']) && (int) $item['generation_id'] === $optionId) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($option['label'], ENT_QUOTES) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-500 px-3 py-2 text-sm font-semibold text-white transition hover:bg-indigo-400">
                                                <svg aria-hidden="true" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                                    <path d="M12 5v14m7-7H5" stroke-linecap="round" stroke-linejoin="round"></path>
                                                </svg>
                                                Update link
                                            </button>
                                        </form>
                                    </div>
                                    <?php if (empty($generationOptions)) : ?>
                                        <p class="mt-3 text-xs text-slate-500">Generate a tailored CV from the Tailor page to link it with this application.</p>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($item['source_url'])) : ?>
                                    <a href="<?= htmlspecialchars($item['source_url'], ENT_QUOTES) ?>" target="_blank" rel="noopener" class="mt-2 inline-flex items-center gap-2 text-xs text-indigo-300 hover:text-indigo-200 theme-light:text-indigo-600 theme-light:hover:text-indigo-500">
                                        View listing
                                        <svg aria-hidden="true" class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                            <path d="M7 17L17 7M7 7h10v10" stroke-linecap="round" stroke-linejoin="round"></path>
                                        </svg>
                                    </a>
                                <?php endif; ?>
                                <p class="mt-2 text-xs leading-relaxed text-slate-400 sm:text-sm theme-light:text-slate-600">
                                    <?= nl2br(htmlspecialchars($item['description_preview'], ENT_QUOTES)) ?>
                                </p>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
        </section>

        <section class="rounded-2xl border border-slate-800/80 bg-slate-900/70 p-6 shadow-xl theme-light:border-slate-200 theme-light:bg-white/90 theme-light:shadow-soft">
                <header class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-white theme-light:text-slate-900">Rejected applications</h3>
                        <p class="text-sm text-slate-400 theme-light:text-slate-600">Log outcomes and learn from every response.</p>
                    </div>
                    <span class="rounded-full border border-rose-400/40 bg-rose-500/10 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-rose-100 theme-light:border-rose-200 theme-light:bg-rose-50 theme-light:text-rose-700">
                        <?= count($failed) ?> recorded
                    </span>
                </header>
                <div class="mt-4 space-y-4">
                    <?php if (empty($failed)) : ?>
                        <p class="rounded-xl border border-slate-800 bg-slate-900/70 px-4 py-6 text-sm text-slate-400 theme-light:border-slate-200 theme-light:bg-slate-50/80 theme-light:text-slate-600">
                            When you capture rejection reasons they will surface here, giving you insight into where to refine your search.
                        </p>
                    <?php else : ?>
                        <?php foreach ($failed as $item) : ?>
                            <?php $failureLabel = $failureReasons[$item['reason_code'] ?? ''] ?? 'Unknown reason'; ?>
                            <article class="rounded-xl border border-slate-800 bg-slate-950/80 p-4 text-sm text-slate-200 shadow-inner theme-light:border-slate-200 theme-light:bg-white theme-light:text-slate-700 theme-light:shadow-soft">
                                <header class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="space-y-2">
                                        <h4 class="text-base font-semibold text-white theme-light:text-slate-900">
                                            <?= htmlspecialchars($item['title'] ?? 'Untitled application', ENT_QUOTES) ?>
                                        </h4>
                                        <p class="text-xs text-slate-500 theme-light:text-slate-500">
                                            Failed <?= htmlspecialchars($item['updated_at'] ?? $item['created_at'], ENT_QUOTES) ?>
                                        </p>
                                        <span class="inline-flex w-fit items-center gap-2 rounded-full border border-rose-400/40 bg-rose-500/10 px-3 py-1 text-[0.7rem] font-semibold uppercase tracking-wide text-rose-100 theme-light:border-rose-200 theme-light:bg-rose-50 theme-light:text-rose-700">
                                            <?= htmlspecialchars($failureLabel, ENT_QUOTES) ?>
                                        </span>
                                    </div>
                                    <div class="flex flex-col gap-2 sm:items-end">
                                        <form method="post" action="/applications/<?= urlencode((string) $item['id']) ?>/status" class="self-start sm:self-auto">
                                            <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken, ENT_QUOTES) ?>">
                                            <input type="hidden" name="status" value="outstanding">
                                            <button type="submit" class="inline-flex items-center gap-2 rounded-full border border-slate-700 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-300 transition hover:border-slate-500 hover:text-slate-100 theme-light:border-slate-300 theme-light:text-slate-600 theme-light:hover:border-slate-400 theme-light:hover:text-slate-700">
                                                Reopen opportunity
                                            </button>
                                        </form>
                                        <form method="post" action="/applications/<?= urlencode((string) $item['id']) ?>/delete" class="self-start sm:self-auto">
                                            <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken, ENT_QUOTES) ?>">
                                            <button type="submit" class="inline-flex items-center gap-2 rounded-full border border-rose-500/60 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-rose-100 transition hover:border-rose-300 hover:text-rose-50 theme-light:border-rose-300 theme-light:text-rose-700 theme-light:hover:border-rose-400 theme-light:hover:text-rose-800">
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </header>
                                <?php $failedGenerationFieldId = 'generation_' . ($item['id'] ?? '0') . '_failed'; ?>
                                <div class="mt-3 rounded-xl border border-slate-800 bg-slate-900/60 p-4 theme-light:border-slate-200 theme-light:bg-slate-50/80">
                                    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                                        <div class="space-y-1">
                                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 theme-light:text-slate-600">Tailored CV link</p>
                                            <?php if (!empty($item['generation'])) : ?>
                                                <p class="text-sm text-slate-200 theme-light:text-slate-700">
                                                    <?= htmlspecialchars($item['generation']['cv_filename'] ?? 'CV draft', ENT_QUOTES) ?>
                                                    <span class="text-slate-500">→</span>
                                                    <?= htmlspecialchars($item['generation']['job_filename'] ?? 'Job description', ENT_QUOTES) ?>
                                                </p>
                                                <?php if (!empty($item['generation']['created_at'])) : ?>
                                                    <p class="text-xs text-slate-500 theme-light:text-slate-500">
                                                        Generated <?= htmlspecialchars($item['generation']['created_at'], ENT_QUOTES) ?>
                                                    </p>
                                                <?php endif; ?>
                                            <?php else : ?>
                                                <p class="text-sm text-slate-400 theme-light:text-slate-600">No tailored CV linked yet.</p>
                                            <?php endif; ?>
                                        </div>
                                        <form method="post" action="/applications/<?= urlencode((string) $item['id']) ?>/generation" class="flex flex-col gap-2 md:flex-row md:items-center md:gap-3">
                                            <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken, ENT_QUOTES) ?>">
                                            <label for="<?= htmlspecialchars($failedGenerationFieldId, ENT_QUOTES) ?>" class="sr-only">Select tailored CV</label>
                                            <select
                                                id="<?= htmlspecialchars($failedGenerationFieldId, ENT_QUOTES) ?>"
                                                name="generation_id"
                                                class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-400 md:min-w-[220px] theme-light:border-slate-300 theme-light:bg-white theme-light:text-slate-700 theme-light:focus:border-indigo-400 theme-light:focus:ring-indigo-200"
                                            >
                                                <option value="">No tailored CV</option>
                                                <?php foreach ($generationOptions as $option) : ?>
                                                    <?php $optionId = (int) $option['id']; ?>
                                                    <option value="<?= htmlspecialchars((string) $optionId, ENT_QUOTES) ?>" <?= (isset($item['generation_id']) && (int) $item['generation_id'] === $optionId) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($option['label'], ENT_QUOTES) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-slate-100 transition hover:bg-slate-700 theme-light:bg-slate-900/10 theme-light:text-slate-700 theme-light:hover:bg-slate-200">
                                                Update link
                                            </button>
                                        </form>
                                    </div>
                                    <?php if (empty($generationOptions)) : ?>
                                        <p class="mt-3 text-xs text-slate-500 theme-light:text-slate-500">
                                            Generate a tailored CV from the Tailor page to link it with this application.
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($item['source_url'])) : ?>
                                    <a href="<?= htmlspecialchars($item['source_url'], ENT_QUOTES) ?>" target="_blank" rel="noopener" class="mt-2 inline-flex items-center gap-2 text-xs text-indigo-300 hover:text-indigo-200 theme-light:text-indigo-600 theme-light:hover:text-indigo-500">
                                        View listing
                                        <svg aria-hidden="true" class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                            <path d="M7 17L17 7M7 7h10v10" stroke-linecap="round" stroke-linejoin="round"></path>
                                        </svg>
                                    </a>
                                <?php endif; ?>
                                <p class="mt-3 text-sm text-slate-300 theme-light:text-slate-600">
                                    <?= nl2br(htmlspecialchars($item['description_preview'], ENT_QUOTES)) ?>
                                </p>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
        </section>
    </section>
</div>
<?php $body = ob_get_clean(); ?>
<?php include __DIR__ . '/layout.php'; ?>
