const STORAGE_KEY = 'structer-theme';

export function getTheme() {
    try {
        const stored = localStorage.getItem(STORAGE_KEY);

        if (stored === 'light' || stored === 'dark') {
            return stored;
        }
    } catch {
        // Ignore storage errors and fall back to the system preference.
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

export function applyTheme(theme, persist = false) {
    const next = theme === 'dark' ? 'dark' : 'light';
    const root = document.documentElement;

    root.classList.toggle('dark', next === 'dark');
    root.dataset.theme = next;
    root.style.colorScheme = next;

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        const isDark = next === 'dark';
        const label = isDark ? button.dataset.labelLight : button.dataset.labelDark;

        button.setAttribute('aria-pressed', isDark ? 'true' : 'false');

        if (label) {
            button.setAttribute('aria-label', label);
        }
    });

    if (persist) {
        try {
            localStorage.setItem(STORAGE_KEY, next);
        } catch {
            // Theme still applies for this page even if storage is blocked.
        }
    }
}

export function initThemeToggle() {
    applyTheme(getTheme());

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            applyTheme(getTheme() === 'dark' ? 'light' : 'dark', true);
        });
    });
}
