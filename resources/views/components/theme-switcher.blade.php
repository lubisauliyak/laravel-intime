<div class="relative inline-block text-left" id="theme-switcher-container">
    <div>
        <button type="button" id="theme-menu-button" class="p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-white/10 rounded-xl transition-all focus:outline-none focus:ring-2 focus:ring-emerald-500" aria-expanded="true" aria-haspopup="true" title="Pilih Tema">
            <!-- Icon Light (Sun) -->
            <svg id="theme-icon-light" class="hidden w-5 h-5 md:w-6 md:h-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
            <!-- Icon Dark (Moon) -->
            <svg id="theme-icon-dark" class="hidden w-5 h-5 md:w-6 md:h-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
            </svg>
            <!-- Icon System (Desktop) -->
            <svg id="theme-icon-system" class="hidden w-5 h-5 md:w-6 md:h-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
            </svg>
        </button>
    </div>

    <!-- Dropdown menu, show/hide based on menu state. -->
    <div id="theme-dropdown-menu" class="origin-top-right absolute right-0 mt-2 w-36 rounded-2xl shadow-xl bg-white dark:bg-[#1a241e] ring-1 ring-black/5 dark:ring-white/10 focus:outline-none hidden z-50 overflow-hidden backdrop-blur-xl border border-gray-100 dark:border-white/5" role="menu" aria-orientation="vertical" aria-labelledby="theme-menu-button" tabindex="-1">
        <div class="py-1" role="none">
            <a href="#" class="theme-option text-gray-700 dark:text-gray-300 block px-4 py-2.5 text-xs font-bold uppercase tracking-wider hover:bg-gray-50 dark:hover:bg-white/5 hover:text-emerald-500 dark:hover:text-emerald-400 flex items-center transition-colors" role="menuitem" tabindex="-1" data-theme="light">
                <svg class="h-4 w-4 mr-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
                Terang
            </a>
            <a href="#" class="theme-option text-gray-700 dark:text-gray-300 block px-4 py-2.5 text-xs font-bold uppercase tracking-wider hover:bg-gray-50 dark:hover:bg-white/5 hover:text-emerald-500 dark:hover:text-emerald-400 flex items-center transition-colors" role="menuitem" tabindex="-1" data-theme="dark">
                <svg class="h-4 w-4 mr-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                </svg>
                Gelap
            </a>
            <a href="#" class="theme-option text-gray-700 dark:text-gray-300 block px-4 py-2.5 text-xs font-bold uppercase tracking-wider hover:bg-gray-50 dark:hover:bg-white/5 hover:text-emerald-500 dark:hover:text-emerald-400 flex items-center transition-colors" role="menuitem" tabindex="-1" data-theme="system">
                <svg class="h-4 w-4 mr-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
                Sistem
            </a>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const menuButton = document.getElementById('theme-menu-button');
        const dropdownMenu = document.getElementById('theme-dropdown-menu');
        const themeOptions = document.querySelectorAll('.theme-option');
        
        const iconLight = document.getElementById('theme-icon-light');
        const iconDark = document.getElementById('theme-icon-dark');
        const iconSystem = document.getElementById('theme-icon-system');

        // Fungsi untuk mengambil tema saat ini dari localStorage
        const getCurrentTheme = () => localStorage.getItem('theme') || 'system';

        // Fungsi untuk memperbarui UI tombol
        const updateUI = (theme) => {
            iconLight.classList.add('hidden');
            iconDark.classList.add('hidden');
            iconSystem.classList.add('hidden');

            if (theme === 'light') {
                iconLight.classList.remove('hidden');
            } else if (theme === 'dark') {
                iconDark.classList.remove('hidden');
            } else {
                iconSystem.classList.remove('hidden');
            }
        };

        // Initialize UI
        updateUI(getCurrentTheme());

        // Toggle dropdown
        menuButton.addEventListener('click', () => {
            dropdownMenu.classList.toggle('hidden');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!menuButton.contains(e.target) && !dropdownMenu.contains(e.target)) {
                dropdownMenu.classList.add('hidden');
            }
        });

        // Handle option click
        themeOptions.forEach(option => {
            option.addEventListener('click', (e) => {
                e.preventDefault();
                const selectedTheme = option.getAttribute('data-theme');
                
                // Simpan ke localStorage
                localStorage.setItem('theme', selectedTheme);
                
                // Terapkan ke document
                if (selectedTheme === 'dark' || (selectedTheme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                    document.documentElement.classList.add('dark');
                } else {
                    document.documentElement.classList.remove('dark');
                }
                
                // Update button UI
                updateUI(selectedTheme);
                
                // Tutup dropdown
                dropdownMenu.classList.add('hidden');
            });
        });
    });
</script>
