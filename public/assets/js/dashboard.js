(function () {
    'use strict';

    /**
     * Register the generation wizard component once Alpine initialises.
     *
     * Hooking into the alpine:init event ensures the component definition is available
     * before any declarative bindings execute.
     */
    document.addEventListener('alpine:init', function () {
        /**
         * Provide the Alpine component definition for the generation wizard interface.
         *
         * The configuration object carries preloaded data from PHP so the component
         * renders immediately with the correct documents, models, and history.
         *
         * @param {object} config The server-provided wizard configuration payload.
         * @returns {object} Alpine-compatible component state and behaviour.
         */
        Alpine.data('generationWizard', function (config) {
            /**
             * Provide a resilient array check for browsers that lack Array.isArray.
             *
             * @param {*} value The value to inspect for array characteristics.
             * @returns {boolean} True when the supplied value is an array-like structure.
             */
            const isArray = Array.isArray || function (value) {
                return Object.prototype.toString.call(value) === '[object Array]';
            };

            const models = isArray(config.models) ? config.models : [];
            const jobDocuments = isArray(config.jobDocuments) ? config.jobDocuments : [];
            const cvDocuments = isArray(config.cvDocuments) ? config.cvDocuments : [];
            const generations = isArray(config.generations) ? config.generations : [];

            /**
             * Determine whether the provided value is a finite number before using it in calculations.
             *
             * @param {*} value The value to evaluate for numeric fitness.
             * @returns {boolean} True when the input is both numeric and finite.
             */
            const isFiniteNumber = function (value) {
                return typeof value === 'number' && isFinite(value);
            };

            /**
             * Normalise the steps collection so static HTML fallbacks stay in sync with the interactive wizard behaviour.
             *
             * @param {*} value The supplied step configuration array or fallback representation.
             * @returns {Array} A sorted collection of step descriptors ready for rendering.
             */
            const normaliseSteps = function (value) {
                if (!isArray(value)) {
                    return [];
                }

                const list = [];

                for (let index = 0; index < value.length; index += 1) {
                    const stepItem = value[index];

                    if (!stepItem) {
                        continue;
                    }

                    const parsedIndex = typeof stepItem.index === 'number'
                        ? stepItem.index
                        : parseInt(stepItem.index, 10);

                    const title = typeof stepItem.title === 'string' ? stepItem.title : '';
                    const description = typeof stepItem.description === 'string' ? stepItem.description : '';
                    const helper = typeof stepItem.helper === 'string' ? stepItem.helper : '';

                    if (isFiniteNumber(parsedIndex) && title !== '') {
                        list.push({
                            index: parsedIndex,
                            title,
                            description,
                            helper,
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

            const defaultThinkingTime = isFiniteNumber(config.defaultThinkingTime)
                ? config.defaultThinkingTime
                : 30;

            const firstModelValue = models.length > 0 && typeof models[0].value === 'string'
                ? models[0].value
                : '';

            const providedSteps = normaliseSteps(config.steps);

            const steps = providedSteps.length > 0
                ? providedSteps
                : [
                    { index: 1, title: 'Choose job description', description: 'Select the role you want to tailor for.', helper: 'Pick the job posting that best matches the next application.' },
                    { index: 2, title: 'Choose CV', description: 'Decide which base CV to tailor.', helper: 'Use the CV with the strongest baseline for this role.' },
                    { index: 3, title: 'Set parameters', description: 'Adjust the model and thinking time.', helper: 'Choose the best model and allow enough thinking time for complex roles.' },
                    { index: 4, title: 'Confirm & queue', description: 'Review before submitting.', helper: 'Double-check your selections before queuing the request.' },
                ];

            return {
                step: 1,
                steps,
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
                /**
                 * Determine whether the wizard should be disabled due to missing prerequisites.
                 *
                 * @returns {boolean} True when the user lacks either job descriptions or CVs.
                 */
                get isWizardDisabled() {
                    return this.jobDocuments.length === 0 || this.cvDocuments.length === 0;
                },
                /**
                 * Evaluate whether the current step has sufficient data to continue.
                 *
                 * @returns {boolean} True when validation passes for the active step.
                 */
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
                /**
                 * Confirm whether the wizard has enough input to queue a generation.
                 *
                 * @returns {boolean} True when the required selections and timing are available.
                 */
                get canSubmit() {
                    return this.selectedJobDocument && this.selectedCvDocument && this.thinkingTimeIsValid;
                },
                /**
                 * Validate that the thinking time falls within the allowed numeric range.
                 *
                 * @returns {boolean} True when the thinking time is within five to sixty seconds.
                 */
                get thinkingTimeIsValid() {
                    return isFiniteNumber(this.form.thinking_time) && this.form.thinking_time >= 5 && this.form.thinking_time <= 60;
                },
                /**
                 * Retrieve the currently selected job document using the stored identifier.
                 *
                 * @returns {object|null} The matched job document or null when unavailable.
                 */
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
                /**
                 * Retrieve the currently selected CV document using the stored identifier.
                 *
                 * @returns {object|null} The matched CV document or null when unavailable.
                 */
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
                /**
                 * Display a human-friendly label for the chosen AI model option.
                 *
                 * @returns {string} The label associated with the selected model value.
                 */
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
                /**
                 * Move backwards through the wizard while clearing previous error messaging.
                 */
                previous() {
                    if (this.step > 1) {
                        this.step -= 1;
                        this.error = '';
                        this.successMessage = '';
                    }
                },
                /**
                 * Advance the wizard when the current step passes validation checks.
                 */
                next() {
                    if (this.step < this.steps.length && this.canMoveForward) {
                        this.step += 1;
                        this.error = '';
                    }
                },
                /**
                 * Submit the wizard payload to queue a new generation request.
                 */
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
                /**
                 * Format a date string so tabular rows remain readable.
                 *
                 * @param {string} value The source date value to convert.
                 * @returns {string} The formatted representation or an empty string.
                 */
                formatDate(value) {
                    if (!value) {
                        return '';
                    }
                    const date = new Date(value);
                    return isNaN(date.getTime()) ? value : date.toLocaleDateString();
                },
                /**
                 * Present full date and time details for generation events.
                 *
                 * @param {string} value The source timestamp to convert.
                 * @returns {string} The formatted representation or an empty string.
                 */
                formatDateTime(value) {
                    if (!value) {
                        return '';
                    }
                    const date = new Date(value);
                    return isNaN(date.getTime()) ? value : date.toLocaleString();
                },
                /**
                 * Reset the wizard to its first step, clear previous selections, and guide the user directly to the tailoring workflow.
                 */
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
})();
