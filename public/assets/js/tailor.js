(function () {
    'use strict';

    /**
     * Provide the Tailor CV wizard state and behaviour for Alpine.js components.
     *
     * The wizard accepts a configuration payload generated on the server so the UI
     * renders with populated documents, recent generations, and available models.
     *
     * @param {object} config The server-provided configuration payload.
     * @returns {object} Alpine-compatible state and behaviour for the wizard.
     */
    const tailorWizardFactory = function (config) {
        /**
         * Normalise arbitrary values into an array instance for template rendering.
         *
         * @param {*} value The value that may already be an array.
         * @returns {Array} A shallow-copied array or an empty fallback.
         */
        const toArray = function (value) {
            if (Array.isArray(value)) {
                return value.slice();
            }

            if (value === null || value === undefined) {
                return [];
            }

            return [value].filter(function () {
                return true;
            });
        };

        /**
         * Generate a comparable identifier string for mixed-value primary keys.
         *
         * @param {*} value The identifier supplied by the configuration payload.
         * @returns {string} A string representation or an empty string when invalid.
         */
        const toId = function (value) {
            if (typeof value === 'number' && isFinite(value)) {
                return String(value);
            }

            if (typeof value === 'string') {
                return value;
            }

            if (value !== null && typeof value === 'object' && typeof value.toString === 'function') {
                return value.toString();
            }

            return '';
        };

        /**
         * Parse a numeric value, returning a fallback when parsing fails.
         *
         * @param {*} value The incoming value that may represent a number.
         * @param {number} fallback The fallback applied when parsing fails.
         * @returns {number} The parsed numeric value or the fallback.
         */
        const normaliseNumber = function (value, fallback) {
            const parsed = typeof value === 'number' ? value : parseInt(value, 10);

            if (!isNaN(parsed) && isFinite(parsed)) {
                return parsed;
            }

            return fallback;
        };

        /**
         * Prepare an ordered list of wizard steps from the configuration payload.
         *
         * @param {*} value A potential array of step descriptors.
         * @returns {Array} A sorted array of steps with numeric indices.
         */
        const buildSteps = function (value) {
            if (!Array.isArray(value)) {
                return [];
            }

            const list = [];

            for (let index = 0; index < value.length; index += 1) {
                const item = value[index];

                if (!item) {
                    continue;
                }

                const parsedIndex = normaliseNumber(item.index, NaN);
                const title = typeof item.title === 'string' ? item.title : '';
                const helper = typeof item.helper === 'string' ? item.helper : '';
                const summary = typeof item.summary === 'string' ? item.summary : '';

                if (!isNaN(parsedIndex) && title !== '') {
                    list.push({
                        index: parsedIndex,
                        title: title,
                        helper: helper,
                        summary: summary,
                    });
                }
            }

            list.sort(function (a, b) {
                if (a.index < b.index) {
                    return -1;
                }
                if (a.index > b.index) {
                    return 1;
                }
                return 0;
            });

            return list;
        };

        /**
         * Locate a document within a collection by its identifier.
         *
         * @param {Array} collection The list of documents to search.
         * @param {*} identifier The identifier to match.
         * @returns {object|null} The matching document or null when not found.
         */
        const findDocument = function (collection, identifier) {
            const normalisedId = toId(identifier);

            if (normalisedId === '') {
                return null;
            }

            for (let index = 0; index < collection.length; index += 1) {
                const item = collection[index];

                if (!item) {
                    continue;
                }

                if (toId(item.id) === normalisedId) {
                    return item;
                }
            }

            return null;
        };

        const defaultThinkingTime = normaliseNumber(config && config.defaultThinkingTime, 30);
        let steps = buildSteps(config && config.steps);

        if (steps.length === 0) {
            steps = [
                { index: 1, title: 'Choose job description', helper: 'Select the job description you want to tailor for.', summary: '' },
                { index: 2, title: 'Choose CV', helper: 'Select the CV that provides the best foundation.', summary: '' },
                { index: 3, title: 'Set parameters', helper: 'Adjust the AI model and thinking time.', summary: '' },
                { index: 4, title: 'Confirm & queue', helper: 'Review your selections before submission.', summary: '' },
            ];
        }

        return {
            step: 1,
            steps: steps,
            jobDocuments: toArray(config && config.jobDocuments),
            cvDocuments: toArray(config && config.cvDocuments),
            models: toArray(config && config.models),
            generations: toArray(config && config.generations),
            defaultThinkingTime: defaultThinkingTime,
            form: {
                job_document_id: null,
                cv_document_id: null,
                model: '',
                thinking_time: defaultThinkingTime,
            },
            errorMessage: '',
            successMessage: '',
            isSubmitting: false,

            /**
             * Initialise the wizard with default selections and contextual messaging.
             */
            initialise() {
                this.resetForm();

                if (this.isDisabled) {
                    this.errorMessage = 'Upload at least one job description and CV to get started.';
                }
            },

            /**
             * Reset the wizard selections and return to the first step.
             */
            resetForm() {
                this.step = 1;
                this.errorMessage = '';
                this.successMessage = '';

                this.form.job_document_id = null;
                this.form.cv_document_id = null;
                this.form.model = this.models.length > 0 && typeof this.models[0].value === 'string'
                    ? this.models[0].value
                    : '';
                this.form.thinking_time = this.defaultThinkingTime;

                this.schedulePanelFocus();
            },

            /**
             * Determine whether the wizard lacks the prerequisites to run.
             *
             * @returns {boolean} True when either job descriptions or CVs are missing.
             */
            get isDisabled() {
                return this.jobDocuments.length === 0 || this.cvDocuments.length === 0;
            },

            /**
             * Provide the descriptor for the currently active step.
             *
             * @returns {object|null} The active step metadata or null when unavailable.
             */
            get activeStep() {
                const index = this.step - 1;

                if (index < 0 || index >= this.steps.length) {
                    return null;
                }

                return this.steps[index];
            },

            /**
             * Confirm whether the current step has valid data for progression.
             *
             * @returns {boolean} True when the wizard can advance.
             */
            get canContinue() {
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

            /**
             * Ensure submission only occurs when all selections are present.
             *
             * @returns {boolean} True when the wizard may queue a generation.
             */
            get canSubmit() {
                return this.selectedJob !== null && this.selectedCv !== null && this.thinkingTimeIsValid;
            },

            /**
             * Validate the thinking time slider value.
             *
             * @returns {boolean} True when the thinking time falls within the allowed range.
             */
            get thinkingTimeIsValid() {
                return typeof this.form.thinking_time === 'number' && this.form.thinking_time >= 5 && this.form.thinking_time <= 60;
            },

            /**
             * Retrieve the currently selected job document.
             *
             * @returns {object|null} The selected job document or null when not chosen.
             */
            get selectedJob() {
                return findDocument(this.jobDocuments, this.form.job_document_id);
            },

            /**
             * Retrieve the currently selected CV document.
             *
             * @returns {object|null} The selected CV document or null when not chosen.
             */
            get selectedCv() {
                return findDocument(this.cvDocuments, this.form.cv_document_id);
            },

            /**
             * Display the label for the currently selected model.
             *
             * @returns {string} The model label or raw model value when no label exists.
             */
            get selectedModelLabel() {
                for (let index = 0; index < this.models.length; index += 1) {
                    const item = this.models[index];

                    if (item && item.value === this.form.model) {
                        return item.label || item.value;
                    }
                }

                return this.form.model;
            },

            /**
             * Decide whether a step is accessible based on completed prerequisites.
             *
             * @param {number} targetStep The step index the user wants to navigate to.
             * @returns {boolean} True when the step can be accessed.
             */
            canAccessStep(targetStep) {
                const parsed = normaliseNumber(targetStep, NaN);

                if (isNaN(parsed) || parsed < 1 || parsed > this.steps.length) {
                    return false;
                }

                if (parsed === 1) {
                    return true;
                }

                if (this.form.job_document_id === null) {
                    return false;
                }

                if (parsed >= 3 && this.form.cv_document_id === null) {
                    return false;
                }

                if (parsed >= 4 && (this.form.model === '' || !this.thinkingTimeIsValid)) {
                    return false;
                }

                return true;
            },

            /**
             * Navigate to a specific step when prerequisites have been satisfied.
             *
             * @param {number} targetStep The desired step index.
             */
            goTo(targetStep) {
                if (!this.canAccessStep(targetStep)) {
                    return;
                }

                this.step = normaliseNumber(targetStep, this.step);
                this.errorMessage = '';
                this.successMessage = '';
                this.schedulePanelFocus();
            },

            /**
             * Advance the wizard to the next step.
             */
            next() {
                if (this.step < this.steps.length && this.canContinue) {
                    this.step += 1;
                    this.errorMessage = '';
                    this.successMessage = '';
                    this.schedulePanelFocus();
                }
            },

            /**
             * Return to the previous step and clear transient messaging.
             */
            previous() {
                if (this.step > 1) {
                    this.step -= 1;
                    this.errorMessage = '';
                    this.successMessage = '';
                    this.schedulePanelFocus();
                }
            },

            /**
             * Record the selected job document identifier.
             *
             * @param {*} identifier The job document identifier to store.
             */
            selectJob(identifier) {
                this.form.job_document_id = identifier;
                this.errorMessage = '';
            },

            /**
             * Record the selected CV document identifier.
             *
             * @param {*} identifier The CV document identifier to store.
             */
            selectCv(identifier) {
                this.form.cv_document_id = identifier;
                this.errorMessage = '';
            },

            /**
             * Update the selected AI model value.
             *
             * @param {string} value The identifier for the chosen model.
             */
            setModel(value) {
                this.form.model = value;
                this.errorMessage = '';
            },

            /**
             * Queue the tailoring request by sending a JSON payload to the server.
             */
            async queue() {
                if (this.isSubmitting || !this.canSubmit) {
                    return;
                }

                this.isSubmitting = true;
                this.errorMessage = '';
                this.successMessage = '';

                try {
                    const response = await fetch('/generations', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        credentials: 'same-origin',
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

                        this.errorMessage = message;
                        return;
                    }

                    const list = this.generations.slice();

                    list.unshift({
                        ...data,
                        job_document: this.selectedJob,
                        cv_document: this.selectedCv,
                        thinking_time: typeof data.thinking_time === 'number'
                            ? data.thinking_time
                            : this.form.thinking_time,
                    });

                    this.generations = list;
                    this.successMessage = 'Generation queued successfully.';
                    this.resetForm();
                } catch (error) {
                    this.errorMessage = 'A network error prevented queuing the generation.';
                } finally {
                    this.isSubmitting = false;
                }
            },

            /**
             * Format a date string for concise card and list displays.
             *
             * @param {string} value The ISO date string to format.
             * @returns {string} The formatted date or an empty string.
             */
            formatDate(value) {
                if (!value) {
                    return '';
                }

                const date = new Date(value);

                if (isNaN(date.getTime())) {
                    return value;
                }

                return date.toLocaleDateString();
            },

            /**
             * Format a timestamp string with both date and time components.
             *
             * @param {string} value The ISO timestamp to format.
             * @returns {string} The formatted timestamp or an empty string.
             */
            formatDateTime(value) {
                if (!value) {
                    return '';
                }

                const date = new Date(value);

                if (isNaN(date.getTime())) {
                    return value;
                }

                return date.toLocaleString();
            },

            /**
             * Ensure the wizard panel receives focus after content changes.
             */
            schedulePanelFocus() {
                this.$nextTick(() => {
                    this.focusPanel();
                });
            },

            /**
             * Move focus to the wizard panel to aid keyboard and screen-reader users.
             */
            focusPanel() {
                if (!this.$refs.panel) {
                    return;
                }

                if (typeof this.$refs.panel.scrollIntoView === 'function') {
                    this.$refs.panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }

                if (typeof this.$refs.panel.focus === 'function') {
                    this.$refs.panel.focus();
                }
            },
        };
    };

    /**
     * Register the Tailor CV wizard with a provided Alpine.js instance.
     *
     * @param {object} Alpine The Alpine.js instance that exposes the registration API.
     * @returns {void}
     */
    const registerTailorWizard = function (Alpine) {
        if (!Alpine || typeof Alpine.data !== 'function') {
            return;
        }

        Alpine.data('tailorWizard', tailorWizardFactory);
    };

    if (typeof window !== "undefined") {
        window.tailorWizard = tailorWizardFactory;

        if (window.Alpine) {
            registerTailorWizard(window.Alpine);
        } else {           document.addEventListener('alpine:init', function (event) {
                // Use the Alpine instance provided by the event detail, falling back to the global reference.
                const alpineInstance = event && event.detail ? event.detail : window.Alpine;

                registerTailorWizard(alpineInstance);

            });
        }
    }
})();
