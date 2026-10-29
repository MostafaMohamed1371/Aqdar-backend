<script>
    (function () {
        const storageKey = 'structer-theme';
        const root = document.documentElement;

        root.classList.remove('dark');
        root.dataset.theme = 'light';
        root.style.colorScheme = 'light';

        try {
            localStorage.setItem(storageKey, 'light');
        } catch (e) {}
    })();
</script>
