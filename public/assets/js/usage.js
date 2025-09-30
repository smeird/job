(function () {
    /**
     * Safely extract the raw value from a potential Tabulator cell argument.
     *
     * The guard keeps the formatter callbacks resilient when Tabulator passes
     * primitive values during lifecycle events instead of full cell objects.
     */
    function getCellValue(cell) {
        if (cell && typeof cell.getValue === 'function') {
            return cell.getValue();
        }

        return cell ?? null;
    }

    const numberFormatter = new Intl.NumberFormat(undefined, { maximumFractionDigits: 0 });
    const currencyFormatter = new Intl.NumberFormat(undefined, { style: 'currency', currency: 'GBP' });
    const dateFormatter = new Intl.DateTimeFormat(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });

    function formatTokens(value) {
        return numberFormatter.format(typeof value === 'number' ? value : 0);
    }

    function formatCostFromPence(value) {
        const amount = (typeof value === 'number' ? value : 0) / 100;

        return currencyFormatter.format(amount);
    }

    function formatDate(value) {
        if (!value) {
            return '';
        }

        const parsed = new Date(value);

        if (Number.isNaN(parsed.getTime())) {
            return value;
        }

        return dateFormatter.format(parsed);
    }

    function formatMonthLabel(value) {
        if (!value) {
            return '';
        }

        const parsed = new Date(value);

        if (Number.isNaN(parsed.getTime())) {
            return value;
        }

        return parsed.toLocaleDateString(undefined, { month: 'short', year: 'numeric' });
    }

    function updateSummary(totals) {
        const current = totals?.current_month ?? {};
        const lifetime = totals?.lifetime ?? {};

        const bindings = [
            ['month-prompt', current.prompt_tokens],
            ['month-completion', current.completion_tokens],
            ['month-total', current.total_tokens],
            ['lifetime-prompt', lifetime.prompt_tokens],
            ['lifetime-completion', lifetime.completion_tokens],
            ['lifetime-total', lifetime.total_tokens],
        ];

        bindings.forEach(([key, value]) => {
            const element = document.querySelector(`[data-summary="${key}"]`);

            if (element) {
                element.textContent = formatTokens(value ?? 0);
            }
        });

        const monthCost = document.querySelector('[data-summary="month-cost"]');
        const lifetimeCost = document.querySelector('[data-summary="lifetime-cost"]');

        if (monthCost) {
            monthCost.textContent = formatCostFromPence(current.cost_pence ?? 0);
        }

        if (lifetimeCost) {
            lifetimeCost.textContent = formatCostFromPence(lifetime.cost_pence ?? 0);
        }
    }

    function renderEmptyState(rows) {
        const emptyState = document.querySelector('[data-empty-state]');

        if (!emptyState) {
            return;
        }

        if (Array.isArray(rows) && rows.length > 0) {
            emptyState.classList.add('hidden');
        } else {
            emptyState.classList.remove('hidden');
        }
    }

    function renderError(message) {
        const errorState = document.querySelector('[data-error-state]');

        if (!errorState) {
            return;
        }

        if (message) {
            errorState.textContent = message;
            errorState.classList.remove('hidden');
        } else {
            errorState.classList.add('hidden');
        }
    }

    function buildTable(rows) {
        const tableElement = document.getElementById('usage-table');

        if (!tableElement || typeof Tabulator === 'undefined') {
            return;
        }

        const data = Array.isArray(rows) ? rows : [];

        renderEmptyState(data);

        const table = new Tabulator(tableElement, {
            data,
            layout: 'fitColumns',
            reactiveData: false,
            placeholder: 'No usage recorded yet.',
            height: data.length > 8 ? 420 : undefined,
            columns: [
                {
                    title: 'Date',
                    field: 'created_at',
                    hozAlign: 'left',
                    sorter: function (a, b) {
                        return new Date(a).getTime() - new Date(b).getTime();
                    },
                    formatter: function (cell) {
                        return formatDate(getCellValue(cell));
                    },
                    width: 180,
                },
                { title: 'Model', field: 'model', hozAlign: 'left', widthGrow: 2 },
                {
                    title: 'Tokens in',
                    field: 'prompt_tokens',
                    hozAlign: 'right',
                    formatter: function (cell) {
                        return formatTokens(getCellValue(cell));
                    },
                },
                {
                    title: 'Tokens out',
                    field: 'completion_tokens',
                    hozAlign: 'right',
                    formatter: function (cell) {
                        return formatTokens(getCellValue(cell));
                    },
                },
                {
                    title: 'Total tokens',
                    field: 'total_tokens',
                    hozAlign: 'right',
                    formatter: function (cell) {
                        return formatTokens(getCellValue(cell));
                    },
                },
                {
                    title: 'Cost (£)',
                    field: 'cost_pence',
                    hozAlign: 'right',
                    formatter: function (cell) {
                        return formatCostFromPence(getCellValue(cell));
                    },
                },
            ],
        });

        return table;
    }

    function renderCharts(monthly) {
        const monthData = Array.isArray(monthly) ? monthly : [];

        const categories = monthData.map((item) => formatMonthLabel(item.month));
        const tokens = monthData.map((item) => item.total_tokens ?? 0);
        const costs = monthData.map((item) => (item.cost_pence ?? 0) / 100);

        if (typeof Highcharts !== 'undefined') {
            if (document.getElementById('usage-tokens-chart')) {
                Highcharts.chart('usage-tokens-chart', {
                    chart: { type: 'column', backgroundColor: 'rgba(15,23,42,0)' },
                    title: { text: null },
                    xAxis: {
                        categories,
                        labels: { style: { color: '#94a3b8' } },
                        lineColor: 'rgba(148, 163, 184, 0.35)',
                    },
                    yAxis: {
                        min: 0,
                        title: { text: 'Tokens', style: { color: '#cbd5f5' } },
                        labels: { style: { color: '#94a3b8' } },
                        gridLineColor: 'rgba(148, 163, 184, 0.15)',
                    },
                    legend: { enabled: false },
                    series: [{
                        name: 'Tokens',
                        data: tokens,
                        color: '#6366f1',
                    }],
                    credits: { enabled: false },
                });
            }

            if (document.getElementById('usage-cost-chart')) {
                Highcharts.chart('usage-cost-chart', {
                    chart: { type: 'spline', backgroundColor: 'rgba(15,23,42,0)' },
                    title: { text: null },
                    xAxis: {
                        categories,
                        labels: { style: { color: '#94a3b8' } },
                        lineColor: 'rgba(148, 163, 184, 0.35)',
                    },
                    yAxis: {
                        min: 0,
                        title: { text: 'Cost (GBP)', style: { color: '#cbd5f5' } },
                        labels: {
                            style: { color: '#94a3b8' },
                            formatter: function () {
                                return currencyFormatter.format(this.value ?? 0);
                            },
                        },
                        gridLineColor: 'rgba(148, 163, 184, 0.15)',
                    },
                    legend: { enabled: false },
                    series: [{
                        name: 'Cost',
                        data: costs,
                        color: '#22d3ee',
                    }],
                    credits: { enabled: false },
                    tooltip: {
                        pointFormatter: function () {
                            return '<span style="color:' + this.color + '">●</span> ' +
                                currencyFormatter.format(this.y ?? 0);
                        },
                    },
                });
            }
        }
    }

    function hydrate(data) {
        updateSummary(data?.totals ?? {});
        buildTable(data?.per_run ?? []);
        renderCharts(data?.monthly ?? []);
    }

    function loadUsage() {
        fetch('/usage/data', { headers: { Accept: 'application/json' } })
            .then((response) => {
                if (!response.ok) {
                    throw new Error('Unable to load usage data.');
                }

                return response.json();
            })
            .then((payload) => {
                renderError('');
                hydrate(payload);
            })
            .catch((error) => {
                renderError(error.message ?? 'Unexpected error.');
                renderEmptyState([]);
            });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', loadUsage);
    } else {
        loadUsage();
    }
})();
