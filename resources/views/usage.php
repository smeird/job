<?php
/** @var string $title */

$fullWidth = true;
$subtitle = 'Spend and token insight';
$navLinks = [
    ['href' => '/', 'label' => 'Dashboard', 'current' => false],
    ['href' => '/documents', 'label' => 'Documents', 'current' => false],
    ['href' => '/usage', 'label' => 'Usage', 'current' => true],
    ['href' => '/retention', 'label' => 'Retention', 'current' => false],
];
$additionalHead = <<<'HTML'
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tabulator-tables@5.5.2/dist/css/tabulator.min.css">
    <script src="https://cdn.jsdelivr.net/npm/tabulator-tables@5.5.2/dist/js/tabulator.min.js" defer></script>
    <script src="https://code.highcharts.com/highcharts.js" defer></script>
    <script src="/assets/js/usage.js" defer></script>
HTML;
?>
<?php ob_start(); ?>
<div class="space-y-10">
    <header class="space-y-4">
        <p class="text-sm uppercase tracking-[0.2em] text-indigo-400">Insights</p>
        <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
            <div class="space-y-2">
                <h1 class="text-4xl font-semibold text-slate-50 sm:text-5xl">Usage analytics</h1>
                <p class="max-w-2xl text-lg text-slate-300">
                    Monitor every run, keep an eye on spend, and understand how your team is engaging with language models.
                </p>
            </div>
            <div class="flex items-center gap-2 rounded-full border border-indigo-500/30 bg-indigo-500/10 px-4 py-2 text-sm text-indigo-200">
                <span class="inline-flex h-2 w-2 rounded-full bg-emerald-400"></span>
                <span>Live meter</span>
            </div>
        </div>
    </header>

    <section class="grid gap-6 lg:grid-cols-2">
        <article class="rounded-3xl border border-white/5 bg-slate-900/60 p-6 shadow-[0_24px_60px_-40px_rgba(15,23,42,0.9)]">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold uppercase tracking-[0.3em] text-slate-400">This month</h2>
                <span class="rounded-full bg-emerald-500/10 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-emerald-300">Current</span>
            </div>
            <div class="mt-6 space-y-5">
                <p class="text-4xl font-semibold text-slate-50" data-summary="month-cost">£0.00</p>
                <dl class="space-y-2 text-sm text-slate-300">
                    <div class="flex justify-between">
                        <dt>Prompt tokens</dt>
                        <dd data-summary="month-prompt">0</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt>Completion tokens</dt>
                        <dd data-summary="month-completion">0</dd>
                    </div>
                    <div class="flex justify-between text-slate-200">
                        <dt>Total tokens</dt>
                        <dd data-summary="month-total">0</dd>
                    </div>
                </dl>
            </div>
        </article>

        <article class="rounded-3xl border border-white/5 bg-slate-900/40 p-6 shadow-[0_24px_60px_-50px_rgba(15,23,42,0.65)]">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold uppercase tracking-[0.3em] text-slate-400">Lifetime</h2>
                <span class="rounded-full bg-slate-500/10 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-300">All time</span>
            </div>
            <div class="mt-6 space-y-5">
                <p class="text-4xl font-semibold text-slate-50" data-summary="lifetime-cost">£0.00</p>
                <dl class="space-y-2 text-sm text-slate-300">
                    <div class="flex justify-between">
                        <dt>Prompt tokens</dt>
                        <dd data-summary="lifetime-prompt">0</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt>Completion tokens</dt>
                        <dd data-summary="lifetime-completion">0</dd>
                    </div>
                    <div class="flex justify-between text-slate-200">
                        <dt>Total tokens</dt>
                        <dd data-summary="lifetime-total">0</dd>
                    </div>
                </dl>
            </div>
        </article>
    </section>

    <section class="space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-semibold text-slate-100">Per-run breakdown</h2>
                <p class="text-sm text-slate-400">Every API call with model usage, tokens in/out, and precise spend.</p>
            </div>
        </div>
        <div class="rounded-3xl border border-white/5 bg-slate-900/60 p-4 shadow-[0_20px_45px_-40px_rgba(15,23,42,0.9)]">
            <div id="usage-table" class="tabulator text-slate-900"></div>
            <p data-empty-state class="mt-4 hidden text-sm text-slate-400">No usage recorded yet. Calls will appear here as they complete.</p>
            <p data-error-state class="mt-4 hidden text-sm text-rose-300">We couldn't load usage right now. Please refresh to try again.</p>
        </div>
    </section>

    <section class="grid gap-6 lg:grid-cols-2">
        <article class="space-y-3 rounded-3xl border border-white/5 bg-slate-900/60 p-6 shadow-[0_24px_60px_-50px_rgba(15,23,42,0.7)]">
            <header class="space-y-1">
                <h2 class="text-xl font-semibold text-slate-100">Tokens by month</h2>
                <p class="text-sm text-slate-400">Track total tokens processed each month to spot trends.</p>
            </header>
            <div id="usage-tokens-chart" class="h-64"></div>
        </article>
        <article class="space-y-3 rounded-3xl border border-white/5 bg-slate-900/60 p-6 shadow-[0_24px_60px_-50px_rgba(15,23,42,0.7)]">
            <header class="space-y-1">
                <h2 class="text-xl font-semibold text-slate-100">Cost by month</h2>
                <p class="text-sm text-slate-400">Model spend in GBP to keep budgets healthy.</p>
            </header>
            <div id="usage-cost-chart" class="h-64"></div>
        </article>
    </section>
</div>
<?php $body = ob_get_clean(); ?>
<?php include __DIR__ . '/layout.php'; ?>
