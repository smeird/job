export type IconName = 'dashboard' | 'applications' | 'timeline' | 'career' | 'documents' | 'tailor' | 'profile' | 'settings' | 'logout' | 'plus' | 'upload' | 'download' | 'trash' | 'edit' | 'merge' | 'arrow-up' | 'arrow-down';

const ICON_PATHS: Record<IconName, string[]> = {
  dashboard: ['M4 4h6v6H4z', 'M14 4h6v6h-6z', 'M4 14h6v6H4z', 'M14 14h6v6h-6z'],
  applications: ['M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2', 'M4 7h16a1 1 0 0 1 1 1v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a1 1 0 0 1 1-1Z', 'M3 12h18', 'M10 12v2h4v-2'],
  timeline: ['M6 3v3', 'M18 3v3', 'M4 7h16', 'M5 5h14a1 1 0 0 1 1 1v14H4V6a1 1 0 0 1 1-1Z', 'M8 11h2', 'M14 11h2', 'M8 15h2', 'M14 15h2'],
  career: ['M12 3a4 4 0 0 1 4 4v1', 'M8 8V7a4 4 0 0 1 4-4', 'M5 21v-4a5 5 0 0 1 5-5h4a5 5 0 0 1 5 5v4', 'M9 18h6'],
  documents: ['M6 3h8l4 4v14H6z', 'M14 3v5h5', 'M9 13h6', 'M9 17h6'],
  tailor: ['m12 3 1.1 3.2L16 7.3l-2.9 1.1L12 12l-1.1-3.6L8 7.3l2.9-1.1Z', 'm18 13 .8 2.2L21 16l-2.2.8L18 19l-.8-2.2L15 16l2.2-.8Z', 'M5 13v8', 'M2 17h6'],
  profile: ['M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z', 'M4 21a8 8 0 0 1 16 0'],
  settings: ['M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z', 'M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.12 2.12-.06-.06a1.7 1.7 0 0 0-1.88-.34 1.7 1.7 0 0 0-1.02 1.56V20.3h-3v-.08a1.7 1.7 0 0 0-1.02-1.56 1.7 1.7 0 0 0-1.88.34l-.06.06-2.12-2.12.06-.06A1.7 1.7 0 0 0 7.04 15a1.7 1.7 0 0 0-1.56-1.02H5.4v-3h.08A1.7 1.7 0 0 0 7.04 9a1.7 1.7 0 0 0-.34-1.88l-.06-.06 2.12-2.12.06.06a1.7 1.7 0 0 0 1.88.34 1.7 1.7 0 0 0 1.02-1.56V3.7h3v.08a1.7 1.7 0 0 0 1.02 1.56 1.7 1.7 0 0 0 1.88-.34l.06-.06 2.12 2.12-.06.06A1.7 1.7 0 0 0 19.4 9a1.7 1.7 0 0 0 1.56 1.02h.08v3h-.08A1.7 1.7 0 0 0 19.4 15Z'],
  logout: ['M10 5H5v14h5', 'M14 8l4 4-4 4', 'M8 12h10'],
  plus: ['M12 5v14', 'M5 12h14'],
  upload: ['M12 16V4', 'm7 9 5-5 5 5', 'M5 20h14'],
  download: ['M12 4v12', 'm7 11 5 5 5-5', 'M5 20h14'],
  trash: ['M4 7h16', 'M9 7V4h6v3', 'm7 7 1 13h8l1-13', 'M10 11v5', 'M14 11v5'],
  edit: ['m4 20 4.2-1 10.6-10.6a2 2 0 0 0-2.8-2.8L5.4 16.2Z', 'm14.5 7.1 2.8 2.8'],
  merge: ['M7 4v4c0 2.2 1.8 4 4 4h6', 'm14 9 3 3-3 3', 'M7 20v-4c0-2.2 1.8-4 4-4'],
  'arrow-up': ['m7 11 5-5 5 5', 'M12 6v12'],
  'arrow-down': ['m7 13 5 5 5-5', 'M12 18V6'],
};

/** Renders one decorative, current-colour line icon alongside an accessible text label. */
export function icon(name: IconName, className = ''): string {
  const paths = ICON_PATHS[name].map((path) => `<path d="${path}"/>`).join('');
  return `<svg class="ui-icon${className ? ` ${className}` : ''}" aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">${paths}</svg>`;
}
