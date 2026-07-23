<?php
/** @var array<int, array{value: string, label: string, description: string}> $models */
/** @var string $planModel */
/** @var string $draftModel */
/** @var string|null $refreshedAt */
/** @var string|null $status */
/** @var string|null $error */
/** @var string|null $csrfToken */
?>
<?php ob_start(); ?>
<div class="space-y-8">
    <header class="max-w-3xl space-y-3">
        <p class="text-sm font-medium uppercase tracking-[0.2em] text-indigo-300">Settings</p>
        <h2 class="text-3xl font-semibold tracking-tight text-white">OpenAI models</h2>
        <p class="text-base text-slate-400">
            Choose the defaults used to analyse job requirements and draft application documents. The Tailor screen can still override the draft model for an individual run.
        </p>
    </header>

    <?php if (!empty($status)) : ?>
        <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100">
            <?= htmlspecialchars($status, ENT_QUOTES) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)) : ?>
        <div class="rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">
            <?= htmlspecialchars($error, ENT_QUOTES) ?>
        </div>
    <?php endif; ?>

    <section class="grid gap-6 lg:grid-cols-[minmax(0,2fr),minmax(280px,1fr)]">
        <form method="post" action="/settings/models" class="space-y-6 rounded-2xl border border-slate-800 bg-slate-900/60 p-6 shadow-xl">
            <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken, ENT_QUOTES) ?>">

            <div class="space-y-2">
                <label for="plan_model" class="text-sm font-semibold text-slate-100">Analysis model</label>
                <p class="text-sm text-slate-400">Maps the job description to evidence in the source CV.</p>
                <select id="plan_model" name="plan_model" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/30">
                    <?php foreach ($models as $model) : ?>
                        <option value="<?= htmlspecialchars($model['value'], ENT_QUOTES) ?>" <?= $planModel === $model['value'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($model['label'] . ' — ' . $model['description'], ENT_QUOTES) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="space-y-2">
                <label for="draft_model" class="text-sm font-semibold text-slate-100">Default drafting model</label>
                <p class="text-sm text-slate-400">Writes the tailored CV and cover letter from the evidence map.</p>
                <select id="draft_model" name="draft_model" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/30">
                    <?php foreach ($models as $model) : ?>
                        <option value="<?= htmlspecialchars($model['value'], ENT_QUOTES) ?>" <?= $draftModel === $model['value'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($model['label'] . ' — ' . $model['description'], ENT_QUOTES) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-indigo-500 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-400">
                Save model defaults
            </button>
        </form>

        <aside class="space-y-5 rounded-2xl border border-slate-800 bg-slate-900/40 p-6">
            <div class="space-y-2">
                <h3 class="text-lg font-semibold text-white">Available models</h3>
                <p class="text-sm text-slate-400">
                    The catalogue is loaded from OpenAI and cached for six hours so new compatible GPT models can appear without a code deployment.
                </p>
            </div>
            <dl class="space-y-3 text-sm">
                <div>
                    <dt class="text-slate-500">Models found</dt>
                    <dd class="mt-1 font-medium text-slate-200"><?= count($models) ?></dd>
                </div>
                <div>
                    <dt class="text-slate-500">Last refreshed</dt>
                    <dd class="mt-1 font-medium text-slate-200"><?= htmlspecialchars($refreshedAt ?? 'Not yet refreshed from OpenAI', ENT_QUOTES) ?></dd>
                </div>
            </dl>
            <form method="post" action="/settings/models/refresh">
                <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken, ENT_QUOTES) ?>">
                <button type="submit" class="w-full rounded-lg border border-slate-600 px-4 py-2 text-sm font-semibold text-slate-200 transition hover:border-slate-400 hover:bg-slate-800">
                    Refresh from OpenAI
                </button>
            </form>
        </aside>
    </section>
</div>
<?php $body = ob_get_clean(); ?>
<?php include __DIR__ . '/layout.php'; ?>
