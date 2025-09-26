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
                <a class="secondary-link focus-ring" href="#components">
                    Explore the flow
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M5 12h14"></path>
                        <path d="M13 6l6 6-6 6"></path>
                    </svg>
                </a>
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
</body>
</html>
