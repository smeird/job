<?php
/** @var string $title */
/** @var string $subtitle */
/** @var array<int, array{href: string, label: string, current: bool}> $navLinks */
/** @var array<int, string> $errors */
/** @var array<string, string> $form */
/** @var string|null $status */
/** @var string|null $csrfToken */
?>
<?php ob_start(); ?>
<div class="space-y-8">
    <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div class="space-y-3">
            <p class="text-sm uppercase tracking-[0.35em] text-indigo-400">Log a new opportunity</p>
            <h2 class="text-3xl font-semibold text-white">Keep your pipeline organised from the start</h2>
            <p class="max-w-2xl text-sm text-slate-400">
                Paste the original listing so you can tailor materials later and always know where each posting originated.
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="/applications" class="inline-flex items-center gap-2 rounded-lg border border-slate-700 px-4 py-2 text-sm font-medium text-slate-200 transition hover:border-slate-500 hover:bg-slate-800/60">
                ← Back to kanban
            </a>
            <a href="/" class="inline-flex items-center gap-2 rounded-lg border border-slate-700 px-4 py-2 text-sm font-medium text-slate-200 transition hover:border-slate-500 hover:bg-slate-800/60">
                Dashboard
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
        <form method="post" action="/applications" class="space-y-5 rounded-2xl border border-slate-800/80 bg-slate-900/70 p-6 shadow-xl">
            <div class="space-y-2">
                <h3 class="text-lg font-semibold text-white">Add a posting</h3>
                <p class="text-sm text-slate-400">
                    Store the core details so they are ready when you move the application through the kanban board.
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
                <p class="text-xs text-slate-500">Storing the link lets you revisit the listing whenever you need more detail.</p>
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
                <p class="text-xs text-slate-500">The text remains editable later if you want to refine or remove details.</p>
            </div>

            <button type="submit" class="w-full rounded-lg bg-indigo-500 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-400">
                Save posting
            </button>
        </form>

        <aside class="space-y-4 rounded-2xl border border-slate-800/80 bg-slate-900/60 p-6 shadow-xl">
            <h3 class="text-lg font-semibold text-white">Why capture everything?</h3>
            <p class="text-sm text-slate-400">
                Having the full description alongside your tailored CVs helps you reuse language, double-check requirements,
                and justify each status change on the kanban board.
            </p>
            <ul class="space-y-3 text-sm text-slate-300">
                <li class="flex items-start gap-2">
                    <span class="mt-1 inline-flex h-2 w-2 flex-shrink-0 rounded-full bg-indigo-400"></span>
                    Keep a reliable record of what sparked your interest in the role.
                </li>
                <li class="flex items-start gap-2">
                    <span class="mt-1 inline-flex h-2 w-2 flex-shrink-0 rounded-full bg-indigo-400"></span>
                    Speed up drafting tailored materials by referencing this saved content.
                </li>
                <li class="flex items-start gap-2">
                    <span class="mt-1 inline-flex h-2 w-2 flex-shrink-0 rounded-full bg-indigo-400"></span>
                    Share context with mentors or peers without hunting for the original listing.
                </li>
            </ul>
        </aside>
    </section>
</div>
<?php $body = ob_get_clean(); ?>
<?php include __DIR__ . '/layout.php'; ?>
