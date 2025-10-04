<?php
/** @var array{filename: string, created_at: string, size: string, mime_type: string, type_label: string, download_url: string|null, plain_view_url: string} $document */
/** @var array{raw: string, html: string} $markdown */
/** @var string $title */
/** @var string $subtitle */
/** @var array<int, array{href: string, label: string, current: bool}> $navLinks */
?>
<?php ob_start(); ?>
<div class="space-y-8">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm uppercase tracking-[0.3em] text-indigo-400">Document preview</p>
            <h2 class="mt-2 text-3xl font-semibold text-white">Formatted markdown</h2>
            <p class="mt-2 max-w-2xl text-sm text-slate-400">
                Headings, lists, and emphasis are rendered exactly as they will appear in generated content.
            </p>
        </div>
        <a href="<?= htmlspecialchars($document['plain_view_url'], ENT_QUOTES) ?>" class="inline-flex items-center gap-2 rounded-lg border border-slate-700 px-4 py-2 text-sm font-medium text-slate-200 transition hover:border-slate-500 hover:bg-slate-800/60">
            ← Back to plain view
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

            <?php if (!empty($document['download_url'])) : ?>
                <a href="<?= htmlspecialchars($document['download_url'], ENT_QUOTES) ?>" class="flex items-center justify-center gap-2 rounded-2xl border border-emerald-400/40 bg-emerald-500/10 px-4 py-3 text-sm font-semibold uppercase tracking-wide text-emerald-100 transition hover:border-emerald-300 hover:text-emerald-50">
                    Download original
                </a>
            <?php endif; ?>
        </div>

        <div class="space-y-6">
            <article id="formatted-markdown" class="rounded-2xl border border-slate-800/80 bg-slate-900/70 p-6 shadow-xl">
                <header class="flex flex-col gap-1">
                    <h3 class="text-lg font-semibold text-white">Formatted view</h3>
                    <p class="text-sm text-slate-400">Clean CommonMark rendering with unsafe HTML stripped for safety.</p>
                </header>
                <div class="prose prose-invert mt-4 max-w-none space-y-4 text-slate-100">
                    <?= $markdown['html'] ?>
                </div>
            </article>

            <article class="rounded-2xl border border-slate-800/80 bg-slate-900/70 p-6 shadow-xl">
                <header class="flex flex-col gap-1">
                    <h3 class="text-lg font-semibold text-white">Raw markdown</h3>
                    <p class="text-sm text-slate-400">Copy and reuse the original text version if you need to edit locally.</p>
                </header>
                <pre class="mt-4 max-h-[320px] overflow-auto rounded-xl border border-slate-800/60 bg-slate-950/60 p-4 text-sm leading-relaxed text-slate-200 whitespace-pre-wrap break-words font-mono text-[13px]"><?= htmlspecialchars($markdown['raw'], ENT_QUOTES) ?></pre>
            </article>
        </div>
    </section>
</div>
<?php $body = ob_get_clean(); ?>
<?php include __DIR__ . '/layout.php'; ?>
