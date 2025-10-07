<?php
/** @var string $title */
/** @var string $subtitle */
/** @var array<int, array{href: string, label: string, current: bool}> $navLinks */
/** @var array<int, string> $errors */
/** @var array<string, string> $form */
/** @var array<string, array{label: string, description: string}> $statusOptions */
/** @var array<string, string> $failureReasons */
/** @var array<string, mixed> $application */
/** @var array<int, array{id: int, label: string}> $generationOptions */
/** @var array<string, mixed>|null $linkedGeneration */
/** @var string|null $status */
/** @var string|null $csrfToken */
?>
<?php ob_start(); ?>
<div class="space-y-8">
    <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div class="space-y-3">
            <p class="text-sm uppercase tracking-[0.35em] text-indigo-400">Update saved opportunity</p>
            <h2 class="text-3xl font-semibold text-white">Refine the record and capture the latest actions</h2>
            <p class="max-w-2xl text-sm text-slate-400">
                Keep the posting accurate as you advance conversations. Adjust the title, link, description, and status so the
                kanban view always mirrors reality.
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="/applications" class="inline-flex items-center gap-2 rounded-lg border border-slate-700 px-4 py-2 text-sm font-medium text-slate-200 transition hover:border-slate-500 hover:bg-slate-800/60">
                ← Back to kanban
            </a>
            <a href="/applications/create" class="inline-flex items-center gap-2 rounded-lg border border-slate-700 px-4 py-2 text-sm font-medium text-slate-200 transition hover:border-slate-500 hover:bg-slate-800/60">
                Add new posting
            </a>
        </div>
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

    <section class="grid gap-6 lg:grid-cols-[minmax(0,520px),1fr]">
        <form method="post" action="/applications/<?= urlencode((string) ($application['id'] ?? '')) ?>" class="space-y-5 rounded-2xl border border-slate-800/80 bg-slate-900/70 p-6 shadow-xl">
            <div class="space-y-2">
                <h3 class="text-lg font-semibold text-white">Edit posting</h3>
                <p class="text-sm text-slate-400">
                    Update the saved details to keep downstream tailoring and reporting accurate.
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
                <p class="text-xs text-slate-500">Keeping the original link handy makes it easy to revisit requirements.</p>
            </div>

            <div class="space-y-2">
                <label for="description" class="text-sm font-medium text-slate-200">Job description</label>
                <textarea
                    id="description"
                    name="description"
                    rows="12"
                    class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-400"
                    placeholder="Paste the responsibilities, requirements, and any key highlights"
                    required
                ><?= htmlspecialchars($form['description'] ?? '', ENT_QUOTES) ?></textarea>
                <p class="text-xs text-slate-500">The text fuels research, tailoring, and progress reports.</p>
            </div>

            <div class="grid gap-4 rounded-xl border border-slate-800/70 bg-slate-950/60 p-4 text-sm text-slate-200">
                <div class="space-y-2">
                    <label for="status" class="text-sm font-medium text-slate-200">Application status</label>
                    <select
                        id="status"
                        name="status"
                        class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-400"
                    >
                        <?php foreach ($statusOptions as $value => $option) : ?>
                            <option value="<?= htmlspecialchars($value, ENT_QUOTES) ?>" <?= ($form['status'] ?? '') === $value ? 'selected' : '' ?>>
                                <?= htmlspecialchars($option['label'], ENT_QUOTES) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-slate-500">
                        <?= htmlspecialchars($statusOptions[$form['status'] ?? 'outstanding']['description'] ?? 'Track whether the role is queued, submitted, or marked for reflection.', ENT_QUOTES) ?>
                    </p>
                </div>
                <div class="space-y-2">
                    <label for="reason_code" class="text-sm font-medium text-slate-200">Rejection reason</label>
                    <select
                        id="reason_code"
                        name="reason_code"
                        class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-400"
                    >
                        <option value="">Select a reason (required for Learning)</option>
                        <?php foreach ($failureReasons as $code => $label) : ?>
                            <option value="<?= htmlspecialchars($code, ENT_QUOTES) ?>" <?= ($form['reason_code'] ?? '') === $code ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label, ENT_QUOTES) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-slate-500">Capture why the opportunity ended to surface patterns later.</p>
                </div>
            </div>

            <button type="submit" class="w-full rounded-lg bg-indigo-500 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-400">
                Save changes
            </button>
        </form>

        <aside class="space-y-5 rounded-2xl border border-slate-800/80 bg-slate-900/60 p-6 shadow-xl">
            <section class="space-y-3">
                <h3 class="text-lg font-semibold text-white">Timeline &amp; context</h3>
                <p class="text-sm text-slate-400">
                    Use these timestamps to gauge momentum and plan follow-ups. Keeping them accurate ensures reminders and reports stay relevant.
                </p>
                <dl class="space-y-3 text-sm text-slate-300">
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-400">Created</dt>
                        <dd><?= htmlspecialchars((string) ($application['created_at'] ?? ''), ENT_QUOTES) ?></dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-400">Last updated</dt>
                        <dd><?= htmlspecialchars((string) ($application['updated_at'] ?? ''), ENT_QUOTES) ?></dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-400">Submitted</dt>
                        <dd>
                            <?php if (!empty($application['applied_at'])) : ?>
                                <?= htmlspecialchars((string) $application['applied_at'], ENT_QUOTES) ?>
                            <?php else : ?>
                                <span class="text-slate-500">Not submitted</span>
                            <?php endif; ?>
                        </dd>
                    </div>
                </dl>
            </section>

            <section class="space-y-3 rounded-2xl border border-slate-800/70 bg-slate-950/70 p-4 text-sm text-slate-200 theme-light:border-slate-200 theme-light:bg-white theme-light:text-slate-700">
                <header class="space-y-1">
                    <h4 class="text-base font-semibold text-white theme-light:text-slate-900">Tailored CV link</h4>
                    <p class="text-xs text-slate-400 theme-light:text-slate-500">Attach a generated CV so downloads stay one click away.</p>
                </header>
                <?php if (!empty($linkedGeneration)) : ?>
                    <div class="space-y-1 rounded-xl border border-indigo-400/30 bg-indigo-500/10 p-3 text-xs text-indigo-100 theme-light:border-indigo-200 theme-light:bg-indigo-50 theme-light:text-indigo-600">
                        <p class="font-semibold uppercase tracking-wide">Currently linked</p>
                        <p class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center gap-2 rounded-md bg-slate-900/60 px-2 py-1 text-[0.7rem] font-medium text-indigo-100 theme-light:bg-white theme-light:text-indigo-600">
                                <svg aria-hidden="true" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path d="M7 3h10l4 4v12a2 2 0 01-2 2H7a2 2 0 01-2-2V5a2 2 0 012-2z" stroke-linecap="round" stroke-linejoin="round"></path>
                                </svg>
                                <?= htmlspecialchars((string) ($linkedGeneration['cv_filename'] ?? 'CV draft'), ENT_QUOTES) ?>
                            </span>
                            <span class="text-indigo-200/70 theme-light:text-indigo-500">tailored for</span>
                            <span class="inline-flex items-center gap-2 rounded-md bg-slate-900/60 px-2 py-1 text-[0.7rem] font-medium text-indigo-100 theme-light:bg-white theme-light:text-indigo-600">
                                <svg aria-hidden="true" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path d="M3 7h18M3 12h18M3 17h18" stroke-linecap="round" stroke-linejoin="round"></path>
                                </svg>
                                <?= htmlspecialchars((string) ($linkedGeneration['job_filename'] ?? 'Job description'), ENT_QUOTES) ?>
                            </span>
                        </p>
                        <?php if (!empty($linkedGeneration['created_at'])) : ?>
                            <p class="text-[0.65rem] text-indigo-200/80 theme-light:text-indigo-500/80">Generated <?= htmlspecialchars((string) $linkedGeneration['created_at'], ENT_QUOTES) ?></p>
                        <?php endif; ?>
                    </div>
                <?php else : ?>
                    <p class="text-xs text-slate-400 theme-light:text-slate-500">No tailored CV linked yet. Choose one to unlock quick downloads from the kanban board.</p>
                <?php endif; ?>
                <form method="post" action="/applications/<?= urlencode((string) ($application['id'] ?? '')) ?>/generation" class="space-y-2">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken, ENT_QUOTES) ?>">
                    <label for="generation_id" class="text-xs font-semibold uppercase tracking-wide text-slate-400 theme-light:text-slate-600">Select tailored document</label>
                    <select
                        id="generation_id"
                        name="generation_id"
                        class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-400 theme-light:border-slate-300 theme-light:bg-white theme-light:text-slate-700"
                    >
                        <option value="">No tailored CV linked</option>
                        <?php foreach ($generationOptions as $option) : ?>
                            <option value="<?= htmlspecialchars((string) $option['id'], ENT_QUOTES) ?>" <?= (string) ($application['generation_id'] ?? '') === (string) $option['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($option['label'], ENT_QUOTES) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="w-full rounded-lg bg-indigo-500 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-400">
                        Update link
                    </button>
                </form>
                <?php if (empty($generationOptions)) : ?>
                    <p class="text-xs text-slate-500 theme-light:text-slate-500">Generate tailored documents from the Tailor page to link them here.</p>
                <?php endif; ?>
            </section>

            <section class="space-y-3 rounded-2xl border border-rose-500/40 bg-rose-500/10 p-4 text-xs text-rose-100 theme-light:border-rose-200 theme-light:bg-rose-50 theme-light:text-rose-600">
                <header class="space-y-1">
                    <h4 class="text-base font-semibold">Delete application</h4>
                    <p class="text-[0.75rem]">Removing the record also clears linked tailored documents and status history.</p>
                </header>
                <form method="post" action="/applications/<?= urlencode((string) ($application['id'] ?? '')) ?>/delete" class="space-y-2">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken, ENT_QUOTES) ?>">
                    <button type="submit" class="w-full rounded-lg border border-rose-400/50 bg-rose-500/20 px-4 py-2 text-sm font-semibold uppercase tracking-wide text-rose-100 transition hover:border-rose-300 hover:bg-rose-500/30 hover:text-rose-50 theme-light:border-rose-300 theme-light:text-rose-700 theme-light:hover:border-rose-400 theme-light:hover:text-rose-800">
                        Delete this application
                    </button>
                </form>
            </section>

            <div class="rounded-xl border border-indigo-500/40 bg-indigo-500/10 p-4 text-xs text-indigo-100 theme-light:border-indigo-200 theme-light:bg-indigo-50 theme-light:text-indigo-600">
                <p class="font-semibold uppercase tracking-wide">Tip</p>
                <p class="mt-1">
                    After saving, return to the kanban board to review the updated column placement or trigger fresh company research.
                </p>
            </div>
        </aside>
    </section>
</div>
<?php $body = ob_get_clean(); ?>
<?php include __DIR__ . '/layout.php'; ?>
