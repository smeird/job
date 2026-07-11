(function () {
    'use strict';

    /**
     * Resolve the CSRF token from the shared meta element.
     *
     * @returns {string} The current CSRF token or an empty string.
     */
    const csrfToken = function () {
        const meta = document.querySelector('meta[name="csrf-token"]');

        return meta ? String(meta.getAttribute('content') || '') : '';
    };

    /**
     * Update the inline import status message for the active form.
     *
     * @param {HTMLElement|null} element The message node beside the URL field.
     * @param {string} message The message to display.
     * @param {boolean} isError Whether the message represents an error.
     * @returns {void}
     */
    const setStatus = function (element, message, isError) {
        if (!element) {
            return;
        }

        element.textContent = message;
        element.classList.remove('hidden', 'text-indigo-200', 'text-rose-200');
        element.classList.add(isError ? 'text-rose-200' : 'text-indigo-200');
    };

    /**
     * Import the job description from the URL field into the current form.
     *
     * @param {HTMLButtonElement} button The clicked import button.
     * @returns {Promise<void>} Completes after the import request resolves.
     */
    const importDescription = async function (button) {
        const form = button.closest('form');
        const urlInput = form ? form.querySelector('[name="source_url"]') : null;
        const titleInput = form ? form.querySelector('[name="title"]') : null;
        const descriptionInput = form ? form.querySelector('[name="description"]') : null;
        const status = form ? form.querySelector('[data-fetch-description-status]') : null;
        const sourceUrl = urlInput ? String(urlInput.value || '') : '';

        button.disabled = true;
        setStatus(status, 'Importing job advert…', false);

        try {
            const response = await fetch('/applications/fetch-description', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                    'X-CSRF-Token': csrfToken(),
                },
                body: new URLSearchParams({ source_url: sourceUrl, _token: csrfToken() }).toString(),
            });
            const payload = await response.json();

            if (!response.ok || payload.status !== 'ok') {
                throw new Error(payload.message || 'Unable to import the job advert.');
            }

            if (titleInput && titleInput.value.trim() === '' && payload.data.title) {
                titleInput.value = payload.data.title;
            }

            if (descriptionInput) {
                descriptionInput.value = payload.data.description || '';
            }

            setStatus(status, 'Imported. Review the text before saving.', false);
        } catch (error) {
            setStatus(status, error && error.message ? error.message : 'Unable to import the job advert.', true);
        } finally {
            button.disabled = false;
        }
    };

    /**
     * Attach import handlers to all job posting forms on the page.
     *
     * @returns {void}
     */
    const initialise = function () {
        const buttons = document.querySelectorAll('[data-fetch-description]');

        buttons.forEach(function (button) {
            button.addEventListener('click', function () {
                importDescription(button);
            });
        });
    };

    document.addEventListener('DOMContentLoaded', initialise);
}());
