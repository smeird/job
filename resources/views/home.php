
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Adaptive Workspace Design System</title>
    <meta name="description" content="Glassmorphic Tailwind design tokens showcasing upload cards, data tables, steppers, progress, and toast notifications.">
    <link rel="stylesheet" href="/assets/css/theme.css">
</head>
<body>
<main>
    <header>
        <div>
            <h1>Adaptive Workspace UI</h1>
            <p>
                A cohesive token-driven theme with glassmorphic surfaces, gradient actions, soft shadows, and a responsive
                dark/light mode built for complex, data-rich workflows.
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
                <span class="badge">AA contrast compliant</span>
                <h2>Design once, respond everywhere</h2>
                <p>
                    Each component inherits the design tokens defined in <code>tailwind.config.js</code> and the layered
                    CSS variables, ensuring translucency, soft radii, and focus states remain consistent across the
                    application.
                </p>
            </div>
            <div class="hero-actions">
                <button type="button" class="gradient-button focus-ring">
                    Launch workflow
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M5 12h14"></path>
                        <path d="M13 6l6 6-6 6"></path>
                    </svg>
                </button>
                <a class="secondary-link focus-ring" href="#components">
                    Explore tokens
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
                <h3 class="section-title">Component showcase</h3>
                <p class="section-subtitle">
                    Upload pipelines, real-time data visibility, wizard flows, progress tracking, and toast feedback all
                    inherit translucent surfaces, gradient accents, and accessible focus treatments.
                </p>
            </div>
        </header>

        <div class="component-grid">
            <article class="surface-card upload-card" aria-labelledby="upload-title">
                <div>
                    <h4 id="upload-title" class="card-heading">Upload workspace assets</h4>
                    <p class="card-description">Drop compliant CSV or JSON exports for instant processing.</p>
                </div>
                <label class="upload-dropzone" for="workspace-upload">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M12 16V4"></path>
                        <path d="M6 10l6-6 6 6"></path>
                        <rect x="4" y="16" width="16" height="4" rx="1.4"></rect>
                    </svg>
                    <span>Drag &amp; drop files here</span>
                </label>
                <input id="workspace-upload" name="workspace-upload" type="file" hidden>
                <div class="upload-actions">
                    <label for="workspace-upload" class="focus-ring">
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M4 4h16v16H4z" opacity="0.4"></path>
                            <path d="M9 12h6"></path>
                            <path d="M12 9v6"></path>
                        </svg>
                        Choose file
                    </label>
                    <p>Supported formats: CSV, JSON, PDF • Max 15&nbsp;MB</p>
                </div>
            </article>

            <article class="surface-card data-table" aria-labelledby="table-title">
                <div>
                    <h4 id="table-title" class="card-heading">Workspace usage</h4>
                    <p class="card-description">Adaptive density table with quick search and status indicators.</p>
                </div>
                <div class="data-table__toolbar">
                    <span class="badge">Live data</span>
                    <label class="data-table__search" aria-label="Search workspace data">
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <circle cx="11" cy="11" r="6"></circle>
                            <path d="M20 20l-3.35-3.35"></path>
                        </svg>
                        <input type="search" placeholder="Search" data-table-search aria-label="Search workspaces">
                    </label>
                </div>
                <div class="data-table__table" id="data-table"></div>
            </article>

            <article class="surface-card" aria-labelledby="wizard-title">
                <div>
                    <h4 id="wizard-title" class="card-heading">Wizard stepper</h4>
                    <p class="card-description">Progressive steps keep teams aligned while maintaining contrast in both themes.</p>
                </div>
                <div class="wizard-stepper" role="list">
                    <div class="step" role="listitem" data-step-index="1" data-step-complete="true">
                        <span class="step__indicator">1</span>
                        <p class="step__label">Plan</p>
                        <p class="step__description">Define cohorts and success metrics.</p>
                    </div>
                    <div class="step" role="listitem" data-step-index="2" data-step-complete="true">
                        <span class="step__indicator">2</span>
                        <p class="step__label">Ingest</p>
                        <p class="step__description">Securely sync vendor and first-party data.</p>
                    </div>
                    <div class="step" role="listitem" data-step-index="3" data-step-active="true">
                        <span class="step__indicator">3</span>
                        <p class="step__label">Model</p>
                        <p class="step__description">Train forecasting pipelines with guardrails.</p>
                    </div>
                    <div class="step" role="listitem" data-step-index="4">
                        <span class="step__indicator">4</span>
                        <p class="step__label">Launch</p>
                        <p class="step__description">Share dashboards &amp; automate alerts.</p>
                    </div>
                </div>
            </article>

            <article class="surface-card progress-card" aria-labelledby="progress-title">
                <div>
                    <h4 id="progress-title" class="card-heading">Progress &amp; feedback</h4>
                    <p class="card-description">Soft gradients, rounded corners, and toasts reinforce meaningful state changes.</p>
                </div>
                <div class="progress-bar" role="progressbar" data-progress-bar="72">
                    <div class="progress-bar__value"></div>
                </div>
                <footer>
                    <span>Integration readiness</span>
                    <strong>72% complete</strong>
                </footer>
                <div class="toast-stack" aria-live="polite">
                    <article class="toast toast--success" data-toast>
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M5 13l4 4L19 7"></path>
                        </svg>
                        <div>
                            <p class="toast__title">Upload processed</p>
                            <p class="toast__message">Nova UX Research synced 28k events just now.</p>
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
                            <p class="toast__title">Workflow reminder</p>
                            <p class="toast__message">Stakeholder review begins tomorrow at 10:00 AM.</p>
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
        <small>Theme tokens ship with Tailwind utilities for backdrop blur, gradients, shadows, and focus safety nets.</small>
    </footer>
</main>

<script src="/assets/js/theme.js" defer></script>
</body>
</html>

