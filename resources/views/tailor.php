<?php
/** @var string $title */
/** @var string $subtitle */
/** @var string $email */
/** @var array<int, array<string, mixed>> $jobDocuments */
/** @var array<int, array<string, mixed>> $cvDocuments */
/** @var array<int, array<string, mixed>> $generations */
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
            <h2 class="mt-2 text-3xl font-semibold tracking-tight text-white">Tailor a CV</h2>
            <p class="mt-2 text-base text-slate-400">
                Choose a job description, pick the seed CV, set the AI parameters, and queue the request for processing.
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
        <div class="mt-6 overflow-hidden rounded-xl border border-slate-800/80">
            <table class="min-w-full divide-y divide-slate-800 text-left text-sm text-slate-300">
                <thead class="bg-slate-900/80 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Requested</th>
                        <th class="px-4 py-3">Job description</th>
                        <th class="px-4 py-3">CV</th>
                        <th class="px-4 py-3">Model</th>
                        <th class="px-4 py-3">Thinking time</th>
                        <th class="px-4 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    <template x-if="generations.length === 0">
                        <tr>
                            <td colspan="6" class="px-4 py-5 text-center text-sm text-slate-400">
                                No generations yet. Your queued requests will appear here.
                            </td>
                        </tr>
                    </template>
                    <template x-for="item in generations" :key="item.id">
                        <tr class="hover:bg-slate-800/40">
                            <td class="px-4 py-4 text-slate-300" x-text="formatDateTime(item.created_at)"></td>
                            <td class="px-4 py-4 font-medium text-slate-200" x-text="item.job_document && item.job_document.filename ? item.job_document.filename : ''"></td>
                            <td class="px-4 py-4" x-text="item.cv_document && item.cv_document.filename ? item.cv_document.filename : ''"></td>
                            <td class="px-4 py-4" x-text="item.model"></td>
                            <td class="px-4 py-4" x-text="(item.thinking_time || 0) + 's'"></td>
                            <td class="px-4 py-4">
                                <span
                                    class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-wide"
                                    :class="item.status === 'queued' ? 'text-amber-200 border-amber-400/30 bg-amber-500/10' : 'text-emerald-200 border-emerald-400/30 bg-emerald-500/10'"
                                    x-text="item.status"
                                ></span>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </section>
</div>
<?php $body = ob_get_clean(); ?>
<?php include __DIR__ . '/layout.php'; ?>
