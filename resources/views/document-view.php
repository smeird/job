<?php
/** @var array{filename: string, created_at: string, size: string, mime_type: string, type_label: string, preview: string, id: int|null, download_url: string|null} $document */
/** @var string $title */
/** @var string $subtitle */
/** @var array<int, array{href: string, label: string, current: bool}> $navLinks */
?>
<?php ob_start(); ?>
<div class="space-y-8">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm uppercase tracking-[0.3em] text-indigo-400">Document preview</p>
            <h2 class="mt-2 text-3xl font-semibold text-white"><?= htmlspecialchars($document['filename'], ENT_QUOTES) ?></h2>
            <p class="mt-2 max-w-2xl text-sm text-slate-400">
                Review the stored file and ensure it contains the latest information before pairing it with a generation.
            </p>
        </div>
        <a href="/documents" class="inline-flex items-center gap-2 rounded-lg border border-slate-700 px-4 py-2 text-sm font-medium text-slate-200 transition hover:border-slate-500 hover:bg-slate-800/60">
            ← Back to documents
        </a>
    </div>

    <section class="grid gap-6 lg:grid-cols-[320px,1fr]">
        <div class="space-y-6">
            <article class="rounded-2xl border border-slate-800/80 bg-slate-900/70 p-6 shadow-xl">
                <h3 class="text-lg font-semibold text-white">File details</h3>
                <dl class="mt-4 space-y-3 text-sm text-slate-300">
                    <div class="flex items-baseline justify-between gap-4">
                        <dt class="text-slate-400">Type</dt>
                        <dd class="font-medium text-white"><?= htmlspecialchars($document['type_label'], ENT_QUOTES) ?></dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-4">
                        <dt class="text-slate-400">Uploaded</dt>
                        <dd class="font-medium text-white"><?= htmlspecialchars($document['created_at'], ENT_QUOTES) ?></dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-4">
                        <dt class="text-slate-400">Size</dt>
                        <dd class="font-medium text-white"><?= htmlspecialchars($document['size'], ENT_QUOTES) ?></dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-4">
                        <dt class="text-slate-400">MIME type</dt>
                        <dd class="font-medium text-white"><?= htmlspecialchars($document['mime_type'], ENT_QUOTES) ?></dd>
                    </div>
                </dl>
            </article>

            <?php if (!empty($document['id'])) : ?>
                <form method="post" action="/documents/<?= urlencode((string) $document['id']) ?>/delete" class="rounded-2xl border border-rose-500/40 bg-rose-500/10 p-6 shadow-xl space-y-4">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken, ENT_QUOTES) ?>">
                    <h3 class="text-lg font-semibold text-rose-100">Delete this document</h3>
                    <p class="text-sm text-rose-100/80">
                        Removing the file will also make it unavailable for the tailoring wizard.
                    </p>
                    <button type="submit" class="inline-flex items-center gap-2 rounded-full border border-rose-500/40 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-rose-100 transition hover:border-rose-300 hover:text-rose-50">
                        Delete document
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <article class="rounded-2xl border border-slate-800/80 bg-slate-900/70 p-6 shadow-xl">
            <header class="flex items-center justify-between gap-4">
                <div>
                    <h3 class="text-lg font-semibold text-white">Contents</h3>
                    <p class="text-sm text-slate-400">Plain text preview extracted for quick review.</p>
                </div>
                <?php if (!empty($document['download_url'])) : ?>
                    <a href="<?= htmlspecialchars($document['download_url'], ENT_QUOTES) ?>" class="inline-flex items-center gap-2 rounded-full border border-emerald-400/40 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-emerald-100 transition hover:border-emerald-300 hover:text-emerald-50">
                        Download original
                    </a>
                <?php endif; ?>
            </header>
            <div class="mt-4 max-h-[540px] overflow-auto rounded-xl border border-slate-800/60 bg-slate-950/60 p-4 text-sm leading-relaxed text-slate-200">
                <?php if ($document['preview'] === '') : ?>
                    <p class="text-slate-500">A preview is not available for this file type, but the document remains stored securely.</p>
                <?php else : ?>
                    <pre class="whitespace-pre-wrap break-words font-mono text-[13px] text-slate-100"><?= htmlspecialchars($document['preview'], ENT_QUOTES) ?></pre>
                <?php endif; ?>
            </div>
        </article>
    </section>
</div>
<?php $body = ob_get_clean(); ?>
<?php include __DIR__ . '/layout.php'; ?>
