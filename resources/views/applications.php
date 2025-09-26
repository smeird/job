<?php
/** @var string $title */
/** @var string $subtitle */
/** @var array<int, array{href: string, label: string, current: bool}> $navLinks */
/** @var array<int, array<string, mixed>> $outstanding */
/** @var array<int, array<string, mixed>> $applied */
/** @var array<int, string> $errors */
/** @var string|null $status */
/** @var array<string, string> $form */
/** @var string|null $csrfToken */
?>
<?php ob_start(); ?>
<div class="space-y-8">
    <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div class="space-y-3">
            <p class="text-sm uppercase tracking-[0.35em] text-indigo-400">Job application tracker</p>
            <h2 class="text-3xl font-semibold text-white">Paste postings and plan your follow-up</h2>
            <p class="max-w-2xl text-sm text-slate-400">
                Capture descriptions directly from job boards, keep the source URL handy, and mark each opportunity once you have
                submitted an application.
            </p>
        </div>
        <a href="/" class="inline-flex items-center gap-2 rounded-lg border border-slate-700 px-4 py-2 text-sm font-medium text-slate-200 transition hover:border-slate-500 hover:bg-slate-800/60">
            ← Back to dashboard
        </a>
    </header>

    <?php if (!empty($status)) : ?>
        <div class="rounded-xl border border-emerald-500/40 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100">
            <?= htmlspecialchars($status, ENT_QUOTES) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)) : ?>
        <div class="space-y-2">
            <?php foreach ($errors as $error) : ?>
                <div class="rounded-xl border border-rose-500/40 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">
                    <?= htmlspecialchars($error, ENT_QUOTES) ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <section class="grid gap-6 xl:grid-cols-[420px,1fr]">
        <form method="post" action="/applications" class="space-y-5 rounded-2xl border border-slate-800/80 bg-slate-900/70 p-6 shadow-xl">
            <div class="space-y-2">
                <h3 class="text-lg font-semibold text-white">Add a posting</h3>
                <p class="text-sm text-slate-400">
                    Paste the full job description text and the URL where you found it. This keeps everything searchable alongside your CVs.
                </p>
            </div>
            <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken, ENT_QUOTES) ?>">

            <div class="space-y-2">
                <label for="title" class="text-sm font-medium text-slate-200">Role title</label>
                <input
                    type="text"
                    id="title"
                    name="title"
                    value="<?= htmlspecialchars($form['title'] ?? '', ENT_QUOTES) ?>"
                    placeholder="e.g. Senior Automation Engineer"
                    class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-400"
                >
            </div>

            <div class="space-y-2">
                <label for="source_url" class="text-sm font-medium text-slate-200">Source URL</label>
                <input
                    type="url"
                    id="source_url"
                    name="source_url"
                    value="<?= htmlspecialchars($form['source_url'] ?? '', ENT_QUOTES) ?>"
                    placeholder="https://"
                    class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-400"
                >
                <p class="text-xs text-slate-500">Storing the URL lets you return to the original listing quickly.</p>
            </div>

            <div class="space-y-2">
                <label for="description" class="text-sm font-medium text-slate-200">Job description</label>
                <textarea
                    id="description"
                    name="description"
                    rows="10"
                    class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-400"
                    placeholder="Paste the role summary, responsibilities, and requirements here"
                    required
                ><?= htmlspecialchars($form['description'] ?? '', ENT_QUOTES) ?></textarea>
                <p class="text-xs text-slate-500">The full text remains editable later through copy and paste.</p>
            </div>

            <button type="submit" class="w-full rounded-lg bg-indigo-500 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-400">
                Save posting
            </button>
        </form>

        <div class="space-y-6">
            <section class="rounded-2xl border border-slate-800/80 bg-slate-900/70 p-6 shadow-xl">
                <header class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-white">Outstanding opportunities</h3>
                        <p class="text-sm text-slate-400">Everything you still plan to apply for appears here.</p>
                    </div>
                    <span class="rounded-full border border-amber-400/40 bg-amber-500/10 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-amber-200">
                        <?= count($outstanding) ?> queued
                    </span>
                </header>
                <div class="mt-4 space-y-4">
                    <?php if (empty($outstanding)) : ?>
                        <p class="rounded-xl border border-slate-800 bg-slate-900/70 px-4 py-6 text-sm text-slate-400">
                            Nothing queued yet. Paste the next role you are targeting to get started.
                        </p>
                    <?php else : ?>
                        <?php foreach ($outstanding as $item) : ?>
                            <article class="rounded-xl border border-slate-800 bg-slate-950/80 p-4 text-sm text-slate-200 shadow-inner">
                                <header class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <h4 class="text-base font-semibold text-white">
                                            <?= htmlspecialchars($item['title'] ?? 'Untitled application', ENT_QUOTES) ?>
                                        </h4>
                                        <p class="text-xs text-slate-500">Added <?= htmlspecialchars($item['created_at'], ENT_QUOTES) ?></p>
                                    </div>
                                    <form method="post" action="/applications/<?= urlencode((string) $item['id']) ?>/status" class="self-start">
                                        <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken, ENT_QUOTES) ?>">
                                        <input type="hidden" name="status" value="applied">
                                        <button type="submit" class="inline-flex items-center gap-2 rounded-full border border-emerald-400/40 bg-emerald-500/10 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-emerald-200 transition hover:border-emerald-300 hover:text-emerald-100">
                                            Mark applied
                                        </button>
                                    </form>
                                </header>
                                <?php if (!empty($item['source_url'])) : ?>
                                    <a href="<?= htmlspecialchars($item['source_url'], ENT_QUOTES) ?>" target="_blank" rel="noopener" class="mt-2 inline-flex items-center gap-2 text-xs text-indigo-300 hover:text-indigo-200">
                                        View listing
                                        <svg aria-hidden="true" class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                            <path d="M7 17L17 7M7 7h10v10" stroke-linecap="round" stroke-linejoin="round"></path>
                                        </svg>
                                    </a>
                                <?php endif; ?>
                                <p class="mt-3 text-sm text-slate-300">
                                    <?= nl2br(htmlspecialchars($item['description_preview'], ENT_QUOTES)) ?>
                                </p>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-800/80 bg-slate-900/70 p-6 shadow-xl">
                <header class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-white">Submitted applications</h3>
                        <p class="text-sm text-slate-400">Keep a record of where you have already applied.</p>
                    </div>
                    <span class="rounded-full border border-indigo-400/40 bg-indigo-500/10 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-indigo-200">
                        <?= count($applied) ?> sent
                    </span>
                </header>
                <div class="mt-4 space-y-4">
                    <?php if (empty($applied)) : ?>
                        <p class="rounded-xl border border-slate-800 bg-slate-900/70 px-4 py-6 text-sm text-slate-400">
                            Once you submit an application it will be archived here for quick reference.
                        </p>
                    <?php else : ?>
                        <?php foreach ($applied as $item) : ?>
                            <article class="rounded-xl border border-slate-800 bg-slate-950/80 p-4 text-sm text-slate-200 shadow-inner">
                                <header class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <h4 class="text-base font-semibold text-white">
                                            <?= htmlspecialchars($item['title'] ?? 'Untitled application', ENT_QUOTES) ?>
                                        </h4>
                                        <p class="text-xs text-slate-500">
                                            Applied <?= htmlspecialchars($item['applied_at'] ?? $item['created_at'], ENT_QUOTES) ?>
                                        </p>
                                    </div>
                                    <form method="post" action="/applications/<?= urlencode((string) $item['id']) ?>/status" class="self-start">
                                        <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken, ENT_QUOTES) ?>">
                                        <input type="hidden" name="status" value="outstanding">
                                        <button type="submit" class="inline-flex items-center gap-2 rounded-full border border-slate-700 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-300 transition hover:border-slate-500 hover:text-slate-100">
                                            Move back to queue
                                        </button>
                                    </form>
                                </header>
                                <?php if (!empty($item['source_url'])) : ?>
                                    <a href="<?= htmlspecialchars($item['source_url'], ENT_QUOTES) ?>" target="_blank" rel="noopener" class="mt-2 inline-flex items-center gap-2 text-xs text-indigo-300 hover:text-indigo-200">
                                        View listing
                                        <svg aria-hidden="true" class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                            <path d="M7 17L17 7M7 7h10v10" stroke-linecap="round" stroke-linejoin="round"></path>
                                        </svg>
                                    </a>
                                <?php endif; ?>
                                <p class="mt-3 text-sm text-slate-300">
                                    <?= nl2br(htmlspecialchars($item['description_preview'], ENT_QUOTES)) ?>
                                </p>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </section>
</div>
<?php $body = ob_get_clean(); ?>
<?php include __DIR__ . '/layout.php'; ?>
