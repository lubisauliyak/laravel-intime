<script>
    // Jalankan sesegera mungkin untuk menghindari FOUC (Flash of Unstyled Content)
    (function() {
        const getThemePreference = () => {
            if (typeof localStorage !== 'undefined' && localStorage.getItem('theme')) {
                return localStorage.getItem('theme');
            }
            return 'system';
        };

        const applyTheme = (theme) => {
            if (theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        };

        const theme = getThemePreference();
        applyTheme(theme);

        // Dengarkan perubahan system preference jika mode saat ini adalah 'system'
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
            if (getThemePreference() === 'system') {
                if (e.matches) {
                    document.documentElement.classList.add('dark');
                } else {
                    document.documentElement.classList.remove('dark');
                }
            }
        });
    })();
</script>
