<?php
/** @var string $title */
/** @var string $subtitle */
/** @var string $email */
/** @var array<int, array<string, mixed>> $jobDocuments */
/** @var array<int, array<string, mixed>> $cvDocuments */
/** @var array<int, array<string, mixed>> $generations */
/** @var array<int, array<string, mixed>> $generationLogs */
/** @var array<int, array<string, mixed>> $modelOptions */
/** @var array<int, array{href: string, label: string, current: bool}> $navLinks */
/** @var string|null $csrfToken */
/** @var string $defaultPrompt */

$fullWidth = true;

$wizardSteps = [
    [
        'index' => 1,
        'title' => 'Choose job description',
        'summary' => 'Select the job description you want to tailor for.',
        'helper' => 'Pick the posting that matches the upcoming application.',
    ],
    [
        'index' => 2,
        'title' => 'Choose CV',
        'summary' => 'Select the CV that provides the best foundation.',
        'helper' => 'Use the document that already aligns with the role.',
    ],
    [
        'index' => 3,
        'title' => 'Set parameters',
        'summary' => 'Adjust the AI model and thinking time.',
        'helper' => 'Higher thinking time gives GPT-5 more reasoning space.',
    ],
    [
        'index' => 4,
        'title' => 'Confirm & queue',
        'summary' => 'Review your selections before submission.',
        'helper' => 'Submit when everything looks correct.',
    ],
];

$wizardState = [
    'jobDocuments' => $jobDocuments,
    'cvDocuments' => $cvDocuments,
    'models' => $modelOptions,
    'generations' => $generations,
    'logs' => $generationLogs,
    'steps' => $wizardSteps,
    'defaultThinkingTime' => 30,
    'prompt' => $defaultPrompt,
];

$wizardJson = htmlspecialchars(
    json_encode($wizardState, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

$additionalHead = '<script src="/assets/js/tailor.js" defer></script>';
?>
<?php ob_start(); ?>

<div
    x-data="tailorWizard(<?= $wizardJson ?>)"
    x-init="initialise()"
    class="space-y-12"
>
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <p class="text-sm uppercase tracking-widest text-indigo-400">
                Signed in as <?= htmlspecialchars($email, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
            </p>
            <h2 class="mt-2 text-3xl font-semibold tracking-tight text-white">Tailor a CV and cover letter</h2>
            <p class="mt-2 text-base text-slate-400">
                Choose a job description, pick the seed CV, set the AI parameters, and queue the request to generate both documents.
            </p>
        </div>
        <div class="flex flex-col gap-3 md:items-end">
            <a
                href="/"
                class="inline-flex items-center gap-2 rounded-lg border border-slate-700 px-4 py-2 text-sm font-medium text-slate-200 transition hover:border-slate-500 hover:bg-slate-800"
            >
                ← Back to dashboard
            </a>
            <form method="post" action="/auth/logout" class="md:self-end">
                <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                <button
                    type="submit"
                    class="inline-flex items-center justify-center rounded-lg border border-slate-700 px-4 py-2 text-sm font-medium text-slate-200 transition hover:border-slate-500 hover:bg-slate-800"
                >
                    Sign out
                </button>
            </form>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-[320px,1fr]">
        <aside class="rounded-2xl border border-slate-800/80 bg-slate-900/70 p-6">
            <ol class="space-y-4">
                <?php foreach ($wizardSteps as $stepItem) : ?>
                    <?php $stepIndex = (int) $stepItem['index']; ?>
                    <li class="flex items-start gap-3">
                        <button
                            type="button"
                            class="flex w-full items-start gap-3 text-left"
                            :class="canAccessStep(<?= $stepIndex ?>) ? (step === <?= $stepIndex ?> ? 'text-white' : 'text-slate-500 hover:text-slate-300 transition') : 'cursor-not-allowed text-slate-700'"
                            :disabled="!canAccessStep(<?= $stepIndex ?>)"
                            :aria-disabled="canAccessStep(<?= $stepIndex ?>) ? 'false' : 'true'"
                            @click="goTo(<?= $stepIndex ?>)"
                        >
                            <span
                                class="flex h-9 w-9 items-center justify-center rounded-full border"
                                :class="step === <?= $stepIndex ?> ? 'border-indigo-400 bg-indigo-500/20 text-indigo-200' : (canAccessStep(<?= $stepIndex ?>) ? 'border-slate-700' : 'border-slate-800/80')"
                            >
                                <?= $stepIndex ?>
                            </span>
                            <div>
                                <p class="text-sm font-semibold">
                                    <?= htmlspecialchars($stepItem['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                                </p>
                                <p class="text-xs text-slate-500">
                                    <?= htmlspecialchars($stepItem['summary'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                                </p>
                            </div>
                        </button>
                    </li>
                <?php endforeach; ?>
            </ol>
        </aside>

        <section
            x-ref="panel"
            id="tailor-wizard"
            tabindex="-1"
            class="rounded-2xl border border-slate-800/80 bg-slate-900/70 shadow-xl"
        >
            <?php $initialStep = $wizardSteps[0] ?? ['title' => 'Tailor your application', 'helper' => 'Follow the steps to queue a tailored CV.']; ?>
            <div class="border-b border-slate-800/60 px-6 py-4">
                <h3 class="text-lg font-semibold text-white" x-text="activeStep ? activeStep.title : ''">
                    <?= htmlspecialchars($initialStep['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                </h3>
                <p class="text-sm text-slate-400" x-text="activeStep ? activeStep.helper : ''">
                    <?= htmlspecialchars($initialStep['helper'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                </p>
            </div>
            <div class="space-y-6 px-6 py-6">
                <template x-if="isDisabled">
                    <div class="rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 text-sm text-amber-200">
                        Upload at least one job description and CV to begin.
                    </div>
                </template>

                <div x-show="step === 1" x-cloak class="space-y-4">
                    <template x-if="jobDocuments.length === 0">
                        <p class="rounded-xl border border-slate-700 bg-slate-800/50 p-5 text-sm text-slate-300">
                            You do not have any job descriptions yet. Upload one to start tailoring.
                        </p>
                    </template>
                    <template x-for="job in jobDocuments" :key="job.id">
                        <button
                            type="button"
                            class="flex w-full flex-col gap-1 rounded-xl border px-4 py-3 text-left transition"
                            :class="form.job_document_id === job.id ? 'border-indigo-400 bg-indigo-500/10 text-white' : 'border-slate-700 hover:border-slate-500 hover:bg-slate-800/40'"
                            @click="selectJob(job.id)"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-500/20 text-indigo-200">
                                        <svg aria-hidden="true" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                            <path d="M4 5h16M4 12h16M4 19h16" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </span>
                                    <div>
                                        <p class="font-medium" x-text="job.filename"></p>
                                        <p class="text-xs text-slate-400">
                                            Added <span x-text="formatDate(job.created_at)"></span>
                                        </p>
                                    </div>
                                </div>
                                <span class="text-xs uppercase tracking-wide" :class="form.job_document_id === job.id ? 'text-indigo-300' : 'text-slate-500'">
                                    Select
                                </span>
                            </div>
                        </button>
                    </template>
                </div>

                <div x-show="step === 2" x-cloak class="space-y-4">
                    <template x-if="cvDocuments.length === 0">
                        <p class="rounded-xl border border-slate-700 bg-slate-800/50 p-5 text-sm text-slate-300">
                            Upload a CV to continue.
                        </p>
                    </template>
                    <template x-for="cv in cvDocuments" :key="cv.id">
                        <button
                            type="button"
                            class="flex w-full flex-col gap-1 rounded-xl border px-4 py-3 text-left transition"
                            :class="form.cv_document_id === cv.id ? 'border-indigo-400 bg-indigo-500/10 text-white' : 'border-slate-700 hover:border-slate-500 hover:bg-slate-800/40'"
                            @click="selectCv(cv.id)"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-500/20 text-emerald-200">
                                        <svg aria-hidden="true" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                            <path d="M5 7l5-4 5 4M5 17l5 4 5-4" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </span>
                                    <div>
                                        <p class="font-medium" x-text="cv.filename"></p>
                                        <p class="text-xs text-slate-400">
                                            Added <span x-text="formatDate(cv.created_at)"></span>
                                        </p>
                                    </div>
                                </div>
                                <span class="text-xs uppercase tracking-wide" :class="form.cv_document_id === cv.id ? 'text-indigo-300' : 'text-slate-500'">
                                    Select
                                </span>
                            </div>
                        </button>
                    </template>
                </div>

                <div x-show="step === 3" x-cloak class="space-y-6">
                    <div class="space-y-3">
                        <p class="text-sm font-medium text-slate-200">Model</p>
                        <div class="grid gap-3 md:grid-cols-3">
                            <template x-for="model in models" :key="model.value">
                                <button
                                    type="button"
                                    class="w-full rounded-xl border px-4 py-3 text-left text-sm transition"
                                    :class="form.model === model.value ? 'border-indigo-400 bg-indigo-500/10 text-white' : 'border-slate-700 hover:border-slate-500 hover:bg-slate-800/40'"
                                    @click="setModel(model.value)"
                                >
                                    <p class="font-semibold" x-text="model.label"></p>
                                    <p class="mt-1 text-xs text-slate-400" x-text="model.value"></p>
                                </button>
                            </template>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <div class="flex items-center justify-between text-sm text-slate-200">
                            <span>Thinking time (seconds)</span>
                            <span class="font-semibold text-indigo-200" x-text="form.thinking_time + 's'"></span>
                        </div>
                        <input
                            type="range"
                            min="5"
                            max="60"
                            step="5"
                            x-model.number="form.thinking_time"
                            class="w-full accent-indigo-500"
                        >
                        <p class="text-xs text-slate-400">
                            Give GPT-5 more time for complex roles. Thirty seconds is a balanced default.
                        </p>
                    </div>
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-slate-200">AI prompt</p>
                            <span class="text-xs text-slate-500">Edit before queuing if needed.</span>
                        </div>
                        <textarea
                            x-model.trim="form.prompt"
                            rows="10"
                            class="min-h-[200px] w-full rounded-xl border border-slate-700 bg-slate-950/40 px-4 py-3 text-sm text-slate-100 focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
                        ><?= htmlspecialchars($defaultPrompt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></textarea>
                        <p class="text-xs text-slate-400">
                            Review or customise the instructions guiding the AI. The default prompt keeps outputs accurate and grounded.
                        </p>
                    </div>
                </div>

                <div x-show="step === 4" x-cloak class="space-y-4">
                    <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-4">
                        <h4 class="text-sm font-semibold text-slate-200">Review selections</h4>
                        <dl class="mt-3 space-y-2 text-sm text-slate-300">
                            <div class="flex items-start justify-between gap-4">
                                <dt class="text-slate-400">Job description</dt>
                                <dd class="font-medium text-right" x-text="selectedJob ? selectedJob.filename : 'None selected'"></dd>
                            </div>
                            <div class="flex items-start justify-between gap-4">
                                <dt class="text-slate-400">CV</dt>
                                <dd class="font-medium text-right" x-text="selectedCv ? selectedCv.filename : 'None selected'"></dd>
                            </div>
                            <div class="flex items-start justify-between gap-4">
                                <dt class="text-slate-400">Model</dt>
                                <dd class="font-medium text-right" x-text="selectedModelLabel"></dd>
                            </div>
                            <div class="flex items-start justify-between gap-4">
                                <dt class="text-slate-400">Thinking time</dt>
                                <dd class="font-medium text-right" x-text="form.thinking_time + 's'"></dd>
                            </div>
                        </dl>
                    </div>
                    <div class="rounded-xl border border-indigo-500/30 bg-indigo-500/10 p-4 text-sm text-indigo-100">
                        Once submitted, the request appears in your queue with the status <strong class="font-semibold">queued</strong> until processing begins.
                    </div>
                </div>

                <div class="flex flex-col gap-3 border-t border-slate-800/60 pt-4 sm:flex-row sm:justify-between">
                    <div class="space-y-2">
                        <template x-if="errorMessage">
                            <div class="rounded-lg border border-rose-500/40 bg-rose-500/10 px-3 py-2 text-sm text-rose-200" x-text="errorMessage"></div>
                        </template>
                        <template x-if="successMessage">
                            <div class="rounded-lg border border-emerald-500/40 bg-emerald-500/10 px-3 py-2 text-sm text-emerald-200" x-text="successMessage"></div>
                        </template>
                    </div>
                    <div class="flex flex-col gap-3 sm:flex-row">
                        <button
                            type="button"
                            class="rounded-lg border border-slate-700 px-4 py-2 text-sm font-medium text-slate-300 transition hover:border-slate-500 hover:bg-slate-800/60"
                            @click="previous()"
                            :disabled="step === 1"
                            :class="step === 1 ? 'cursor-not-allowed opacity-40' : ''"
                        >
                            Back
                        </button>
                        <template x-if="step < steps.length">
                            <button
                                type="button"
                                class="rounded-lg bg-indigo-500 px-5 py-2 text-sm font-semibold text-white transition hover:bg-indigo-400 disabled:cursor-not-allowed disabled:opacity-50"
                                @click="next()"
                                :disabled="!canContinue || isDisabled"
                            >
                                Continue
                            </button>
                        </template>
                        <template x-if="step === steps.length">
                            <button
                                type="button"
                                class="rounded-lg bg-indigo-500 px-5 py-2 text-sm font-semibold text-white transition hover:bg-indigo-400 disabled:cursor-not-allowed disabled:opacity-50"
                                @click="queue()"
                                :disabled="isSubmitting || isDisabled || !canSubmit"
                            >
                                <span x-show="!isSubmitting">Confirm &amp; queue</span>
                                <span x-show="isSubmitting" class="inline-flex items-center gap-2">
                                    <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                        <path d="M12 4v2m4.24.76l-1.42 1.42M20 12h-2m-.76 4.24l-1.42-1.42M12 20v-2m-4.24-.76l1.42-1.42M4 12h2m.76-4.24l1.42 1.42" stroke-linecap="round" stroke-linejoin="round"></path>
                                    </svg>
                                    Queuing…
                                </span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <section class="rounded-2xl border border-slate-800/80 bg-slate-900/70 p-6 shadow-xl">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h3 class="text-xl font-semibold text-white">Recent generations</h3>
                <p class="text-sm text-slate-400">Track each request from submission through completion.</p>
            </div>
        </div>
        <div class="mt-6 space-y-4">
            <template x-if="generations.length === 0">
                <p class="rounded-xl border border-slate-800 bg-slate-900/60 p-5 text-sm text-slate-400">
                    No generations yet. Your queued requests will appear here.
                </p>
            </template>
            <template x-for="item in generations" :key="item.id">
                <article class="rounded-xl border border-slate-800/80 bg-slate-900/60 p-4 text-sm text-slate-300">
                    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                        <div class="flex flex-1 items-start gap-3">
                            <span
                                class="flex h-10 w-10 items-center justify-center rounded-full border"
                                :class="item.status === 'queued'
                                    ? 'border-amber-400/40 bg-amber-500/10 text-amber-200'
                                    : (item.status === 'failed'
                                        ? 'border-rose-400/40 bg-rose-500/10 text-rose-200'
                                        : (item.status === 'cancelled'
                                            ? 'border-slate-600/60 bg-slate-800/60 text-slate-200'
                                            : 'border-emerald-400/40 bg-emerald-500/10 text-emerald-200'))"
                                aria-hidden="true"
                            >
                                <template x-if="item.status === 'completed'">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                        <path d="m5 13 4 4L19 7" stroke-linecap="round" stroke-linejoin="round"></path>
                                    </svg>
                                </template>
                                <template x-if="item.status === 'queued'">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                        <path d="M12 6v6l3 3" stroke-linecap="round" stroke-linejoin="round"></path>
                                    </svg>
                                </template>
                                <template x-if="item.status === 'failed'">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                        <path d="M6 6l12 12M6 18 18 6" stroke-linecap="round" stroke-linejoin="round"></path>
                                    </svg>
                                </template>
                                <template x-if="item.status === 'cancelled'">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                        <path d="m7 7 10 10M7 17 17 7" stroke-linecap="round" stroke-linejoin="round"></path>
                                    </svg>
                                </template>
                            </span>
                            <div class="space-y-2">
                                <span
                                    class="inline-flex items-center gap-2 rounded-full border px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide"
                                    :class="item.status === 'queued'
                                        ? 'text-amber-200 border-amber-400/30 bg-amber-500/10'
                                        : (item.status === 'failed'
                                            ? 'text-rose-200 border-rose-400/40 bg-rose-500/10'
                                            : (item.status === 'cancelled'
                                                ? 'text-slate-200 border-slate-500/40 bg-slate-800/50'
                                                : 'text-emerald-200 border-emerald-400/30 bg-emerald-500/10'))"
                                >
                                    <svg class="h-2.5 w-2.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                        <circle cx="12" cy="12" r="6"></circle>
                                    </svg>
                                    <span x-text="item.status"></span>
                                </span>
                                <h4 class="text-sm font-semibold text-white">
                                    <span x-text="item.job_document && item.job_document.filename ? item.job_document.filename : 'Manual submission'"></span>
                                </h4>
                                <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-slate-400">
                                    <span class="inline-flex items-center gap-1">
                                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                            <path d="M8 7V5a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2" stroke-linecap="round" stroke-linejoin="round"></path>
                                            <path d="M5 11h14" stroke-linecap="round" stroke-linejoin="round"></path>
                                            <path d="M6 9h12v10a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V9Z" stroke-linecap="round" stroke-linejoin="round"></path>
                                        </svg>
                                        <span x-text="formatDateTime(item.created_at)"></span>
                                    </span>
                                    <template x-if="item.cv_document && item.cv_document.filename">
                                        <span class="inline-flex items-center gap-1">
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                                <path d="M4 4h9l5 5v11a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1Z" stroke-linecap="round" stroke-linejoin="round"></path>
                                                <path d="M13 4v5h5" stroke-linecap="round" stroke-linejoin="round"></path>
                                            </svg>
                                            <span x-text="item.cv_document.filename"></span>
                                        </span>
                                    </template>
                                    <span class="inline-flex items-center gap-1">
                                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                            <path d="M7 4h10l3 6-8 10-8-10 3-6Z" stroke-linecap="round" stroke-linejoin="round"></path>
                                        </svg>
                                        <span x-text="item.model"></span>
                                    </span>
                                    <span class="inline-flex items-center gap-1">
                                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                            <path d="M12 6v6l2.5 2.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                            <circle cx="12" cy="12" r="9" stroke-linecap="round" stroke-linejoin="round"></circle>
                                        </svg>
                                        <span x-text="(item.thinking_time || 0) + 's'"></span>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-col items-stretch justify-end gap-3 md:items-end">
                            <template x-if="item.status === 'completed' && Array.isArray(item.downloads) && item.downloads.length > 0">
                                <div class="flex flex-col items-stretch gap-2 md:items-end">
                                    <template x-for="group in item.downloads" :key="group.artifact">
                                        <div class="flex flex-col items-stretch gap-1 md:items-end">
                                            <p class="text-[10px] font-semibold uppercase tracking-wide text-emerald-300" x-text="group.label"></p>
                                            <div class="flex flex-wrap justify-start gap-1 md:justify-end">
                                                <template x-for="(link, format) in group.links" :key="format">
                                                    <a
                                                        :href="link"
                                                        class="inline-flex items-center gap-1 rounded-lg border border-emerald-400/40 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-emerald-100 transition hover:border-emerald-200 hover:text-emerald-50"
                                                    >
                                                        <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                                            <path d="M12 4v12" stroke-linecap="round" stroke-linejoin="round"></path>
                                                            <path d="m8 12 4 4 4-4" stroke-linecap="round" stroke-linejoin="round"></path>
                                                            <path d="M6 18h12" stroke-linecap="round" stroke-linejoin="round"></path>
                                                        </svg>
                                                        <span x-text="downloadLabel(format)"></span>
                                                    </a>
                                                </template>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </template>
                            <template x-if="item.status === 'queued'">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-lg border border-slate-700 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-200 transition hover:border-rose-400/40 hover:bg-rose-500/10 hover:text-rose-100"
                                    @click="cancelGeneration(item.id)"
                                    :disabled="isCancellingGeneration(item.id)"
                                    :class="isCancellingGeneration(item.id) ? 'cursor-not-allowed opacity-60' : ''"
                                >
                                    <span x-show="!isCancellingGeneration(item.id)" class="inline-flex items-center gap-1">
                                        <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                            <path d="m6 6 12 12M6 18 18 6" stroke-linecap="round" stroke-linejoin="round"></path>
                                        </svg>
                                        Cancel
                                    </span>
                                    <span x-show="isCancellingGeneration(item.id)" class="inline-flex items-center gap-2">
                                        <svg class="h-3 w-3 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                            <path d="M12 4v2m4.24.76l-1.42 1.42M20 12h-2m-.76 4.24l-1.42-1.42M12 20v-2m-4.24-.76l1.42-1.42M4 12h2m.76-4.24l1.42 1.42" stroke-linecap="round" stroke-linejoin="round"></path>
                                        </svg>
                                        Removing…
                                    </span>
                                </button>
                            </template>
                        </div>
                    </div>
                </article>
            </template>
        </div>
    </section>

    <section class="rounded-2xl border border-slate-800/80 bg-slate-900/70 p-6 shadow-xl">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h3 class="text-xl font-semibold text-white">Processing logs</h3>
                <p class="text-sm text-slate-400">Review recent failures recorded while tailoring CVs.</p>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <template x-if="processingLogs.length">
                    <span class="rounded-full border border-rose-400/40 bg-rose-500/10 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-rose-200">
                        <span x-text="processingLogs.length"></span>
                        <span class="ml-1">issue<span x-text="processingLogs.length === 1 ? '' : 's'"></span></span>
                    </span>
                </template>
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg border border-slate-700 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-200 transition hover:border-indigo-400/40 hover:bg-indigo-500/10 hover:text-indigo-100"
                    @click="cleanupTailoringData()"
                    :disabled="isCleaning"
                    :class="isCleaning ? 'cursor-not-allowed opacity-60' : ''"
                >
                    <span x-show="!isCleaning">Clean up logs &amp; jobs</span>
                    <span x-show="isCleaning" class="inline-flex items-center gap-2">
                        <svg class="h-3 w-3 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M12 4v2m4.24.76l-1.42 1.42M20 12h-2m-.76 4.24l-1.42-1.42M12 20v-2m-4.24-.76l1.42-1.42M4 12h2m.76-4.24l1.42 1.42" stroke-linecap="round" stroke-linejoin="round"></path>
                        </svg>
                        Cleaning…
                    </span>
                </button>
            </div>
        </div>
        <div class="mt-6 space-y-4">
            <template x-if="processingLogs.length === 0">
                <p class="rounded-xl border border-slate-800 bg-slate-900/60 p-5 text-sm text-slate-400">
                    No processing issues recorded. This list updates when the queue reports a problem.
                </p>
            </template>
            <template x-for="log in processingLogs" :key="log.id">
                <article class="space-y-3 rounded-xl border border-rose-500/30 bg-rose-500/5 p-5 text-sm text-slate-200">
                    <header class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div class="space-y-1">
                            <p class="font-semibold text-white" x-text="log.message || 'Processing log'"></p>
                            <p class="text-xs text-slate-400">
                                <span x-text="formatDateTime(log.created_at)"></span>
                                <template x-if="log.generation_id">
                                    <span>
                                        · Generation <span x-text="'#' + log.generation_id"></span>
                                    </span>
                                </template>
                            </p>
                        </div>
                        <span class="inline-flex items-center rounded-full border border-rose-400/40 bg-rose-500/20 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-rose-100">
                            Failure
                        </span>
                    </header>
                    <template x-if="log.error">
                        <p class="rounded-lg border border-rose-400/40 bg-rose-500/15 p-3 text-xs text-rose-100" x-text="log.error"></p>
                    </template>
                </article>
            </template>
        </div>
    </section>
</div>
<?php $body = ob_get_clean(); ?>
<?php include __DIR__ . '/layout.php'; ?>
