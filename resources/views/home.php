<?php
$generationIdRaw = $_GET['generation'] ?? null;
$generationId = null;

if (is_string($generationIdRaw)) {
    $trimmed = trim($generationIdRaw);

    if ($trimmed !== '') {
        $sanitised = preg_replace('/[^0-9]/', '', $trimmed);
        $generationId = $sanitised !== '' ? $sanitised : null;
    }
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>job.smeird.com Workspace</title>
    <meta name="description" content="Upload job descriptions and CVs, generate tailored drafts with AI, and monitor usage in the job.smeird.com workspace.">
    <link rel="stylesheet" href="/assets/css/theme.css">
</head>
<body>
<main>
    <?php if (!empty($isAuthenticated)) : ?>
        <div class="auth-banner" role="status">
            <span>You are currently signed in.</span>
            <a class="secondary-link focus-ring" href="/#generation-wizard">Open your tailoring wizard</a>
        </div>
    <?php endif; ?>
    <header>
        <div>
            <h1>job.smeird.com workspace</h1>
            <p>
                Purpose-built tooling for applicants who need to ingest job descriptions, match them with curated CVs, and ship
                polished drafts without juggling spreadsheets or ad-hoc prompts.
            </p>
        </div>
        <button type="button" class="theme-toggle" data-theme-toggle aria-pressed="false">
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <path d="M12 3v2"></path>
                <path d="M12 19v2"></path>
                <path d="M5.64 5.64l1.42 1.42"></path>
                <path d="M16.94 16.94l1.42 1.42"></path>
                <path d="M3 12h2"></path>
                <path d="M19 12h2"></path>
                <path d="M5.64 18.36l1.42-1.42"></path>
                <path d="M16.94 7.06l1.42-1.42"></path>
                <circle cx="12" cy="12" r="4.2"></circle>
            </svg>
            <span data-theme-label>Light mode</span>
        </button>
    </header>

    <section class="hero-card">
        <div class="hero-card-content">
            <div>
                <span class="badge">AI-assisted job applications</span>
                <h2>Tailor every submission with confidence</h2>
                <p>
                    Upload job specs, pair them with the right CV, and queue drafts that honour your brand voice. The workspace
                    keeps authentication passwordless, enforces document safety checks, and signs download links so only you can
                    access the finished artefacts.
                </p>
            </div>
            <div class="hero-actions">
                <?php if (!empty($isAuthenticated)) : ?>
                    <a class="gradient-button focus-ring" href="/#generation-wizard">
                        Go to the tailoring wizard
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M5 12h14"></path>
                            <path d="M13 6l6 6-6 6"></path>
                        </svg>
                    </a>
                    <a class="secondary-link focus-ring" href="#wizard-preview">
                        Preview the steps
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M5 12h14"></path>
                            <path d="M13 6l6 6-6 6"></path>
                        </svg>
                    </a>
                <?php else : ?>
                    <a class="gradient-button focus-ring" href="/auth/login">
                        Sign in to your workspace
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M5 12h14"></path>
                            <path d="M13 6l6 6-6 6"></path>
                        </svg>
                    </a>
                    <a class="secondary-link focus-ring" href="/auth/register">
                        Create an account
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M5 12h14"></path>
                            <path d="M13 6l6 6-6 6"></path>
                        </svg>
                    </a>
                    <a class="secondary-link focus-ring" href="#wizard-preview">
                        Explore the flow
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M5 12h14"></path>
                            <path d="M13 6l6 6-6 6"></path>
                        </svg>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="surface-card wizard-preview" id="wizard-preview" data-wizard-preview>
        <header class="wizard-preview__header">
            <div>
                <span class="badge">Tailoring workflow</span>
                <h3>How the tailored CV wizard works</h3>
                <p>
                    Step through the same guided experience you will use after signing in.
                    Each stage focuses on pairing the right documents with the right AI guidance.
                </p>
            </div>
        </header>
        <div class="wizard-preview__layout">
            <ol class="wizard-preview__steps" aria-label="Wizard steps">
                <li>
                    <button type="button" class="wizard-preview__step" data-step-button="1" aria-pressed="false">
                        <span class="wizard-preview__step-number">1</span>
                        <span class="wizard-preview__step-content">
                            <strong>Choose job description</strong>
                            <small>Select the role you are targeting next.</small>
                        </span>
                    </button>
                </li>
                <li>
                    <button type="button" class="wizard-preview__step" data-step-button="2" aria-pressed="false">
                        <span class="wizard-preview__step-number">2</span>
                        <span class="wizard-preview__step-content">
                            <strong>Select the best CV</strong>
                            <small>Pick the baseline CV that fits the posting.</small>
                        </span>
                    </button>
                </li>
                <li>
                    <button type="button" class="wizard-preview__step" data-step-button="3" aria-pressed="false">
                        <span class="wizard-preview__step-number">3</span>
                        <span class="wizard-preview__step-content">
                            <strong>Configure generation</strong>
                            <small>Set the model, tone, and thinking time.</small>
                        </span>
                    </button>
                </li>
                <li>
                    <button type="button" class="wizard-preview__step" data-step-button="4" aria-pressed="false">
                        <span class="wizard-preview__step-number">4</span>
                        <span class="wizard-preview__step-content">
                            <strong>Confirm &amp; queue</strong>
                            <small>Review selections before sending.</small>
                        </span>
                    </button>
                </li>
            </ol>
            <div class="wizard-preview__panels">
                <article class="wizard-preview__panel" data-step-panel="1" aria-live="polite">
                    <h4>Choose job description</h4>
                    <p>
                        Bring the job posting into the workspace so it is easy to reference later.
                        The wizard highlights saved descriptions with dates and titles, keeping the choice straightforward.
                    </p>
                    <ul class="wizard-preview__list" aria-label="Sample job descriptions">
                        <li>
                            <span class="wizard-preview__list-title">Programme Manager — VodafoneThree</span>
                            <span class="wizard-preview__list-meta">Added 3 days ago</span>
                        </li>
                        <li>
                            <span class="wizard-preview__list-title">AI Operations Lead — Horizon Labs</span>
                            <span class="wizard-preview__list-meta">Added 1 week ago</span>
                        </li>
                    </ul>
                </article>
                <article class="wizard-preview__panel" data-step-panel="2" aria-live="polite" hidden>
                    <h4>Select the best CV</h4>
                    <p>
                        Switch between previously uploaded CVs to align tone and achievements with the role.
                        The wizard confirms which version you are tailoring before moving on.
                    </p>
                    <ul class="wizard-preview__list" aria-label="Sample CVs">
                        <li>
                            <span class="wizard-preview__list-title">Delivery Manager · UK Market</span>
                            <span class="wizard-preview__list-meta">Updated 2 weeks ago</span>
                        </li>
                        <li>
                            <span class="wizard-preview__list-title">Product Strategy CV · EMEA</span>
                            <span class="wizard-preview__list-meta">Updated 1 month ago</span>
                        </li>
                    </ul>
                </article>
                <article class="wizard-preview__panel" data-step-panel="3" aria-live="polite" hidden>
                    <h4>Configure generation</h4>
                    <p>
                        Fine-tune how the AI responds by choosing the model and allowing extra thinking time when the role is complex.
                        Defaults are balanced, yet every option is surfaced to help you tailor confidently.
                    </p>
                    <div class="wizard-preview__options">
                        <div>
                            <span class="wizard-preview__option-label">Model</span>
                            <span class="wizard-preview__option-value">GPT-4o mini · Fast and affordable</span>
                        </div>
                        <div>
                            <span class="wizard-preview__option-label">Thinking time</span>
                            <span class="wizard-preview__option-value">30 seconds</span>
                        </div>
                    </div>
                </article>
                <article class="wizard-preview__panel" data-step-panel="4" aria-live="polite" hidden>
                    <h4>Confirm &amp; queue</h4>
                    <p>
                        Review your selections and send the request to the queue.
                        The workspace tracks status, token usage, and spend while your tailored draft is generated.
                    </p>
                    <dl class="wizard-preview__summary">
                        <div>
                            <dt>Job description</dt>
                            <dd>Programme Manager — VodafoneThree</dd>
                        </div>
                        <div>
                            <dt>CV</dt>
                            <dd>Delivery Manager · UK Market</dd>
                        </div>
                        <div>
                            <dt>Thinking time</dt>
                            <dd>30 seconds</dd>
                        </div>
                    </dl>
                    <button type="button" class="gradient-button gradient-button--compact" disabled>
                        Confirm &amp; queue
                    </button>
                </article>
            </div>
        </div>
    </section>

    <section id="components">
        <header>
            <div>
                <h3 class="section-title">Workspace highlights</h3>
                <p class="section-subtitle">
                    From passcode-based sign-in to usage analytics, every feature on this page reflects the experience you get
                    after logging in.
                </p>
            </div>
        </header>

        <div class="component-grid">
            <article class="surface-card upload-card" aria-labelledby="upload-title">
                <div>
                    <h4 id="upload-title" class="card-heading">Ingest job descriptions and CVs</h4>
                    <p class="card-description">Drop structured job specs or CV updates directly into the secure document store.</p>
                </div>
                <label class="upload-dropzone" for="workspace-upload">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M12 16V4"></path>
                        <path d="M6 10l6-6 6 6"></path>
                        <rect x="4" y="16" width="16" height="4" rx="1.4"></rect>
                    </svg>
                    <span>Drag &amp; drop PDFs, DOCX, Markdown, or text files</span>
                </label>
                <input id="workspace-upload" name="workspace-upload" type="file" hidden>
                <div class="upload-actions">
                    <label for="workspace-upload" class="focus-ring">
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M4 4h16v16H4z" opacity="0.4"></path>
                            <path d="M9 12h6"></path>
                            <path d="M12 9v6"></path>
                        </svg>
                        Choose a file
                    </label>
                    <p>Uploads are capped at 1&nbsp;MiB and scanned for risky macros before storage.</p>
                </div>
            </article>

            <article class="surface-card data-table" aria-labelledby="table-title">
                <div>
                    <h4 id="table-title" class="card-heading">Recent tailored drafts</h4>
                    <p class="card-description">Tabulator powers sortable previews of queued and completed generations.</p>
                </div>
                <div class="data-table__toolbar">
                    <span class="badge">Sample queue</span>
                    <label class="data-table__search" aria-label="Search tailored drafts">
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <circle cx="11" cy="11" r="6"></circle>
                            <path d="M20 20l-3.35-3.35"></path>
                        </svg>
                        <input type="search" placeholder="Search drafts" data-table-search aria-label="Search tailored drafts">
                    </label>
                </div>
                <div class="data-table__table" id="data-table"></div>
            </article>

            <article class="surface-card" aria-labelledby="wizard-title">
                <div>
                    <h4 id="wizard-title" class="card-heading">Workflow preview</h4>
                    <p class="card-description">The same wizard guides every generation once you sign in.</p>
                </div>
                <div class="wizard-stepper" role="list">
                    <div class="step" role="listitem" data-step-index="1" data-step-complete="true">
                        <span class="step__indicator">1</span>
                        <p class="step__label">Upload job spec</p>
                        <p class="step__description">Capture each posting so you can reference it later.</p>
                    </div>
                    <div class="step" role="listitem" data-step-index="2" data-step-complete="true">
                        <span class="step__indicator">2</span>
                        <p class="step__label">Select the right CV</p>
                        <p class="step__description">Swap between tailored resumes stored securely in the vault.</p>
                    </div>
                    <div class="step" role="listitem" data-step-index="3" data-step-active="true">
                        <span class="step__indicator">3</span>
                        <p class="step__label">Configure the draft</p>
                        <p class="step__description">Pick a model, adjust creativity, and confirm usage expectations.</p>
                    </div>
                    <div class="step" role="listitem" data-step-index="4">
                        <span class="step__indicator">4</span>
                        <p class="step__label">Review &amp; download</p>
                        <p class="step__description">Receive a signed link and archive outputs alongside analytics.</p>
                    </div>
                </div>
            </article>

            <article class="surface-card progress-card" aria-labelledby="progress-title" data-generation-monitor<?php if ($generationId !== null) {
                echo ' data-generation-id="' . htmlspecialchars($generationId, ENT_QUOTES, 'UTF-8') . '"';
            } ?>>
                <div>
                    <h4 id="progress-title" class="card-heading">Live generation monitor</h4>
                    <p class="card-description">Server-sent events track status, token usage, and spend while drafts are built.</p>
                </div>
                <div class="progress-card__status" aria-live="polite">
                    <span class="status-pill status-pill--pending" data-generation-status>Queued</span>
                    <span class="progress-card__metric" data-generation-tokens>0 tokens</span>
                    <span class="progress-card__metric" data-cost-ticker>&pound;0.00 spent</span>
                </div>
                <div class="progress-bar" role="progressbar" data-progress-bar="48">
                    <div class="progress-bar__value"></div>
                </div>
                <footer>
                    <span>Queue progress</span>
                    <strong data-progress-label>48% complete</strong>
                </footer>
                <div class="toast-stack" aria-live="polite">
                    <article class="toast toast--success" data-toast>
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M5 13l4 4L19 7"></path>
                        </svg>
                        <div>
                            <p class="toast__title">Draft delivered</p>
                            <p class="toast__message">Your frontend engineer cover letter is ready to download.</p>
                        </div>
                        <button type="button" class="toast__close" aria-label="Dismiss success toast" data-toast-dismiss>
                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M6 6l12 12"></path>
                                <path d="M18 6L6 18"></path>
                            </svg>
                        </button>
                    </article>
                    <article class="toast toast--info" data-toast>
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <circle cx="12" cy="12" r="9"></circle>
                            <path d="M12 8v4"></path>
                            <path d="M12 16h.01"></path>
                        </svg>
                        <div>
                            <p class="toast__title">Retention reminder</p>
                            <p class="toast__message">Policy is set to purge unused drafts after 30 days.</p>
                        </div>
                        <button type="button" class="toast__close" aria-label="Dismiss info toast" data-toast-dismiss>
                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M6 6l12 12"></path>
                                <path d="M18 6L6 18"></path>
                            </svg>
                        </button>
                    </article>
                </div>
            </article>
        </div>
    </section>

    <footer>
        <small>Powered by Tailwind design tokens, Tabulator tables, and Highcharts dashboards across the authenticated workspace.</small>
    </footer>
</main>

<script src="/assets/js/theme.js" defer></script>
<script>
    (function () {
        'use strict';

        /**
         * Toggle the displayed wizard panel so visitors can explore the workflow.
         *
         * The helper wires up step buttons, applies active state styling, and
         * reveals the corresponding panel while hiding the others.
         *
         * @param {HTMLElement} root The root element that wraps the wizard preview UI.
         */
        function initialiseWizardPreview(root) {
            if (!root) {
                return;
            }

            var buttons = Array.prototype.slice.call(root.querySelectorAll('[data-step-button]'));
            var panels = Array.prototype.slice.call(root.querySelectorAll('[data-step-panel]'));

            if (buttons.length === 0 || panels.length === 0) {
                return;
            }

            /**
             * Activate a given wizard step and ensure matching panel visibility.
             *
             * Keeping this logic in a dedicated function keeps the event handlers small.
             *
             * @param {number} stepNumber The wizard step that should become active.
             */
            function activateStep(stepNumber) {
                buttons.forEach(function (button) {
                    var isActive = Number(button.getAttribute('data-step-button')) === stepNumber;
                    button.classList.toggle('is-active', isActive);
                    button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                });

                panels.forEach(function (panel) {
                    var matches = Number(panel.getAttribute('data-step-panel')) === stepNumber;
                    panel.toggleAttribute('hidden', !matches);
                });
            }

            buttons.forEach(function (button) {
                button.addEventListener('click', function () {
                    var step = Number(button.getAttribute('data-step-button'));

                    if (!isNaN(step)) {
                        activateStep(step);
                    }
                });
            });

            activateStep(1);
        }

        /**
         * Initialise the wizard preview after the DOM is ready.
         *
         * This keeps the behaviour working even when the script loads before the markup.
         */
        function handleContentLoaded() {
            var preview = document.querySelector('[data-wizard-preview]');
            initialiseWizardPreview(preview);
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', handleContentLoaded);
        } else {
            handleContentLoaded();
        }
    })();
</script>
</body>
</html>
