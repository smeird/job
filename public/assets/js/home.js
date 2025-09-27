(function () {
    'use strict';

    /**
     * Toggle the displayed wizard panel so visitors can explore the workflow.
     *
     * The helper wires up step buttons, applies active state styling, and
     * reveals the corresponding panel while hiding the others.
     *
     * @param {HTMLElement|null} root The root element that wraps the wizard preview UI.
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
