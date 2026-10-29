const STORAGE_KEY = 'structer-theme';

export function getTheme() {
    return 'light';
}

export function applyTheme() {
    const root = document.documentElement;

    root.classList.remove('dark');
    root.dataset.theme = 'light';
    root.style.colorScheme = 'light';
    localStorage.setItem(STORAGE_KEY, 'light');
}

export function initThemeToggle() {
    applyTheme();
}
