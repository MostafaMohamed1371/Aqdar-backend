<script>
    (function () {
        const storageKey = 'structer-theme';
        const root = document.documentElement;
        let theme = null;

        try {
            theme = localStorage.getItem(storageKey);
        } catch (e) {}

        if (theme !== 'light' && theme !== 'dark') {
            theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }

        root.classList.toggle('dark', theme === 'dark');
        root.dataset.theme = theme;
        root.style.colorScheme = theme;
    })();
</script>
