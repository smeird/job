<?php
/** @var string $title */
/** @var string $subtitle */
/** @var string $email */
/** @var array<int, array<string, mixed>> $jobDocuments */
/** @var array<int, array<string, mixed>> $cvDocuments */
/** @var array<int, array<string, mixed>> $generations */
/** @var array<int, array<string, mixed>> $modelOptions */
/** @var array<int, array<string, mixed>> $outstandingApplications */
/** @var int $outstandingApplicationsCount */

$fullWidth = true;

$wizardState = [
    'jobDocuments' => $jobDocuments,
    'cvDocuments' => $cvDocuments,
    'models' => $modelOptions,
    'generations' => $generations,
];

$wizardJson = htmlspecialchars(
    json_encode($wizardState, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ENT_QUOTES,
    'UTF-8'
);
?>
<?php ob_start(); ?>

<div
    x-data="generationWizard(<?= $wizardJson ?>)"
    class="space-y-10"
>
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <p class="text-sm uppercase tracking-widest text-indigo-400">Signed in as <?= htmlspecialchars($email, ENT_QUOTES) ?></p>
            <h2 class="mt-2 text-3xl font-semibold tracking-tight text-white">Application tailoring</h2>
            <p class="mt-2 text-base text-slate-400">
                Queue a new generation by pairing a job description with your best CV.
                Adjust generation parameters and confirm before sending it to the AI queue.
            </p>
        </div>
        <div class="flex flex-col gap-3 md:items-end">
            <button
                type="button"
                class="inline-flex items-center justify-center rounded-lg bg-indigo-500 px-4 py-2 text-sm font-semibold text-white shadow-lg transition hover:bg-indigo-400 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-300"
                @click="startNewGeneration()"
            >
                Start a tailored CV
            </button>
            <form method="post" action="/auth/logout" class="md:self-end">
                <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                <button type="submit" class="inline-flex items-center justify-center rounded-lg border border-slate-700 px-4 py-2 text-sm font-medium text-slate-200 transition hover:border-slate-500 hover:bg-slate-800">
                    Sign out
                </button>

            </form>
        </div>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <a href="/documents" class="group flex items-center justify-between gap-3 rounded-xl border border-slate-800/80 bg-slate-900/60 px-4 py-3 text-sm font-medium text-slate-200 transition hover:border-indigo-400/60 hover:bg-indigo-500/10 hover:text-indigo-100">
            <span class="inline-flex items-center gap-2">
                <span class="rounded-full bg-indigo-500/20 px-2 py-1 text-xs uppercase tracking-wide text-indigo-200">Upload</span>
                <span>Manage documents</span>
            </span>
            <svg aria-hidden="true" class="h-4 w-4 transition group-hover:translate-x-1" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path d="M5 12h14" stroke-linecap="round" stroke-linejoin="round"></path>
                <path d="M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"></path>
            </svg>
        </a>
        <a href="/applications" class="group flex items-center justify-between gap-3 rounded-xl border border-slate-800/80 bg-slate-900/60 px-4 py-3 text-sm font-medium text-slate-200 transition hover:border-indigo-400/60 hover:bg-indigo-500/10 hover:text-indigo-100">
            <span class="inline-flex items-center gap-2">
                <span class="rounded-full bg-amber-500/20 px-2 py-1 text-xs uppercase tracking-wide text-amber-200">Track</span>
                <span>Job tracker</span>
            </span>
            <span class="text-xs font-semibold uppercase tracking-wide text-amber-200">
                <?= (int) $outstandingApplicationsCount ?> outstanding
            </span>
        </a>
        <a href="/usage" class="group flex items-center justify-between gap-3 rounded-xl border border-slate-800/80 bg-slate-900/60 px-4 py-3 text-sm font-medium text-slate-200 transition hover:border-indigo-400/60 hover:bg-indigo-500/10 hover:text-indigo-100">
            <span class="inline-flex items-center gap-2">
                <span class="rounded-full bg-emerald-500/20 px-2 py-1 text-xs uppercase tracking-wide text-emerald-200">Insights</span>
                <span>Usage analytics</span>
            </span>
            <svg aria-hidden="true" class="h-4 w-4 transition group-hover:translate-x-1" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path d="M5 12h14" stroke-linecap="round" stroke-linejoin="round"></path>
                <path d="M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"></path>
            </svg>
        </a>
        <a href="/retention" class="group flex items-center justify-between gap-3 rounded-xl border border-slate-800/80 bg-slate-900/60 px-4 py-3 text-sm font-medium text-slate-200 transition hover:border-indigo-400/60 hover:bg-indigo-500/10 hover:text-indigo-100">
            <span class="inline-flex items-center gap-2">
                <span class="rounded-full bg-sky-500/20 px-2 py-1 text-xs uppercase tracking-wide text-sky-200">Policy</span>
                <span>Retention settings</span>
            </span>
            <svg aria-hidden="true" class="h-4 w-4 transition group-hover:translate-x-1" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path d="M5 12h14" stroke-linecap="round" stroke-linejoin="round"></path>
                <path d="M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"></path>
            </svg>
        </a>
    </div>

    <section class="rounded-2xl border border-slate-800/80 bg-slate-900/70 p-6 shadow-xl">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-lg font-semibold text-white">Next up</h3>
                <p class="text-sm text-slate-400">A snapshot of the roles you still plan to apply for.</p>
            </div>
            <a href="/applications" class="inline-flex items-center gap-2 rounded-full border border-slate-700 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-300 transition hover:border-slate-500 hover:text-slate-100">
                Open tracker
            </a>
        </div>
        <div class="mt-4 space-y-4">
            <?php if (empty($outstandingApplications)) : ?>
                <p class="rounded-xl border border-slate-800 bg-slate-950/70 px-4 py-6 text-sm text-slate-400">
                    You have no outstanding postings right now. Capture the next role you find to keep everything organised.
                </p>
            <?php else : ?>
                <?php foreach ($outstandingApplications as $application) : ?>
                    <article class="flex flex-col gap-2 rounded-xl border border-slate-800 bg-slate-950/80 px-4 py-3 text-sm text-slate-200">
                        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                            <p class="font-medium text-white"><?= htmlspecialchars($application['title'] ?? 'Untitled application', ENT_QUOTES) ?></p>
                            <p class="text-xs text-slate-500">Added <?= htmlspecialchars($application['created_at'], ENT_QUOTES) ?></p>
                        </div>
                        <?php if (!empty($application['source_url'])) : ?>
                            <a href="<?= htmlspecialchars($application['source_url'], ENT_QUOTES) ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-2 text-xs text-indigo-300 hover:text-indigo-200">
                                View listing
                                <svg aria-hidden="true" class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path d="M7 17L17 7M7 7h10v10" stroke-linecap="round" stroke-linejoin="round"></path>
                                </svg>
                            </a>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <div class="grid gap-6 lg:grid-cols-[320px,1fr]">
        <nav class="rounded-2xl border border-slate-800/80 bg-slate-900/70 p-6">
            <ol class="space-y-4">
                <template x-for="item in steps" :key="item.index">
                    <li class="flex items-start gap-3" :class="item.index === step ? 'text-white' : 'text-slate-500'">
                        <span class="flex h-9 w-9 items-center justify-center rounded-full border" :class="item.index === step ? 'border-indigo-400 bg-indigo-500/20 text-indigo-200' : 'border-slate-700'">{{ item.index }}</span>
                        <div>
                            <p class="text-sm font-semibold" x-text="item.title"></p>
                            <p class="text-xs text-slate-500" x-text="item.description"></p>
                        </div>
                    </li>
                </template>
            </ol>
        </nav>
        <section
            x-ref="wizardPanel"
            tabindex="-1"
            class="rounded-2xl border border-slate-800/80 bg-slate-900/70 shadow-xl"
        >
            <div class="border-b border-slate-800/60 px-6 py-4">
                <h3 class="text-lg font-semibold text-white" x-text="steps[step - 1] ? steps[step - 1].title : ''"></h3>
                <p class="text-sm text-slate-400" x-text="steps[step - 1] ? steps[step - 1].helper : ''"></p>
            </div>
            <div class="space-y-6 px-6 py-6">
                <template x-if="isWizardDisabled">
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
                        <label class="flex cursor-pointer flex-col gap-1 rounded-xl border px-4 py-3 transition" :class="form.job_document_id === job.id ? 'border-indigo-400 bg-indigo-500/10 text-white' : 'border-slate-700 hover:border-slate-500 hover:bg-slate-800/40'">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-500/20 text-indigo-200">
                                        <svg aria-hidden="true" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                            <path d="M4 5h16M4 12h16M4 19h16" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </span>
                                    <div>
                                        <p class="font-medium" x-text="job.filename"></p>
                                        <p class="text-xs text-slate-400">Added <span x-text="formatDate(job.created_at)"></span></p>
                                    </div>
                                </div>
                                <input type="radio" class="hidden" name="job_document" :value="job.id" x-model="form.job_document_id">
                                <span class="text-xs uppercase tracking-wide" :class="form.job_document_id === job.id ? 'text-indigo-300' : 'text-slate-500'">
                                    Select
                                </span>
                            </div>
                        </label>
                    </template>
                </div>

                <div x-show="step === 2" x-cloak class="space-y-4">
                    <template x-if="cvDocuments.length === 0">
                        <p class="rounded-xl border border-slate-700 bg-slate-800/50 p-5 text-sm text-slate-300">
                            Upload a CV to continue.
                        </p>
                    </template>
                    <template x-for="cv in cvDocuments" :key="cv.id">
                        <label class="flex cursor-pointer flex-col gap-1 rounded-xl border px-4 py-3 transition" :class="form.cv_document_id === cv.id ? 'border-indigo-400 bg-indigo-500/10 text-white' : 'border-slate-700 hover:border-slate-500 hover:bg-slate-800/40'">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-500/20 text-emerald-200">
                                        <svg aria-hidden="true" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                            <path d="M5 7l5-4 5 4M5 17l5 4 5-4" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </span>
                                    <div>
                                        <p class="font-medium" x-text="cv.filename"></p>
                                        <p class="text-xs text-slate-400">Added <span x-text="formatDate(cv.created_at)"></span></p>
                                    </div>
                                </div>
                                <input type="radio" class="hidden" name="cv_document" :value="cv.id" x-model="form.cv_document_id">
                                <span class="text-xs uppercase tracking-wide" :class="form.cv_document_id === cv.id ? 'text-indigo-300' : 'text-slate-500'">
                                    Select
                                </span>
                            </div>
                        </label>
                    </template>
                </div>

                <div x-show="step === 3" x-cloak class="space-y-6">
                    <div>
                        <p class="text-sm font-medium text-slate-200">Model</p>
                        <div class="mt-3 grid gap-3 md:grid-cols-3">
                            <template x-for="model in models" :key="model.value">
                                <button type="button" class="w-full rounded-xl border px-4 py-3 text-left text-sm transition" :class="form.model === model.value ? 'border-indigo-400 bg-indigo-500/10 text-white' : 'border-slate-700 hover:border-slate-500 hover:bg-slate-800/40'" @click="form.model = model.value">
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
                        <input type="range" min="5" max="60" step="5" x-model.number="form.thinking_time" class="w-full accent-indigo-500">
                        <p class="text-xs text-slate-400">Give GPT-5 a little more time for deeper reasoning. Thirty seconds is a balanced default.</p>
                    </div>
                </div>

                <div x-show="step === 4" x-cloak class="space-y-4">
                    <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-4">
                        <h4 class="text-sm font-semibold text-slate-200">Review selections</h4>
                        <dl class="mt-3 space-y-2 text-sm text-slate-300">
                            <div class="flex items-start justify-between gap-4">
                                <dt class="text-slate-400">Job description</dt>
                                <dd class="font-medium text-right" x-text="selectedJobDocument ? selectedJobDocument.filename : 'None selected'"></dd>
                            </div>
                            <div class="flex items-start justify-between gap-4">
                                <dt class="text-slate-400">CV</dt>
                                <dd class="font-medium text-right" x-text="selectedCvDocument ? selectedCvDocument.filename : 'None selected'"></dd>
                            </div>
                            <div class="flex items-start justify-between gap-4">
                                <dt class="text-slate-400">Model</dt>
                                <dd class="font-medium text-right" x-text="displayModelLabel"></dd>
                            </div>
                            <div class="flex items-start justify-between gap-4">
                                <dt class="text-slate-400">Thinking time</dt>
                                <dd class="font-medium text-right" x-text="form.thinking_time + 's'"></dd>
                            </div>
                        </dl>
                    </div>
                    <div class="rounded-xl border border-indigo-500/30 bg-indigo-500/10 p-4 text-sm text-indigo-100">
                        Once submitted, the request appears in your queue with status <strong class="font-semibold">queued</strong> until processing begins.
                    </div>
                </div>

                <div class="flex flex-col gap-3 border-t border-slate-800/60 pt-4 sm:flex-row sm:justify-between">
                    <div class="space-y-2">
                        <template x-if="error">
                            <div class="rounded-lg border border-rose-500/40 bg-rose-500/10 px-3 py-2 text-sm text-rose-200" x-text="error"></div>
                        </template>
                        <template x-if="successMessage">
                            <div class="rounded-lg border border-emerald-500/40 bg-emerald-500/10 px-3 py-2 text-sm text-emerald-200" x-text="successMessage"></div>
                        </template>
                    </div>
                    <div class="flex flex-col gap-3 sm:flex-row">
                        <button type="button" class="rounded-lg border border-slate-700 px-4 py-2 text-sm font-medium text-slate-300 transition hover:border-slate-500 hover:bg-slate-800/60" @click="previous()" :disabled="step === 1" :class="step === 1 ? 'cursor-not-allowed opacity-40' : ''">
                            Back
                        </button>
                        <template x-if="step < steps.length">
                            <button type="button" class="rounded-lg bg-indigo-500 px-5 py-2 text-sm font-semibold text-white transition hover:bg-indigo-400 disabled:cursor-not-allowed disabled:opacity-50" @click="next()" :disabled="!canMoveForward || isWizardDisabled">
                                Continue
                            </button>
                        </template>
                        <template x-if="step === steps.length">
                            <button type="button" class="rounded-lg bg-indigo-500 px-5 py-2 text-sm font-semibold text-white transition hover:bg-indigo-400 disabled:cursor-not-allowed disabled:opacity-50" @click="submit()" :disabled="isSubmitting || isWizardDisabled || !canSubmit">
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
                            <td class="px-4 py-4 font-medium text-slate-200" x-text="item.job_document.filename"></td>
                            <td class="px-4 py-4" x-text="item.cv_document.filename"></td>
                            <td class="px-4 py-4" x-text="item.model"></td>
                            <td class="px-4 py-4" x-text="item.thinking_time + 's'"></td>
                            <td class="px-4 py-4">
                                <span class="inline-flex items-center rounded-full bg-slate-800 px-3 py-1 text-xs font-semibold uppercase tracking-wide" :class="item.status === 'queued' ? 'text-amber-200 bg-amber-500/10 border border-amber-400/30' : 'text-emerald-200 bg-emerald-500/10 border border-emerald-400/30'" x-text="item.status"></span>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </section>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('generationWizard', (config) => {
            // Provide a resilient array check for browsers that lack Array.isArray.
            const isArray = Array.isArray || function (value) {
                return Object.prototype.toString.call(value) === '[object Array]';
            };

            const models = isArray(config.models) ? config.models : [];
            const jobDocuments = isArray(config.jobDocuments) ? config.jobDocuments : [];
            const cvDocuments = isArray(config.cvDocuments) ? config.cvDocuments : [];
            const generations = isArray(config.generations) ? config.generations : [];

            // Determine whether the provided value is a finite number before using it in calculations.
            const isFiniteNumber = function (value) {
                return typeof value === 'number' && isFinite(value);
            };

            const defaultThinkingTime = isFiniteNumber(config.defaultThinkingTime)
                ? config.defaultThinkingTime
                : 30;

            const firstModelValue = models.length > 0 && typeof models[0].value === 'string'
                ? models[0].value
                : '';

            return {
                step: 1,
                steps: [
                    { index: 1, title: 'Choose job description', description: 'Select the role you want to tailor for.', helper: 'Pick the job posting that best matches the next application.' },
                    { index: 2, title: 'Choose CV', description: 'Decide which base CV to tailor.', helper: 'Use the CV with the strongest baseline for this role.' },
                    { index: 3, title: 'Set parameters', description: 'Adjust the model and thinking time.', helper: 'Choose the best model and allow enough thinking time for complex roles.' },
                    { index: 4, title: 'Confirm & queue', description: 'Review before submitting.', helper: 'Double-check your selections before queuing the request.' },
                ],
                jobDocuments,
                cvDocuments,
                models,
                generations,
                form: {
                    job_document_id: null,
                    cv_document_id: null,
                    model: firstModelValue,
                    thinking_time: defaultThinkingTime,
                },
                defaultThinkingTime,
                isSubmitting: false,
                error: '',
                successMessage: '',
                get isWizardDisabled() {
                    return this.jobDocuments.length === 0 || this.cvDocuments.length === 0;
                },
                get canMoveForward() {
                    if (this.step === 1) {
                        return this.form.job_document_id !== null;
                    }
                    if (this.step === 2) {
                        return this.form.cv_document_id !== null;
                    }
                    if (this.step === 3) {
                        return this.form.model !== '' && this.thinkingTimeIsValid;
                    }
                    return true;
                },
                get canSubmit() {
                    return this.selectedJobDocument && this.selectedCvDocument && this.thinkingTimeIsValid;
                },
                get thinkingTimeIsValid() {
                    return isFiniteNumber(this.form.thinking_time) && this.form.thinking_time >= 5 && this.form.thinking_time <= 60;
                },
                get selectedJobDocument() {
                    const documents = isArray(this.jobDocuments) ? this.jobDocuments : [];
                    for (let index = 0; index < documents.length; index += 1) {
                        const documentItem = documents[index];
                        if (documentItem && documentItem.id === this.form.job_document_id) {
                            return documentItem;
                        }
                    }
                    return null;
                },
                get selectedCvDocument() {
                    const documents = isArray(this.cvDocuments) ? this.cvDocuments : [];
                    for (let index = 0; index < documents.length; index += 1) {
                        const documentItem = documents[index];
                        if (documentItem && documentItem.id === this.form.cv_document_id) {
                            return documentItem;
                        }
                    }
                    return null;
                },
                get displayModelLabel() {
                    const availableModels = isArray(this.models) ? this.models : [];
                    for (let index = 0; index < availableModels.length; index += 1) {
                        const model = availableModels[index];
                        if (model && model.value === this.form.model) {
                            return model.label;
                        }
                    }
                    return this.form.model;
                },
                previous() {
                    if (this.step > 1) {
                        this.step -= 1;
                        this.error = '';
                        this.successMessage = '';
                    }
                },
                next() {
                    if (this.step < this.steps.length && this.canMoveForward) {
                        this.step += 1;
                        this.error = '';
                    }
                },
                async submit() {
                    if (this.isSubmitting || !this.canSubmit) {
                        return;
                    }

                    this.isSubmitting = true;
                    this.error = '';
                    this.successMessage = '';

                    try {
                        const response = await fetch('/generations', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                job_document_id: this.form.job_document_id,
                                cv_document_id: this.form.cv_document_id,
                                model: this.form.model,
                                thinking_time: this.form.thinking_time,
                            }),
                        });

                        const data = await response.json();

                        if (!response.ok) {
                            const message = data && typeof data.error === 'string'
                                ? data.error
                                : 'Unable to queue the generation. Please try again.';
                            this.error = message;
                            return;
                        }

                        const list = isArray(this.generations) ? this.generations : [];

                        list.unshift({
                            ...data,
                            thinking_time: typeof data.thinking_time === 'number'
                                ? data.thinking_time
                                : this.form.thinking_time,
                            job_document: this.selectedJobDocument,
                            cv_document: this.selectedCvDocument,
                        });

                        this.generations = list;

                        this.successMessage = 'Generation queued successfully.';
                        this.step = 1;
                    } catch (error) {
                        this.error = 'A network error prevented queuing the generation.';
                    } finally {
                        this.isSubmitting = false;
                    }
                },
                formatDate(value) {
                    if (!value) {
                        return '';
                    }
                    const date = new Date(value);
                    return isNaN(date.getTime()) ? value : date.toLocaleDateString();
                },
                formatDateTime(value) {
                    if (!value) {
                        return '';
                    }
                    const date = new Date(value);
                    return isNaN(date.getTime()) ? value : date.toLocaleString();
                },
                // Reset the wizard to its first step, clear previous selections, and guide the user directly to the tailoring workflow.
                startNewGeneration() {
                    this.step = 1;
                    this.error = '';
                    this.successMessage = '';

                    this.form.job_document_id = null;
                    this.form.cv_document_id = null;
                    this.form.model = (isArray(this.models) && this.models.length > 0 && typeof this.models[0].value === 'string')
                        ? this.models[0].value
                        : '';
                    this.form.thinking_time = this.defaultThinkingTime;

                    if (this.isWizardDisabled) {
                        this.error = 'Upload at least one job description and CV to start tailoring.';
                    }

                    this.$nextTick(() => {
                        if (this.$refs.wizardPanel) {
                            this.$refs.wizardPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });

                            if (typeof this.$refs.wizardPanel.focus === 'function') {
                                this.$refs.wizardPanel.focus();
                            }
                        }
                    });
                },
            };
        });
    });
</script>
<?php $body = ob_get_clean(); ?>
<?php include __DIR__ . '/layout.php'; ?>
