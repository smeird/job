class Tabulator {
  constructor(element, options = {}) {
    const target = typeof element === 'string' ? document.querySelector(element) : element;
    if (!target) {
      throw new Error('Tabulator target element was not found.');
    }

    this.element = target;
    this.element.classList.add('tabulator-lite');
    this.options = options;
    this.columns = Array.isArray(options.columns) ? options.columns : [];
    this.data = Array.isArray(options.data) ? options.data.slice() : [];
    this.filteredData = this.data.slice();

    this.table = document.createElement('table');
    this.table.className = 'tabulator-lite__table';
    this.table.setAttribute('role', 'table');

    this.buildHeader();
    this.tbody = document.createElement('tbody');
    this.table.appendChild(this.tbody);
    this.element.appendChild(this.table);
    this.renderRows();
  }

  buildHeader() {
    const thead = document.createElement('thead');
    const row = document.createElement('tr');

    this.columns.forEach((column) => {
      const th = document.createElement('th');
      th.scope = 'col';
      th.textContent = column.title ?? column.field ?? '';
      row.appendChild(th);
    });

    thead.appendChild(row);
    this.table.appendChild(thead);
  }

  renderRows() {
    this.tbody.innerHTML = '';

    if (this.filteredData.length === 0) {
      const emptyRow = document.createElement('tr');
      const emptyCell = document.createElement('td');
      emptyCell.colSpan = this.columns.length || 1;
      emptyCell.textContent = 'No records match the current filters.';
      emptyCell.className = 'tabulator-lite__empty';
      emptyRow.appendChild(emptyCell);
      this.tbody.appendChild(emptyRow);
      return;
    }

    this.filteredData.forEach((rowData) => {
      const tr = document.createElement('tr');
      this.columns.forEach((column) => {
        const td = document.createElement('td');
        const value = rowData[column.field];

        if (typeof column.formatter === 'function') {
          const formatted = column.formatter({
            value,
            data: rowData,
            column,
            cell: td,
          });

          if (formatted instanceof Node) {
            td.appendChild(formatted);
          } else if (formatted !== undefined && formatted !== null) {
            td.innerHTML = String(formatted);
          } else {
            td.textContent = Tabulator.escape(value);
          }
        } else {
          td.textContent = Tabulator.escape(value);
        }

        tr.appendChild(td);
      });

      this.tbody.appendChild(tr);
    });
  }

  filter(query) {
    const term = String(query ?? '')
      .trim()
      .toLowerCase();

    if (!term) {
      this.filteredData = this.data.slice();
      this.renderRows();
      return;
    }

    this.filteredData = this.data.filter((row) =>
      this.columns.some((column) => {
        const rawValue = row[column.field];
        if (rawValue === undefined || rawValue === null) {
          return false;
        }

        return String(rawValue).toLowerCase().includes(term);
      }),
    );

    this.renderRows();
  }

  static escape(value) {
    if (value === undefined || value === null) {
      return '';
    }

    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }
}

window.Tabulator = Tabulator;

const THEME_STORAGE_KEY = 'app-theme-preference';

const applyTheme = (theme) => {
  const root = document.documentElement;
  const normalized = theme === 'dark' ? 'dark' : 'light';
  root.setAttribute('data-theme', normalized);

  const toggle = document.querySelector('[data-theme-toggle]');
  const label = toggle?.querySelector('[data-theme-label]');

  if (toggle) {
    toggle.setAttribute('aria-pressed', normalized === 'dark' ? 'true' : 'false');
    toggle.setAttribute('aria-label', normalized === 'dark' ? 'Switch to light theme' : 'Switch to dark theme');
  }

  if (label) {
    label.textContent = normalized === 'dark' ? 'Dark mode' : 'Light mode';
  }
};

const initializeTheme = () => {
  const stored = localStorage.getItem(THEME_STORAGE_KEY);
  const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
  const initial = stored === 'dark' || stored === 'light' ? stored : mediaQuery.matches ? 'dark' : 'light';

  applyTheme(initial);

  const toggle = document.querySelector('[data-theme-toggle]');
  toggle?.addEventListener('click', () => {
    const current = document.documentElement.getAttribute('data-theme');
    const nextTheme = current === 'dark' ? 'light' : 'dark';
    localStorage.setItem(THEME_STORAGE_KEY, nextTheme);
    applyTheme(nextTheme);
  });

  mediaQuery.addEventListener('change', (event) => {
    const preference = localStorage.getItem(THEME_STORAGE_KEY);
    if (preference === 'dark' || preference === 'light') {
      return;
    }

    applyTheme(event.matches ? 'dark' : 'light');
  });
};

const buildStatusPill = (status) => {
  const pill = document.createElement('span');
  pill.classList.add('status-pill');

  const normalized = String(status ?? '').toLowerCase();
  if (normalized === 'active' || normalized === 'success' || normalized === 'completed') {
    pill.classList.add('status-pill--success');
    pill.textContent = 'Active';
    return pill;
  }

  if (normalized === 'pending' || normalized === 'in review') {
    pill.classList.add('status-pill--pending');
    pill.textContent = 'Pending';
    return pill;
  }

  pill.classList.add('status-pill--blocked');
  pill.textContent = 'Blocked';
  return pill;
};

const initializeDataTable = () => {
  const container = document.querySelector('#data-table');
  if (!container) {
    return null;
  }

  const rows = [
    {
      workspace: 'Nova UX Research',
      owner: 'Ada Lovelace',
      status: 'active',
      usage: '87%',
      updated: '2024-07-01',
    },
    {
      workspace: 'Quantum Finance',
      owner: 'Grace Hopper',
      status: 'pending',
      usage: '54%',
      updated: '2024-06-24',
    },
    {
      workspace: 'Atlas Manufacturing',
      owner: 'George Boole',
      status: 'blocked',
      usage: '31%',
      updated: '2024-06-13',
    },
    {
      workspace: 'Lumen Analytics',
      owner: 'Evelyn Boyd',
      status: 'active',
      usage: '92%',
      updated: '2024-07-04',
    },
  ];

  const table = new Tabulator(container, {
    data: rows,
    columns: [
      { title: 'Workspace', field: 'workspace' },
      { title: 'Owner', field: 'owner' },
      {
        title: 'Status',
        field: 'status',
        formatter: ({ value }) => buildStatusPill(value),
      },
      { title: 'Usage', field: 'usage' },
      { title: 'Last Updated', field: 'updated' },
    ],
  });

  const searchInput = document.querySelector('[data-table-search]');
  searchInput?.addEventListener('input', (event) => {
    table.filter(event.target.value);
  });

  return table;
};

const initializeProgressIndicators = () => {
  document.querySelectorAll('[data-progress-bar]').forEach((bar) => {
    const value = Number.parseInt(bar.getAttribute('data-progress-bar') ?? '0', 10);
    const fallback = Number.isFinite(value) ? value : 0;
    const clamped = Math.min(100, Math.max(0, fallback));

    bar.style.setProperty('--progress', `${clamped}%`);
    bar.setAttribute('aria-valuenow', String(clamped));
    bar.setAttribute('aria-valuemin', '0');
    bar.setAttribute('aria-valuemax', '100');
  });
};

const initializeToasts = () => {
  const toasts = document.querySelectorAll('[data-toast]');
  toasts.forEach((toast, index) => {
    const dismissButton = toast.querySelector('[data-toast-dismiss]');
    const removeToast = () => {
      toast.classList.add('is-dismissed');
      window.setTimeout(() => {
        toast.remove();
      }, 280);
    };

    dismissButton?.addEventListener('click', () => removeToast());
    window.setTimeout(removeToast, 7000 + index * 650);
  });
};

const initializeWizardStepper = () => {
  const steps = document.querySelectorAll('[data-step-index]');
  steps.forEach((step) => {
    const isActive = step.getAttribute('data-step-active') === 'true';
    const isComplete = step.getAttribute('data-step-complete') === 'true';

    if (isActive) {
      step.classList.add('step--active');
      step.setAttribute('aria-current', 'step');
    }

    if (isComplete) {
      step.classList.add('step--complete');
    }
  });
};

document.addEventListener('DOMContentLoaded', () => {
  initializeTheme();
  initializeDataTable();
  initializeProgressIndicators();
  initializeToasts();
  initializeWizardStepper();
});
