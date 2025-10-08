(function () {
    'use strict';

    /**
     * Read the CSRF token placed within the document head meta tags.
     *
     * @returns {string} The token value or an empty string when missing.
     */
    const resolveCsrfToken = function () {
        const meta = document.querySelector('meta[name="csrf-token"]');

        if (!meta) {
            return '';
        }

        const value = meta.getAttribute('content');

        return typeof value === 'string' ? value : '';
    };

    /**
     * Normalise arbitrary identifiers into a string for DOM queries.
     *
     * @param {*} value The raw value sourced from data attributes.
     * @returns {string} A string identifier or an empty fallback.
     */
    const normaliseId = function (value) {
        if (typeof value === 'string') {
            return value;
        }

        if (typeof value === 'number' && isFinite(value)) {
            return String(value);
        }

        return '';
    };

    /**
     * Toggle the hidden state for an element using the `hidden` attribute.
     *
     * @param {HTMLElement|null} element The element to show or hide.
     * @param {boolean} shouldHide Indicates whether the element should be hidden.
     * @returns {void}
     */
    const setHiddenState = function (element, shouldHide) {
        if (!element) {
            return;
        }

        if (shouldHide) {
            element.setAttribute('hidden', 'hidden');
            return;
        }

        element.removeAttribute('hidden');
    };

    /**
     * Collect important child elements from the research panel.
     *
     * @param {HTMLElement} panel The container element holding research output.
     * @returns {{content: HTMLElement|null, loading: HTMLElement|null, error: HTMLElement|null, meta: HTMLElement|null}} Resolved child references.
     */
    const locatePanelElements = function (panel) {
        return {
            content: panel.querySelector('[data-research-content]'),
            loading: panel.querySelector('[data-research-loading]'),
            error: panel.querySelector('[data-research-error]'),
            meta: panel.querySelector('[data-research-meta]'),
        };
    };

    /**
     * Clear the research content container to prepare for new markup.
     *
     * @param {HTMLElement|null} container The container that receives generated markup.
     * @returns {void}
     */
    const resetContent = function (container) {
        if (!container) {
            return;
        }

        while (container.firstChild) {
            container.removeChild(container.firstChild);
        }
    };

    /**
     * Append a list element with supplied items to the container.
     *
     * @param {(HTMLElement|DocumentFragment)} container The container accepting the list.
     * @param {Array} items The collection of string items.
     * @returns {boolean} Indicates whether list items were appended.
     */
    const appendList = function (container, items) {
        if (!container || !Array.isArray(items) || items.length === 0) {
            return false;
        }

        const list = document.createElement('ul');
        list.className = 'list-disc space-y-1 pl-5 text-left';

        let appended = false;

        for (let index = 0; index < items.length; index += 1) {
            const item = items[index];

            if (typeof item !== 'string' || item.trim() === '') {
                continue;
            }

            const listItem = document.createElement('li');
            listItem.textContent = item.trim();
            list.appendChild(listItem);
            appended = true;
        }

        if (!appended) {
            return false;
        }

        container.appendChild(list);

        return true;
    };

    /**
     * Render markdown-style content into a container with minimal formatting.
     *
     * @param {string} markdownText The markdown text returned by the API.
     * @param {HTMLElement|null} container The container that receives rendered HTML.
     * @returns {boolean} Indicates whether any markdown content was rendered.
     */
    const renderMarkdownContent = function (markdownText, container) {
        if (!container || typeof markdownText !== 'string' || markdownText.trim() === '') {
            return false;
        }

        const text = markdownText.replace(/\r\n/g, '\n');
        const lines = text.split('\n');
        const fragment = document.createDocumentFragment();

        let paragraphBuffer = [];
        let hasRendered = false;

        /**
         * Flush the buffered paragraph lines into a paragraph element.
         *
         * @returns {void}
         */
        const flushParagraph = function () {
            if (paragraphBuffer.length === 0) {
                return;
            }

            const paragraph = document.createElement('p');
            paragraph.textContent = paragraphBuffer.join(' ');
            fragment.appendChild(paragraph);
            paragraphBuffer = [];
            hasRendered = true;
        };

        let activeList = null;

        for (let index = 0; index < lines.length; index += 1) {
            const rawLine = lines[index];
            const line = typeof rawLine === 'string' ? rawLine.trim() : '';

            if (line === '') {
                flushParagraph();
                activeList = null;
                continue;
            }

            if (line.charAt(0) === '-' || line.charAt(0) === '*') {
                const cleaned = line.replace(/^[-*]\s*/, '');

                flushParagraph();

                if (!activeList) {
                    activeList = document.createElement('ul');
                    activeList.className = 'list-disc space-y-1 pl-5 text-left';
                    fragment.appendChild(activeList);
                }

                const listItem = document.createElement('li');
                listItem.textContent = cleaned;
                activeList.appendChild(listItem);
                hasRendered = true;
                continue;
            }

            if (line.charAt(0) === '#') {
                const levelMatch = line.match(/^(#+)\s*(.*)$/);
                const headingText = levelMatch && levelMatch[2] ? levelMatch[2].trim() : line.replace(/^#+\s*/, '').trim();

                flushParagraph();
                activeList = null;

                if (headingText !== '') {
                    const heading = document.createElement('h3');
                    heading.className = 'text-sm font-semibold text-indigo-200 theme-light:text-indigo-600';
                    heading.textContent = headingText;
                    fragment.appendChild(heading);
                    hasRendered = true;
                }

                continue;
            }

            activeList = null;
            paragraphBuffer.push(line);
        }

        flushParagraph();

        if (!hasRendered) {
            return false;
        }

        resetContent(container);
        container.appendChild(fragment);

        return true;
    };

    /**
     * Render structured sections or highlight lists when provided.
     *
     * @param {object} payload The payload returned from the API.
     * @param {HTMLElement|null} container The container that receives rendered HTML.
     * @returns {boolean} Indicates whether any structured content was rendered.
     */
    const renderStructuredContent = function (payload, container) {
        if (!container || !payload || typeof payload !== 'object') {
            return false;
        }

        const fragment = document.createDocumentFragment();
        let hasRendered = false;

        if (Array.isArray(payload.sections) && payload.sections.length > 0) {
            for (let index = 0; index < payload.sections.length; index += 1) {
                const section = payload.sections[index];

                if (!section || typeof section !== 'object') {
                    continue;
                }

                const title = typeof section.title === 'string' ? section.title.trim() : '';
                const points = Array.isArray(section.points) ? section.points : section.items;

                if (title !== '') {
                    const heading = document.createElement('h3');
                    heading.className = 'text-sm font-semibold text-indigo-200 theme-light:text-indigo-600';
                    heading.textContent = title;
                    fragment.appendChild(heading);
                    hasRendered = true;
                }

                if (Array.isArray(points) && points.length > 0) {
                    const list = document.createElement('ul');
                    list.className = 'list-disc space-y-1 pl-5 text-left';

                    for (let pointIndex = 0; pointIndex < points.length; pointIndex += 1) {
                        const value = points[pointIndex];

                        if (typeof value !== 'string' || value.trim() === '') {
                            continue;
                        }

                        const listItem = document.createElement('li');
                        listItem.textContent = value.trim();
                        list.appendChild(listItem);
                    }

                    if (list.childNodes.length > 0) {
                        fragment.appendChild(list);
                        hasRendered = true;
                    }
                }
            }
        }

        if (!hasRendered) {
            const highlights = Array.isArray(payload.highlights)
                ? payload.highlights
                : (Array.isArray(payload.points) ? payload.points : null);

            if (Array.isArray(highlights) && highlights.length > 0) {
                hasRendered = appendList(fragment, highlights);
            }
        }

        if (!hasRendered) {
            return false;
        }

        resetContent(container);
        container.appendChild(fragment);

        return true;
    };

    /**
     * Resolve the primary payload object from the API response.
     *
     * @param {*} response The decoded JSON response.
     * @returns {object|null} The payload object or null when absent.
     */
    const resolvePayload = function (response) {
        if (!response || typeof response !== 'object') {
            return null;
        }

        if (response.data && typeof response.data === 'object') {
            return response.data;
        }

        if (response.result && typeof response.result === 'object') {
            return response.result;
        }

        return response;
    };

    /**
     * Derive a readable timestamp description for screen readers.
     *
     * @param {object} payload The payload containing optional timestamp metadata.
     * @returns {{message: string, raw: string}|null} A prepared timestamp description or null when unavailable.
     */
    const resolveTimestamp = function (payload) {
        if (!payload || typeof payload !== 'object') {
            return null;
        }

        const candidates = [
            payload.generated_at,
            payload.generatedAt,
            payload.cached_at,
            payload.cachedAt,
            payload.updated_at,
            payload.updatedAt,
        ];

        let timestamp = '';

        for (let index = 0; index < candidates.length; index += 1) {
            const value = candidates[index];

            if (typeof value === 'string' && value.trim() !== '') {
                timestamp = value.trim();
                break;
            }
        }

        if (timestamp === '') {
            return null;
        }

        const prefixSources = [payload.cached, payload.fromCache, payload.is_cached, payload.cachedResult];
        let prefix = 'Generated';

        for (let index = 0; index < prefixSources.length; index += 1) {
            const value = prefixSources[index];

            if (value === true || value === 'true') {
                prefix = 'Cached result';
                break;
            }
        }

        const parsed = new Date(timestamp);
        let readable = timestamp;

        if (!isNaN(parsed.getTime()) && typeof parsed.toLocaleString === 'function') {
            readable = parsed.toLocaleString(undefined, {
                dateStyle: 'medium',
                timeStyle: 'short',
            });
        }

        return {
            message: prefix + ' ' + readable + '.',
            raw: timestamp,
        };
    };

    /**
     * Display the loading state for the research panel.
     *
     * @param {HTMLElement} panel The container element that reports busy state.
     * @param {{loading: HTMLElement|null, content: HTMLElement|null, error: HTMLElement|null, meta: HTMLElement|null}} elements The resolved child references.
     * @returns {void}
     */
    const presentLoadingState = function (panel, elements) {
        panel.setAttribute('aria-busy', 'true');
        panel.dataset.researchLoading = 'true';

        setHiddenState(elements.loading, false);
        setHiddenState(elements.content, true);
        setHiddenState(elements.error, true);

        if (elements.meta) {
            elements.meta.textContent = '';
            setHiddenState(elements.meta, true);
        }
    };

    /**
     * Present an error message to the user when the request fails.
     *
     * @param {HTMLElement} panel The container element that reports busy state.
     * @param {{loading: HTMLElement|null, content: HTMLElement|null, error: HTMLElement|null, meta: HTMLElement|null}} elements The resolved child references.
     * @param {string} message The error message to display.
     * @returns {void}
     */
    const presentErrorState = function (panel, elements, message) {
        panel.setAttribute('aria-busy', 'false');
        panel.dataset.researchLoading = 'false';

        setHiddenState(elements.loading, true);
        setHiddenState(elements.content, true);

        if (elements.error) {
            const heading = elements.error.querySelector('[data-research-error-heading]');
            const body = elements.error.querySelector('[data-research-error-body]');

            if (heading) {
                heading.textContent = message;
            } else {
                const fallbackHeading = document.createElement('p');
                fallbackHeading.textContent = message;
                elements.error.appendChild(fallbackHeading);
            }

            if (body) {
                body.textContent = 'Please try again in a moment.';
            }

            setHiddenState(elements.error, false);
        }

        if (elements.meta) {
            elements.meta.textContent = '';
            setHiddenState(elements.meta, true);
        }
    };

    /**
     * Present the rendered research content to the user.
     *
     * @param {HTMLElement} panel The container element that reports busy state.
     * @param {{loading: HTMLElement|null, content: HTMLElement|null, error: HTMLElement|null, meta: HTMLElement|null}} elements The resolved child references.
     * @param {object|null} payload The payload returned from the API.
     * @returns {boolean} Indicates whether content was successfully rendered.
     */
    const presentContentState = function (panel, elements, payload) {
        panel.setAttribute('aria-busy', 'false');
        panel.dataset.researchLoading = 'false';

        setHiddenState(elements.loading, true);
        setHiddenState(elements.error, true);

        let rendered = false;

        if (payload) {
            if (!rendered && typeof payload.markdown === 'string') {
                rendered = renderMarkdownContent(payload.markdown, elements.content);
            }

            if (!rendered && typeof payload.content === 'string') {
                rendered = renderMarkdownContent(payload.content, elements.content);
            }

            if (!rendered) {
                rendered = renderStructuredContent(payload, elements.content);
            }
        }

        if (!rendered && elements.content) {
            resetContent(elements.content);
            const paragraph = document.createElement('p');
            paragraph.textContent = 'No research notes were returned for this application.';
            elements.content.appendChild(paragraph);
            rendered = true;
        }

        if (elements.content) {
            setHiddenState(elements.content, !rendered);
        }

        if (elements.meta) {
            const timestamp = resolveTimestamp(payload || {});

            if (timestamp) {
                elements.meta.textContent = timestamp.message;
                elements.meta.setAttribute('data-timestamp', timestamp.raw);
                setHiddenState(elements.meta, false);
            } else {
                elements.meta.textContent = '';
                setHiddenState(elements.meta, true);
            }
        }

        return rendered;
    };

    /**
     * Perform the POST request that retrieves company research insights.
     *
     * @param {HTMLButtonElement} button The trigger that initiated the request.
     * @param {HTMLElement} panel The container element that displays responses.
     * @param {{loading: HTMLElement|null, content: HTMLElement|null, error: HTMLElement|null, meta: HTMLElement|null}} elements The resolved child references.
     * @param {string} csrfToken The CSRF token applied to the request headers.
     * @returns {void}
     */
    const requestResearch = function (button, panel, elements, csrfToken) {
        if (!button || !panel) {
            return;
        }

        const identifier = normaliseId(button.getAttribute('data-application-id'));

        if (identifier === '') {
            presentErrorState(panel, elements, 'Missing application identifier.');
            return;
        }

        const title = button.getAttribute('data-application-title') || '';
        const endpoint = '/applications/' + encodeURIComponent(identifier) + '/research';

        presentLoadingState(panel, elements);

        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        };

        if (csrfToken !== '') {
            headers['X-CSRF-Token'] = csrfToken;
        }

        fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: headers,
            body: JSON.stringify({
                title: title,
            }),
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Unable to fetch research details at this time.');
                }

                return response.json();
            })
            .then(function (json) {
                const payload = resolvePayload(json);

                if (json && json.success === false) {
                    const message = typeof json.message === 'string' && json.message !== ''
                        ? json.message
                        : 'The research endpoint reported an error.';
                    throw new Error(message);
                }

                presentContentState(panel, elements, payload);
                panel.dataset.researchLoaded = 'true';
            })
            .catch(function (error) {
                presentErrorState(panel, elements, error.message || 'Unable to fetch research details at this time.');
            });
    };

    /**
     * Show the research panel and optionally toggle it closed when already visible.
     *
     * @param {HTMLButtonElement} button The button that toggles the panel.
     * @param {HTMLElement} panel The panel element to toggle.
     * @returns {boolean} Indicates whether the panel remains visible after toggling.
     */
    const togglePanelVisibility = function (button, panel) {
        const classList = panel.classList;
        const isHidden = classList.contains('hidden');

        if (isHidden) {
            classList.remove('hidden');
            panel.setAttribute('aria-hidden', 'false');

            if (typeof panel.focus === 'function') {
                panel.focus();
            }

            button.setAttribute('aria-expanded', 'true');
            return true;
        }

        classList.add('hidden');
        panel.setAttribute('aria-hidden', 'true');
        button.setAttribute('aria-expanded', 'false');

        return false;
    };

    const csrfToken = resolveCsrfToken();
    const triggers = document.querySelectorAll('[data-research-trigger]');

    for (let index = 0; index < triggers.length; index += 1) {
        const trigger = triggers[index];

        trigger.addEventListener('click', function (event) {
            event.preventDefault();

            const button = event.currentTarget;
            const targetId = button.getAttribute('aria-controls');
            const panelId = normaliseId(targetId);

            if (panelId === '') {
                return;
            }

            const panel = document.getElementById(panelId);

            if (!panel) {
                return;
            }

            const isVisible = togglePanelVisibility(button, panel);

            if (!isVisible) {
                return;
            }

            const elements = locatePanelElements(panel);

            if (panel.dataset.researchLoading === 'true') {
                presentLoadingState(panel, elements);
                return;
            }

            if (panel.dataset.researchLoaded === 'true') {
                panel.setAttribute('aria-busy', 'false');
                panel.dataset.researchLoading = 'false';
                setHiddenState(elements.loading, true);
                setHiddenState(elements.error, true);

                if (elements.content) {
                    setHiddenState(elements.content, false);
                }

                if (elements.meta && elements.meta.textContent && elements.meta.textContent.trim() !== '') {
                    setHiddenState(elements.meta, false);
                }

                return;
            }

            requestResearch(button, panel, elements, csrfToken);
        });
    }
})();
