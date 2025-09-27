<?php
/** @var string $title */
/** @var string $subtitle */
/** @var array<int, array{href: string, label: string, current: bool}> $navLinks */
/** @var array<int, array{id: int|null, filename: string, created_at: string, size: string}> $jobDocuments */
/** @var array<int, array{id: int|null, filename: string, created_at: string, size: string}> $cvDocuments */
/** @var array<int, array<string, mixed>> $tailoredGenerations */
/** @var array<int, string> $errors */
/** @var string|null $status */
/** @var string|null $csrfToken */
?>
<?php ob_start(); ?>
<div class="space-y-8">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm uppercase tracking-[0.3em] text-indigo-400">Workspace documents</p>
            <h2 class="mt-2 text-3xl font-semibold text-white">Manage uploads</h2>
            <p class="mt-2 max-w-2xl text-sm text-slate-400">
                Keep your latest job descriptions and CVs ready for tailoring. Upload new files below and they will appear
                instantly inside the generation wizard.
            </p>
        </div>
        <a href="/" class="inline-flex items-center gap-2 rounded-lg border border-slate-700 px-4 py-2 text-sm font-medium text-slate-200 transition hover:border-slate-500 hover:bg-slate-800/60">
            ← Back to dashboard
        </a>
    </div>

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

    <section class="grid gap-6 lg:grid-cols-[420px,1fr]">
        <form
            method="post"
            action="/documents/upload"
            enctype="multipart/form-data"
            class="space-y-5 rounded-2xl border border-slate-800/80 bg-slate-900/70 p-6 shadow-xl"
        >
            <div class="space-y-2">
                <h3 class="text-lg font-semibold text-white">Upload a document</h3>
                <p class="text-sm text-slate-400">Accepted formats: PDF, DOCX, Markdown, and plain text. Maximum size 1&nbsp;MiB.</p>
            </div>
            <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken, ENT_QUOTES) ?>">

            <fieldset class="space-y-3">
                <legend class="text-sm font-medium text-slate-200">Document type</legend>
                <p class="text-xs text-slate-400">Choose how the file should be stored so the wizard can find it.</p>
                <label class="flex items-center gap-3 text-sm text-slate-200">
                    <input type="radio" name="document_type" value="job_description" class="h-4 w-4 border-slate-600 bg-slate-800 text-indigo-500 focus:ring-indigo-400" required>
                    <span>Job description</span>
                </label>
                <label class="flex items-center gap-3 text-sm text-slate-200">
                    <input type="radio" name="document_type" value="cv" class="h-4 w-4 border-slate-600 bg-slate-800 text-indigo-500 focus:ring-indigo-400">
                    <span>CV</span>
                </label>
            </fieldset>

            <div class="space-y-2">
                <label for="document" class="text-sm font-medium text-slate-200">Select file</label>
                <input
                    type="file"
                    id="document"
                    name="document"
                    accept=".pdf,.docx,.md,.txt,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document,text/markdown,text/plain"
                    class="block w-full cursor-pointer rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-200 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-400"
                    required
                >
                <p class="text-xs text-slate-500">Uploads are scanned for macros and binary content before being stored.</p>
            </div>

            <button type="submit" class="w-full rounded-lg bg-indigo-500 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-400">
                Upload document
            </button>
        </form>

        <div class="space-y-6">
            <section class="rounded-2xl border border-slate-800/80 bg-slate-900/70 p-6 shadow-xl">
                <header class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-white">Job descriptions</h3>
                        <p class="text-sm text-slate-400">Latest first. Pick any of these inside the dashboard wizard.</p>
                    </div>
                    <span class="rounded-full border border-indigo-400/40 bg-indigo-500/10 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-indigo-200">
                        <?= count($jobDocuments) ?> stored
                    </span>
                </header>
                <div class="mt-4 divide-y divide-slate-800/60 text-sm text-slate-200">
                    <?php if (empty($jobDocuments)) : ?>
                        <p class="py-4 text-slate-400">No job descriptions uploaded yet.</p>
                    <?php else : ?>
                        <?php foreach ($jobDocuments as $document) : ?>
                            <article class="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p class="font-medium text-white"><?= htmlspecialchars($document['filename'], ENT_QUOTES) ?></p>
                                    <p class="text-xs text-slate-400">Added <?= htmlspecialchars($document['created_at'], ENT_QUOTES) ?> · <?= htmlspecialchars($document['size'], ENT_QUOTES) ?></p>
                                </div>
                                <?php if (!empty($document['id'])) : ?>
                                    <div class="flex items-center gap-2 self-start sm:self-auto">
                                        <?php if (!empty($document['view_url'])) : ?>
                                            <a href="<?= htmlspecialchars($document['view_url'], ENT_QUOTES) ?>" class="inline-flex items-center gap-2 rounded-full border border-indigo-400/40 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-indigo-100 transition hover:border-indigo-300 hover:text-indigo-50">
                                                View
                                            </a>
                                        <?php endif; ?>
                                        <form method="post" action="/documents/<?= urlencode((string) $document['id']) ?>/delete">
                                            <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken, ENT_QUOTES) ?>">
                                            <button type="submit" class="inline-flex items-center gap-2 rounded-full border border-rose-500/40 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-rose-100 transition hover:border-rose-300 hover:text-rose-50">
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-800/80 bg-slate-900/70 p-6 shadow-xl">
                <header class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-white">CV library</h3>
                        <p class="text-sm text-slate-400">Keep your strongest CV variants available for pairing.</p>
                    </div>
                    <span class="rounded-full border border-emerald-400/40 bg-emerald-500/10 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-emerald-200">
                        <?= count($cvDocuments) ?> stored
                    </span>
                </header>
                <div class="mt-4 divide-y divide-slate-800/60 text-sm text-slate-200">
                    <?php if (empty($cvDocuments)) : ?>
                        <p class="py-4 text-slate-400">No CVs uploaded yet.</p>
                    <?php else : ?>
                        <?php foreach ($cvDocuments as $document) : ?>
                            <article class="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p class="font-medium text-white"><?= htmlspecialchars($document['filename'], ENT_QUOTES) ?></p>
                                    <p class="text-xs text-slate-400">Added <?= htmlspecialchars($document['created_at'], ENT_QUOTES) ?> · <?= htmlspecialchars($document['size'], ENT_QUOTES) ?></p>
                                </div>
                                <?php if (!empty($document['id'])) : ?>
                                    <div class="flex items-center gap-2 self-start sm:self-auto">
                                        <?php if (!empty($document['view_url'])) : ?>
                                            <a href="<?= htmlspecialchars($document['view_url'], ENT_QUOTES) ?>" class="inline-flex items-center gap-2 rounded-full border border-indigo-400/40 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-indigo-100 transition hover:border-indigo-300 hover:text-indigo-50">
                                                View
                                            </a>
                                        <?php endif; ?>
                                        <form method="post" action="/documents/<?= urlencode((string) $document['id']) ?>/delete">
                                            <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken, ENT_QUOTES) ?>">
                                            <button type="submit" class="inline-flex items-center gap-2 rounded-full border border-rose-500/40 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-rose-100 transition hover:border-rose-300 hover:text-rose-50">
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-800/80 bg-slate-900/70 p-6 shadow-xl">
                <header class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-white">Tailored CV runs</h3>
                        <p class="text-sm text-slate-400">Review the drafts generated by the tailoring assistant.</p>
                    </div>
                    <span class="rounded-full border border-sky-400/40 bg-sky-500/10 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-sky-200">
                        <?= count($tailoredGenerations) ?> runs
                    </span>
                </header>
                <div class="mt-4 divide-y divide-slate-800/60 text-sm text-slate-200">
                    <?php if (empty($tailoredGenerations)) : ?>
                        <p class="py-4 text-slate-400">No tailored drafts yet. Generate one from the tailor workspace.</p>
                    <?php else : ?>
                        <?php foreach ($tailoredGenerations as $generation) : ?>
                            <?php
                                $statusLabel = ucwords(str_replace('_', ' ', (string) $generation['status']));
                            ?>
                            <article class="flex flex-col gap-3 py-4">
                                <div class="flex flex-wrap items-center gap-3">
                                    <span class="inline-flex items-center rounded-full border border-indigo-400/40 bg-indigo-500/10 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-indigo-100">
                                        <?= htmlspecialchars($statusLabel, ENT_QUOTES) ?>
                                    </span>
                                    <p class="text-xs text-slate-400">Queued <?= htmlspecialchars($generation['created_at'], ENT_QUOTES) ?></p>
                                </div>
                                <div>
                                    <p class="font-medium text-white">Job: <?= htmlspecialchars($generation['job_document']['filename'], ENT_QUOTES) ?></p>
                                    <p class="text-xs text-slate-400">Source CV: <?= htmlspecialchars($generation['cv_document']['filename'], ENT_QUOTES) ?></p>
                                </div>
                                <dl class="grid gap-2 text-xs text-slate-300 sm:grid-cols-3">
                                    <div>
                                        <dt class="font-semibold text-slate-200">Model</dt>
                                        <dd class="text-slate-400"><?= htmlspecialchars($generation['model'], ENT_QUOTES) ?></dd>
                                    </div>
                                    <div>
                                        <dt class="font-semibold text-slate-200">Thinking time</dt>
                                        <dd class="text-slate-400"><?= htmlspecialchars((string) $generation['thinking_time'], ENT_QUOTES) ?> seconds</dd>
                                    </div>
                                    <div>
                                        <dt class="font-semibold text-slate-200">Run ID</dt>
                                        <dd class="text-slate-400">#<?= htmlspecialchars((string) $generation['id'], ENT_QUOTES) ?></dd>
                                    </div>
                                </dl>
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
