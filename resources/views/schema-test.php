<?php
/** @var string $title */
/** @var array{passed: bool, results: array<int, array{table: string, exists: bool, missing_columns: array<int, string>, unexpected_columns: array<int, string>, error: string|null, is_valid: bool}>} $report */

$fullWidth = true;
$subtitle = 'Schema health in a single click';
$navLinks = [
    ['href' => '/', 'label' => 'Dashboard', 'current' => false],
    ['href' => '/tailor', 'label' => 'Tailor CV & letter', 'current' => false],
    ['href' => '/documents', 'label' => 'Documents', 'current' => false],
    ['href' => '/applications', 'label' => 'Applications', 'current' => false],
    ['href' => '/profile/contact-details', 'label' => 'Contact details', 'current' => false],
    ['href' => '/usage', 'label' => 'Usage', 'current' => false],
    ['href' => '/retention', 'label' => 'Retention', 'current' => false],
    ['href' => '/settings/schema-test', 'label' => 'Schema test', 'current' => true],
];
?>
<?php ob_start(); ?>
<div class="space-y-10">
    <header class="space-y-3">
        <p class="text-sm uppercase tracking-[0.3em] text-indigo-400">Diagnostics</p>
        <div class="space-y-2">
            <h1 class="text-4xl font-semibold text-slate-50 sm:text-5xl">Database schema test</h1>
            <p class="max-w-2xl text-base text-slate-300">
                Run this test directly in production to confirm every required table and column is available before shipping new features.
            </p>
        </div>
    </header>

    <section class="space-y-4">
        <?php if ($report['passed']) : ?>
            <div class="flex items-center gap-3 rounded-2xl border border-emerald-500/40 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100 shadow-lg">
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-emerald-500/20">
                    <svg aria-hidden="true" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path d="M5 13l4 4L19 7" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                </span>
                <p class="text-sm font-medium">
                    All tables look healthy. No action required.
                </p>
            </div>
        <?php else : ?>
            <div class="space-y-2 rounded-2xl border border-rose-500/40 bg-rose-500/10 px-4 py-4 text-sm text-rose-100 shadow-lg">
                <div class="flex items-center gap-3">
                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-rose-500/20">
                        <svg aria-hidden="true" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path d="M12 9v4" stroke-linecap="round" stroke-linejoin="round"></path>
                            <path d="M12 17h.01" stroke-linecap="round" stroke-linejoin="round"></path>
                            <path d="M10.29 3.86L1.82 18a1 1 0 00.86 1.5h18.64a1 1 0 00.86-1.5L13.71 3.86a1 1 0 00-1.72 0z" stroke-linecap="round" stroke-linejoin="round"></path>
                        </svg>
                    </span>
                    <p class="text-sm font-semibold">
                        Some tables need attention. Review the breakdown below and rerun after correcting the schema.
                    </p>
                </div>
            </div>
        <?php endif; ?>

        <div class="grid gap-4 lg:grid-cols-2">
            <?php foreach ($report['results'] as $tableResult) : ?>
                <?php
                $isValid = $tableResult['is_valid'];
                $panelClasses = $isValid
                    ? 'border-emerald-500/30 bg-emerald-500/5'
                    : 'border-rose-500/40 bg-rose-500/5';
                ?>
                <article class="rounded-2xl border <?= $panelClasses ?> p-5 shadow-lg">
                    <header class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-100"><?= htmlspecialchars($tableResult['table'], ENT_QUOTES) ?></h2>
                            <p class="text-xs uppercase tracking-[0.25em] text-slate-400">Schema status</p>
                        </div>
                        <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold <?= $isValid ? 'bg-emerald-500/20 text-emerald-100' : 'bg-rose-500/20 text-rose-100' ?>">
                            <?php if ($isValid) : ?>
                                <svg aria-hidden="true" class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path d="M5 13l4 4L19 7" stroke-linecap="round" stroke-linejoin="round"></path>
                                </svg>
                                Passing
                            <?php else : ?>
                                <svg aria-hidden="true" class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path d="M6 18L18 6" stroke-linecap="round" stroke-linejoin="round"></path>
                                    <path d="M6 6l12 12" stroke-linecap="round" stroke-linejoin="round"></path>
                                </svg>
                                Needs fixes
                            <?php endif; ?>
                        </span>
                    </header>

                    <dl class="mt-4 space-y-3 text-sm text-slate-300">
                        <div class="flex items-start justify-between gap-3">
                            <dt class="text-slate-400">Exists in database</dt>
                            <dd class="font-medium text-slate-100"><?= $tableResult['exists'] ? 'Yes' : 'No' ?></dd>
                        </div>
                        <?php if ($tableResult['error'] !== null) : ?>
                            <div class="rounded-lg border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-xs text-amber-100">
                                <?= htmlspecialchars($tableResult['error'], ENT_QUOTES) ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($tableResult['missing_columns'] !== []) : ?>
                            <div>
                                <dt class="text-slate-400">Missing columns</dt>
                                <dd class="mt-1 font-medium text-rose-200">
                                    <?= htmlspecialchars(implode(', ', $tableResult['missing_columns']), ENT_QUOTES) ?>
                                </dd>
                            </div>
                        <?php endif; ?>
                        <?php if ($tableResult['unexpected_columns'] !== []) : ?>
                            <div>
                                <dt class="text-slate-400">Unexpected columns</dt>
                                <dd class="mt-1 font-medium text-amber-200">
                                    <?= htmlspecialchars(implode(', ', $tableResult['unexpected_columns']), ENT_QUOTES) ?>
                                </dd>
                            </div>
                        <?php endif; ?>
                        <?php if ($tableResult['missing_columns'] === [] && $tableResult['unexpected_columns'] === [] && $tableResult['error'] === null) : ?>
                            <p class="text-xs text-slate-400">All expected columns are present.</p>
                        <?php endif; ?>
                    </dl>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
</div>
<?php $body = ob_get_clean(); ?>
<?php include __DIR__ . '/layout.php'; ?>
