<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Generus - Sistem Absensi &amp; Keanggotaan Cerdas</title>
    <link href="https://fonts.googleapis.com" rel="preconnect" />
    <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect" />
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200;300;400;500;600;700;800&amp;display=swap"
        rel="stylesheet" />
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet" />
    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap"
        rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#065f46", "primary-dark": "#064e3b",
                        "background-light": "#f6f7f8",
                        "background-dark": "#0d1511",
                        "teal-accent": "#8da399", "status-late": "#b45309",
                    },
                    fontFamily: {
                        "display": ["Manrope", "sans-serif"]
                    },
                    borderRadius: {
                        "DEFAULT": "0.25rem",
                        "lg": "0.5rem",
                        "xl": "0.75rem",
                        "2xl": "1rem",
                        "full": "9999px"
                    },
                    backgroundImage: {
                        'hero-gradient': 'linear-gradient(135deg, #065f46 0%, #064e3b 50%, #8da399 100%)',
                    }
                },
            },
        }
    </script>
    <style>
        body {
            font-family: 'Manrope', sans-serif;
        }
    </style>
</head>

<body
    class="bg-background-light dark:bg-background-dark text-slate-800 dark:text-slate-100 font-display antialiased selection:bg-primary selection:text-white">
    <nav
        class="fixed w-full z-50 bg-white/90 dark:bg-background-dark/90 backdrop-blur-md border-b border-slate-200 dark:border-slate-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">
                <div class="flex-shrink-0 flex items-center gap-2">
                    <span class="material-icons-round text-primary text-3xl">schedule</span>
                    <span class="font-bold text-2xl tracking-tight text-slate-900 dark:text-white">Generus</span>
                </div>
                <div class="hidden md:flex space-x-8 items-center">
                    <a class="text-slate-600 dark:text-slate-300 hover:text-primary dark:hover:text-primary transition-colors font-medium"
                        href="#">Beranda</a>
                    <a class="text-slate-600 dark:text-slate-300 hover:text-primary dark:hover:text-primary transition-colors font-medium"
                        href="#features">Fitur</a>
                    <a class="text-slate-600 dark:text-slate-300 hover:text-primary dark:hover:text-primary transition-colors font-medium cursor-pointer"
                        onclick="openModal('modal-blog')">Blog</a>
                    <a class="text-slate-600 dark:text-slate-300 hover:text-primary dark:hover:text-primary transition-colors font-medium cursor-pointer"
                        onclick="openModal('modal-karir')">Karir</a>
                    <a class="text-slate-600 dark:text-slate-300 hover:text-primary dark:hover:text-primary transition-colors font-medium cursor-pointer"
                        onclick="openModal('modal-tentang')">Tentang Kami</a>
                    @auth
                        <a class="px-5 py-2.5 rounded-full bg-primary text-white font-semibold hover:bg-primary-dark transition-all shadow-lg shadow-primary/30"
                            href="{{ route('filament.admin.pages.dashboard') }}">
                            Dashboard
                        </a>
                    @else
                        <a class="px-5 py-2.5 rounded-full bg-primary text-white font-semibold hover:bg-primary-dark transition-all shadow-lg shadow-primary/30"
                            href="{{ route('filament.admin.auth.login') }}">
                            Masuk
                        </a>
                    @endauth
                </div>
                <div class="md:hidden flex items-center">
                    <button id="mobile-menu-button"
                        class="text-slate-600 dark:text-slate-300 hover:text-primary focus:outline-none p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                        <span id="menu-icon" class="material-icons-round text-3xl">menu</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Menu -->
        <div id="mobile-menu"
            class="hidden md:hidden absolute top-20 left-0 w-full bg-white/95 dark:bg-background-dark/95 backdrop-blur-md border-b border-slate-200 dark:border-slate-800 shadow-xl z-40">
            <div class="px-4 py-6 space-y-4">
                <a class="block text-lg font-semibold text-slate-600 dark:text-slate-300 hover:text-primary dark:hover:text-primary transition-colors"
                    href="#">Beranda</a>
                <a class="block text-lg font-semibold text-slate-600 dark:text-slate-300 hover:text-primary dark:hover:text-primary transition-colors"
                    href="#features">Fitur</a>
                <a class="block text-lg font-semibold text-slate-600 dark:text-slate-300 hover:text-primary dark:hover:text-primary transition-colors cursor-pointer"
                    onclick="openModal('modal-tentang')">Tentang Kami</a>
                <a class="block text-lg font-semibold text-slate-600 dark:text-slate-300 hover:text-primary dark:hover:text-primary transition-colors cursor-pointer"
                    onclick="openModal('modal-karir')">Karir</a>
                <a class="block text-lg font-semibold text-slate-600 dark:text-slate-300 hover:text-primary dark:hover:text-primary transition-colors cursor-pointer"
                    onclick="openModal('modal-blog')">Blog</a>

                <div class="pt-4 border-t border-slate-100 dark:border-slate-800">
                    @auth
                        <a class="flex justify-center items-center w-full px-6 py-3 rounded-full bg-primary text-white font-bold hover:bg-primary-dark transition-all shadow-lg shadow-primary/30"
                            href="{{ route('filament.admin.pages.dashboard') }}">
                            Dashboard
                        </a>
                    @else
                        <a class="flex justify-center items-center w-full px-6 py-3 rounded-full bg-primary text-white font-bold hover:bg-primary-dark transition-all shadow-lg shadow-primary/30"
                            href="{{ route('filament.admin.auth.login') }}">
                            Masuk Ke Aplikasi
                        </a>
                    @endauth
                </div>
            </div>
        </div>
    </nav>
    <section class="relative pt-32 pb-20 lg:pt-40 lg:pb-32 overflow-hidden">
        <div class="absolute inset-0 bg-hero-gradient opacity-10 dark:opacity-20 pointer-events-none"></div>
        <div class="absolute -top-24 -right-24 w-96 h-96 bg-teal-accent/20 rounded-full blur-3xl pointer-events-none">
        </div>
        <div class="absolute top-1/2 -left-24 w-72 h-72 bg-primary/20 rounded-full blur-3xl pointer-events-none"></div>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                <div class="space-y-8">
                    <div
                        class="inline-flex items-center space-x-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-full px-4 py-1.5 shadow-sm">
                        <span class="flex h-2 w-2 rounded-full bg-teal-accent animate-pulse"></span>
                        <span class="text-sm font-semibold text-slate-600 dark:text-slate-300">Versi 2.0 Kini
                            Tersedia</span>
                    </div>
                    <h1
                        class="text-5xl lg:text-6xl font-extrabold tracking-tight leading-tight text-slate-900 dark:text-white">
                        Efisiensi Tinggi dengan <span
                            class="text-transparent bg-clip-text bg-gradient-to-r from-primary to-teal-accent">Scan
                            QR</span>
                    </h1>
                    <p class="text-lg text-slate-600 dark:text-slate-300 max-w-lg leading-relaxed">
                        Solusi absensi dan keanggotaan cerdas untuk organisasi besar di Indonesia. Kelola ribuan anggota
                        dengan data real-time dan analitik mendalam.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4">
                        <a class="inline-flex justify-center items-center px-8 py-4 rounded-xl bg-primary text-white font-bold text-lg hover:bg-primary-dark transition-all shadow-xl shadow-primary/30 transform hover:-translate-y-1"
                            href="{{ route('filament.admin.auth.login') }}">
                            Mulai Sekarang
                            <span class="material-icons-round ml-2">arrow_forward</span>
                        </a>
                        <a href="javascript:openModal('modal-demo')"
                            class="inline-flex justify-center items-center px-8 py-4 rounded-xl bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 font-bold text-lg hover:bg-slate-50 dark:hover:bg-slate-700 transition-all cursor-pointer">
                            <span class="material-icons-round mr-2 text-primary">play_circle</span>
                            Demo Video
                        </a>
                    </div>
                    <div class="pt-4 flex items-center gap-4 text-sm text-slate-500 dark:text-slate-400 font-medium">
                        <div class="flex -space-x-2">
                            <img alt="User Avatar 1"
                                class="w-8 h-8 rounded-full border-2 border-white dark:border-background-dark object-cover"
                                src="https://lh3.googleusercontent.com/aida-public/AB6AXuCBNKQmBgYtawx0eWZJ9vfZslP1SCrNkTbPnDU0LbkyknqRLaZqHStYlYV-wxMyrnQW4AEU4KwR-V03xoPsSLVq7oBh_dX_uBjwQQO24FeOtgmXJQKvEUOBcMTm3xdTuimkv0HDIP-VVT6d7IEcIygwWYuL5OHbOZB-fbwaVyJB8w5ic66zYbtMg1uAzkFZSQo1gP40Gn4AWVfZ3zE-6ILsPyt3vBCaZicqBniyM_Qjt9G1S2JnokRdbgrNByPvQQag6bC0a-r-Jro" />
                            <img alt="User Avatar 2"
                                class="w-8 h-8 rounded-full border-2 border-white dark:border-background-dark object-cover"
                                src="https://lh3.googleusercontent.com/aida-public/AB6AXuB2g_FnELCV1SJGSJXe5e82Qtog8XmqaupdV09ss2MV-ohF-xGsTujydgMOWlgPxOy1mvZtzgYhj13SZL6-O-tTi3eWFM6ilyTxopI2lSSTdlnWE5P4SgFGWV4Lho5qp1DGPx1XXUT2OpWqD7Z8T0S9tk-1MFNM1y1QUkUHJbZmzNHw8gTWztd5UD0ITdFFQQT4lw3td6A5kebRIsM6M9Zk1UlYrEzYqIHCc7NjmH35R7suk6QE6Jx-dDUJhr7fwRLQdigpIIWx0vE" />
                            <img alt="User Avatar 3"
                                class="w-8 h-8 rounded-full border-2 border-white dark:border-background-dark object-cover"
                                src="https://lh3.googleusercontent.com/aida-public/AB6AXuC-t8Y29C2_-hQ5HmYekZa_MRZFnsOp3v9bMnmlqHtC81JnNqYJjWq57__HmCL1HWJzLBjSdhY3Gxgqhwzky4JfWqPe5oZah-QOyCRR76NPRgTlEuVEANBQB0bRaRKOAKNRs_CXSoxCZeyiqH8v8haevl9lwDLLv-bzQYKTdTzsKlLMxwkMi91gjg_lB7pwjphxlrriC9-sTuxWT7zEIM-VWBtf8-irahnOb6BjeFxVnZn1bOscVlZJWdIjrdb_qA-1Zppeit-o8Hs" />
                            <div
                                class="w-8 h-8 rounded-full border-2 border-white dark:border-background-dark bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-xs text-slate-600 dark:text-slate-300 font-bold">
                                +2k</div>
                        </div>
                        <p>Organisasi telah bergabung</p>
                    </div>
                </div>
                <div class="relative lg:h-[600px] flex items-center justify-center">
                    <div
                        class="relative w-full max-w-md aspect-[4/5] bg-white dark:bg-slate-800 rounded-2xl shadow-2xl border border-slate-100 dark:border-slate-700 p-6 overflow-hidden transform rotate-2 hover:rotate-0 transition-transform duration-500">
                        <div class="flex justify-between items-center mb-6">
                            <div>
                                <h3 class="font-bold text-slate-900 dark:text-white text-lg">Dashboard Absensi</h3>
                                <p class="text-xs text-slate-500">Senin, 24 Okt 2023</p>
                            </div>
                            <div
                                class="h-10 w-10 bg-primary/10 rounded-full flex items-center justify-center text-primary">
                                <span class="material-icons-round">qr_code_scanner</span>
                            </div>
                        </div>
                        <div
                            class="h-40 w-full bg-gradient-to-b from-primary/5 to-transparent rounded-xl border border-primary/10 mb-6 relative overflow-hidden flex items-end px-2 pb-2 gap-2">
                            <div class="w-1/6 bg-primary/40 h-1/3 rounded-t-sm"></div>
                            <div class="w-1/6 bg-primary/60 h-2/3 rounded-t-sm"></div>
                            <div class="w-1/6 bg-primary h-full rounded-t-sm"></div>
                            <div class="w-1/6 bg-primary/80 h-4/5 rounded-t-sm"></div>
                            <div class="w-1/6 bg-primary/50 h-1/2 rounded-t-sm"></div>
                            <div class="w-1/6 bg-primary/30 h-1/4 rounded-t-sm"></div>
                        </div>
                        <div class="space-y-3">
                            <div
                                class="flex items-center p-3 rounded-lg bg-slate-50 dark:bg-slate-700/50 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                                <div
                                    class="h-10 w-10 rounded-full bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mr-3">
                                    <span class="material-icons-round text-lg">check_circle</span>
                                </div>
                                <div>
                                    <p class="font-bold text-sm text-slate-800 dark:text-slate-200">Ahmad Fauzi</p>
                                    <p class="text-xs text-slate-500">Hadir • 07:45 WIB</p>
                                </div>
                                <span
                                    class="ml-auto text-xs font-semibold text-emerald-600 dark:text-emerald-400 bg-emerald-100 dark:bg-emerald-900/20 px-2 py-1 rounded">Tepat
                                    Waktu</span>
                            </div>
                            <div
                                class="flex items-center p-3 rounded-lg bg-slate-50 dark:bg-slate-700/50 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                                <div
                                    class="h-10 w-10 rounded-full bg-amber-100 dark:bg-amber-900/30 text-status-late flex items-center justify-center mr-3">
                                    <span class="material-icons-round text-lg">schedule</span>
                                </div>
                                <div>
                                    <p class="font-bold text-sm text-slate-800 dark:text-slate-200">Siti Aminah</p>
                                    <p class="text-xs text-slate-500">Terlambat • 08:15 WIB</p>
                                </div>
                                <span
                                    class="ml-auto text-xs font-semibold text-status-late bg-amber-100 dark:bg-amber-900/20 px-2 py-1 rounded">Terlambat</span>
                            </div>
                            <div
                                class="flex items-center p-3 rounded-lg bg-slate-50 dark:bg-slate-700/50 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                                <div
                                    class="h-10 w-10 rounded-full bg-primary/10 text-primary flex items-center justify-center mr-3">
                                    <span class="material-icons-round text-lg">check_circle</span>
                                </div>
                                <div>
                                    <p class="font-bold text-sm text-slate-800 dark:text-slate-200">Budi Santoso</p>
                                    <p class="text-xs text-slate-500">Hadir • 07:50 WIB</p>
                                </div>
                                <span
                                    class="ml-auto text-xs font-semibold text-primary bg-primary/10 px-2 py-1 rounded">Tepat
                                    Waktu</span>
                            </div>
                        </div>
                    </div>
                    <div class="absolute bottom-10 -left-4 bg-white dark:bg-slate-800 p-4 rounded-xl shadow-xl border border-slate-100 dark:border-slate-700 animate-bounce"
                        style="animation-duration: 3s;">
                        <div class="flex items-center gap-3">
                            <div class="bg-emerald-600 h-3 w-3 rounded-full"></div>
                            <div>
                                <p class="text-xs text-slate-500 uppercase font-bold tracking-wider">Status Sistem</p>
                                <p class="text-sm font-bold text-slate-900 dark:text-white">Online &amp; Stabil</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <section class="py-10 bg-white dark:bg-slate-900 border-y border-slate-100 dark:border-slate-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div
                class="grid grid-cols-2 md:grid-cols-4 gap-8 text-center divide-x divide-slate-100 dark:divide-slate-800">
                <div class="space-y-2">
                    <h3 class="text-3xl lg:text-4xl font-extrabold text-primary">500+</h3>
                    <p class="text-slate-500 dark:text-slate-400 font-medium">Organisasi Aktif</p>
                </div>
                <div class="space-y-2">
                    <h3 class="text-3xl lg:text-4xl font-extrabold text-teal-accent">1.2M</h3>
                    <p class="text-slate-500 dark:text-slate-400 font-medium">Scan Bulanan</p>
                </div>
                <div class="space-y-2">
                    <h3 class="text-3xl lg:text-4xl font-extrabold text-primary">99.9%</h3>
                    <p class="text-slate-500 dark:text-slate-400 font-medium">Uptime Server</p>
                </div>
                <div class="space-y-2">
                    <h3 class="text-3xl lg:text-4xl font-extrabold text-slate-800 dark:text-white">24/7</h3>
                    <p class="text-slate-500 dark:text-slate-400 font-medium">Layanan Support</p>
                </div>
            </div>
        </div>
    </section>
    <section class="py-24 bg-background-light dark:bg-background-dark" id="features">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <h2 class="text-primary font-bold tracking-wide uppercase text-sm mb-2">Mengapa Generus?</h2>
                <h3 class="text-3xl md:text-4xl font-extrabold text-slate-900 dark:text-white mb-4">Fitur Unggulan untuk
                    Produktivitas</h3>
                <p class="text-lg text-slate-600 dark:text-slate-400">Kami merancang setiap fitur untuk memudahkan
                    manajemen SDM dan keanggotaan Anda tanpa hambatan.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div
                    class="group bg-white dark:bg-slate-800 rounded-2xl p-8 border border-slate-100 dark:border-slate-700 shadow-sm hover:shadow-xl transition-all duration-300 relative overflow-hidden">
                    <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                        <span class="material-icons-round text-9xl text-teal-accent transform rotate-12">cake</span>
                    </div>
                    <div class="relative z-10">
                        <span
                            class="inline-block px-3 py-1 rounded-full text-xs font-bold bg-teal-accent/20 text-teal-accent dark:bg-teal-accent/40 dark:text-white mb-6">
                            Usia Otomatis
                        </span>
                        <div
                            class="h-14 w-14 bg-teal-accent/10 rounded-xl flex items-center justify-center text-teal-accent mb-6 group-hover:scale-110 transition-transform">
                            <span class="material-icons-round text-3xl">cake</span>
                        </div>
                        <h4 class="text-xl font-bold text-slate-900 dark:text-white mb-3">Kalkulasi Umur Real-time</h4>
                        <p class="text-slate-600 dark:text-slate-400 leading-relaxed">
                            Sistem secara otomatis menghitung dan memperbarui usia anggota berdasarkan tanggal lahir,
                            memudahkan pengelompokan demografi.
                        </p>
                    </div>
                </div>
                <div
                    class="group bg-white dark:bg-slate-800 rounded-2xl p-8 border border-slate-100 dark:border-slate-700 shadow-sm hover:shadow-xl transition-all duration-300 relative overflow-hidden">
                    <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                        <span class="material-icons-round text-9xl text-status-late transform rotate-12">layers</span>
                    </div>
                    <div class="relative z-10">
                        <span
                            class="inline-block px-3 py-1 rounded-full text-xs font-bold bg-status-late/10 text-status-late dark:bg-status-late/30 dark:text-amber-200 mb-6">
                            Level Tak Terbatas
                        </span>
                        <div
                            class="h-14 w-14 bg-status-late/10 rounded-xl flex items-center justify-center text-status-late mb-6 group-hover:scale-110 transition-transform">
                            <span class="material-icons-round text-3xl">layers</span>
                        </div>
                        <h4 class="text-xl font-bold text-slate-900 dark:text-white mb-3">Struktur Organisasi Fleksibel
                        </h4>
                        <p class="text-slate-600 dark:text-slate-400 leading-relaxed">
                            Buat tingkatan keanggotaan atau departemen tanpa batas. Generus beradaptasi dengan
                            kompleksitas hierarki organisasi Anda.
                        </p>
                    </div>
                </div>
                <div
                    class="group bg-white dark:bg-slate-800 rounded-2xl p-8 border border-slate-100 dark:border-slate-700 shadow-sm hover:shadow-xl transition-all duration-300 relative overflow-hidden">
                    <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                        <span
                            class="material-icons-round text-9xl text-primary transform rotate-12">verified_user</span>
                    </div>
                    <div class="relative z-10">
                        <span
                            class="inline-block px-3 py-1 rounded-full text-xs font-bold bg-primary/10 text-primary dark:bg-primary/20 mb-6">
                            Keamanan Terjamin
                        </span>
                        <div
                            class="h-14 w-14 bg-primary/10 rounded-xl flex items-center justify-center text-primary mb-6 group-hover:scale-110 transition-transform">
                            <span class="material-icons-round text-3xl">verified_user</span>
                        </div>
                        <h4 class="text-xl font-bold text-slate-900 dark:text-white mb-3">Enkripsi Data Tingkat Lanjut
                        </h4>
                        <p class="text-slate-600 dark:text-slate-400 leading-relaxed">
                            Data kehadiran dan identitas anggota dilindungi dengan enkripsi standar industri dan backup
                            berkala otomatis.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <section class="py-24 bg-white dark:bg-slate-900 overflow-hidden">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col lg:flex-row items-center gap-16">
                <div class="w-full lg:w-1/2 order-2 lg:order-1">
                    <div
                        class="relative rounded-2xl bg-background-light dark:bg-background-dark p-6 border border-slate-200 dark:border-slate-800 shadow-2xl">
                        <div class="flex justify-between items-end mb-4">
                            <div>
                                <h5 class="text-sm uppercase text-slate-500 font-bold">Trend Kehadiran</h5>
                                <h3 class="text-2xl font-bold text-slate-900 dark:text-white">Minggu Ini</h3>
                            </div>
                            <div class="flex gap-2">
                                <div class="px-3 py-1 rounded bg-white dark:bg-slate-700 shadow text-xs font-medium">
                                    Harian</div>
                                <div
                                    class="px-3 py-1 rounded bg-transparent hover:bg-white dark:hover:bg-slate-700 text-slate-500 text-xs font-medium cursor-pointer">
                                    Bulanan</div>
                            </div>
                        </div>
                        <div class="h-64 w-full flex items-end justify-between gap-2 px-2">
                            <div class="w-full bg-slate-200 dark:bg-slate-800 rounded-t-md relative group">
                                <div
                                    class="absolute bottom-0 w-full bg-primary/30 h-[40%] rounded-t-md group-hover:bg-primary/50 transition-colors">
                                </div>
                                <div
                                    class="absolute -top-8 left-1/2 -translate-x-1/2 opacity-0 group-hover:opacity-100 text-xs bg-slate-800 text-white px-2 py-1 rounded transition-opacity">
                                    Mon</div>
                            </div>
                            <div class="w-full bg-slate-200 dark:bg-slate-800 rounded-t-md relative group">
                                <div
                                    class="absolute bottom-0 w-full bg-primary/40 h-[65%] rounded-t-md group-hover:bg-primary/60 transition-colors">
                                </div>
                                <div
                                    class="absolute -top-8 left-1/2 -translate-x-1/2 opacity-0 group-hover:opacity-100 text-xs bg-slate-800 text-white px-2 py-1 rounded transition-opacity">
                                    Tue</div>
                            </div>
                            <div class="w-full bg-slate-200 dark:bg-slate-800 rounded-t-md relative group">
                                <div
                                    class="absolute bottom-0 w-full bg-primary/60 h-[55%] rounded-t-md group-hover:bg-primary/80 transition-colors">
                                </div>
                                <div
                                    class="absolute -top-8 left-1/2 -translate-x-1/2 opacity-0 group-hover:opacity-100 text-xs bg-slate-800 text-white px-2 py-1 rounded transition-opacity">
                                    Wed</div>
                            </div>
                            <div class="w-full bg-slate-200 dark:bg-slate-800 rounded-t-md relative group">
                                <div
                                    class="absolute bottom-0 w-full bg-primary h-[85%] rounded-t-md shadow-[0_0_15px_rgba(6,95,70,0.5)]">
                                </div>
                                <div
                                    class="absolute -top-8 left-1/2 -translate-x-1/2 opacity-100 text-xs bg-primary text-white px-2 py-1 rounded font-bold">
                                    Thu</div>
                            </div>
                            <div class="w-full bg-slate-200 dark:bg-slate-800 rounded-t-md relative group">
                                <div
                                    class="absolute bottom-0 w-full bg-primary/50 h-[60%] rounded-t-md group-hover:bg-primary/70 transition-colors">
                                </div>
                                <div
                                    class="absolute -top-8 left-1/2 -translate-x-1/2 opacity-0 group-hover:opacity-100 text-xs bg-slate-800 text-white px-2 py-1 rounded transition-opacity">
                                    Fri</div>
                            </div>
                            <div class="w-full bg-slate-200 dark:bg-slate-800 rounded-t-md relative group">
                                <div
                                    class="absolute bottom-0 w-full bg-teal-accent/60 h-[30%] rounded-t-md group-hover:bg-teal-accent/80 transition-colors">
                                </div>
                                <div
                                    class="absolute -top-8 left-1/2 -translate-x-1/2 opacity-0 group-hover:opacity-100 text-xs bg-slate-800 text-white px-2 py-1 rounded transition-opacity">
                                    Sat</div>
                            </div>
                            <div class="w-full bg-slate-200 dark:bg-slate-800 rounded-t-md relative group">
                                <div
                                    class="absolute bottom-0 w-full bg-teal-accent/40 h-[20%] rounded-t-md group-hover:bg-teal-accent/60 transition-colors">
                                </div>
                                <div
                                    class="absolute -top-8 left-1/2 -translate-x-1/2 opacity-0 group-hover:opacity-100 text-xs bg-slate-800 text-white px-2 py-1 rounded transition-opacity">
                                    Sun</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="w-full lg:w-1/2 order-1 lg:order-2 space-y-6">
                    <h2 class="text-3xl lg:text-4xl font-extrabold text-slate-900 dark:text-white leading-tight">
                        Wawasan Data untuk <br /><span class="text-primary">Keputusan Lebih Baik</span>
                    </h2>
                    <p class="text-lg text-slate-600 dark:text-slate-400">
                        Jangan biarkan data absensi hanya menjadi tumpukan angka. Dashboard Generus menyajikan
                        visualisasi yang intuitif, membantu Anda memahami pola kehadiran, keterlambatan, dan
                        produktivitas tim secara instan.
                    </p>
                    <ul class="space-y-4 pt-4">
                        <li class="flex items-start">
                            <span class="material-icons-round text-primary mr-3 mt-1">analytics</span>
                            <span class="text-slate-700 dark:text-slate-300 font-medium">Laporan analitik bulanan yang
                                dapat diunduh (PDF/Excel)</span>
                        </li>
                        <li class="flex items-start">
                            <span class="material-icons-round text-primary mr-3 mt-1">notifications_active</span>
                            <span class="text-slate-700 dark:text-slate-300 font-medium">Notifikasi anomali kehadiran
                                secara real-time</span>
                        </li>
                        <li class="flex items-start">
                            <span class="material-icons-round text-primary mr-3 mt-1">people</span>
                            <span class="text-slate-700 dark:text-slate-300 font-medium">Pemetaan demografi anggota
                                otomatis</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>
    <section class="py-12 bg-slate-50 dark:bg-slate-900/50 border-t border-slate-200 dark:border-slate-800">
        <div class="max-w-7xl mx-auto px-4 text-center">
            <p class="text-sm font-semibold text-slate-400 uppercase tracking-widest mb-8">Built with Modern Technology
                Stack</p>
            <div
                class="flex flex-wrap justify-center items-center gap-12 opacity-60 grayscale hover:grayscale-0 transition-all duration-500">
                <div class="flex items-center gap-2 group">
                    <div
                        class="w-8 h-8 bg-red-500 rounded flex items-center justify-center text-white font-bold text-xs">
                        L</div>
                    <span
                        class="font-bold text-xl text-slate-600 dark:text-slate-300 group-hover:text-red-500 transition-colors">Laravel</span>
                </div>
                <div class="flex items-center gap-2 group">
                    <div
                        class="w-8 h-8 bg-amber-500 rounded flex items-center justify-center text-white font-bold text-xs">
                        F</div>
                    <span
                        class="font-bold text-xl text-slate-600 dark:text-slate-300 group-hover:text-amber-500 transition-colors">Filament</span>
                </div>
                <div class="flex items-center gap-2 group">
                    <div
                        class="w-8 h-8 bg-cyan-500 rounded flex items-center justify-center text-white font-bold text-xs">
                        T</div>
                    <span
                        class="font-bold text-xl text-slate-600 dark:text-slate-300 group-hover:text-cyan-500 transition-colors">Tailwind
                        CSS</span>
                </div>
            </div>
        </div>
    </section>
    <section class="py-24 bg-background-light dark:bg-background-dark">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <div
                class="relative bg-primary rounded-3xl p-10 md:p-16 text-center overflow-hidden shadow-2xl shadow-primary/40">
                <div class="absolute top-0 left-0 w-full h-full overflow-hidden opacity-20 pointer-events-none">
                    <div class="absolute -top-24 -left-24 w-96 h-96 bg-white rounded-full blur-3xl opacity-30"></div>
                    <div class="absolute top-1/2 -right-24 w-72 h-72 bg-teal-accent rounded-full blur-3xl opacity-40">
                    </div>
                </div>
                <div class="relative z-10">
                    <h2 class="text-3xl md:text-5xl font-extrabold text-white mb-6">Siap Meningkatkan Efisiensi?</h2>
                    <p class="text-emerald-100 text-lg md:text-xl max-w-2xl mx-auto mb-10">
                        Bergabunglah dengan ratusan organisasi yang telah beralih ke Generus. Mulai kelola absensi
                        organisasi Anda sekarang.
                    </p>
                    <div class="flex flex-col sm:flex-row justify-center gap-4">
                        <a href="{{ route('filament.admin.auth.login') }}"
                            class="px-8 py-4 bg-white text-primary font-bold rounded-xl shadow-lg hover:bg-slate-50 transition-colors transform hover:-translate-y-1 inline-block">
                            Jadwalkan Demo
                        </a>

                    </div>
                </div>
            </div>
        </div>
    </section>
    <footer class="bg-white dark:bg-slate-900 border-t border-slate-200 dark:border-slate-800 pt-16 pb-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-12 mb-12">
                <div class="col-span-1 md:col-span-1">
                    <div class="flex items-center gap-2 mb-4">
                        <span class="material-icons-round text-primary text-3xl">schedule</span>
                        <span class="font-bold text-2xl tracking-tight text-slate-900 dark:text-white">Generus</span>
                    </div>
                    <p class="text-slate-500 text-sm leading-relaxed mb-4">
                        Platform manajemen absensi modern untuk efisiensi maksimal organisasi Anda.
                    </p>
                    <div class="flex gap-4">
                        <a class="text-slate-400 hover:text-primary transition-colors"
                            href="https://www.instagram.com/gensinga.official/" target="_blank"
                            rel="noopener noreferrer" aria-label="Instagram">
                            <svg class="w-6 h-6 fill-current" viewBox="0 0 24 24">
                                <path
                                    d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z" />
                            </svg>
                        </a>
                        <a class="text-slate-400 hover:text-primary transition-colors"
                            href="https://www.tiktok.com/@gensinga354" target="_blank" rel="noopener noreferrer"
                            aria-label="TikTok">
                            <svg class="w-6 h-6 fill-current" viewBox="0 0 24 24">
                                <path
                                    d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 2.22-1.15 4.38-2.9 5.76-1.74 1.39-4.04 1.94-6.2 1.55-2.19-.39-4.14-1.7-5.26-3.57-1.13-1.89-1.29-4.31-.41-6.35.89-2.07 2.72-3.63 4.9-4.3 2.19-.68 4.67-.53 6.69.57.01 1.43-.01 2.87.01 4.3-.98-.63-2.14-.97-3.32-.97-1.65-.01-3.21.91-4.04 2.33-.82 1.42-.82 3.25 0 4.67.83 1.42 2.39 2.34 4.04 2.33 1.87-.01 3.51-1.26 4.02-3.04.14-.49.2-.99.21-1.5.03-4.57.02-9.14.03-13.7Z" />
                            </svg>
                        </a>
                        <a class="text-slate-400 hover:text-primary transition-colors"
                            href="mailto:mudamudisinga@gmail.com" aria-label="Email">
                            <span class="material-icons-round">alternate_email</span>
                        </a>
                    </div>
                </div>
                <div>
                    <h4 class="font-bold text-slate-900 dark:text-white mb-4">Produk</h4>
                    <ul class="space-y-2 text-sm text-slate-500 dark:text-slate-400">
                        <li><a class="hover:text-primary transition-colors" href="#features">Fitur</a></li>
                        <li><a class="hover:text-primary transition-colors cursor-pointer"
                                onclick="openModal('modal-integrasi')">Integrasi</a></li>
                        <li><a class="hover:text-primary transition-colors cursor-pointer"
                                onclick="openModal('modal-updates')">Updates</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-bold text-slate-900 dark:text-white mb-4">Perusahaan</h4>
                    <ul class="space-y-2 text-sm text-slate-500 dark:text-slate-400">
                        <li><a class="hover:text-primary transition-colors cursor-pointer"
                                onclick="openModal('modal-tentang')">Tentang Kami</a></li>
                        <li><a class="hover:text-primary transition-colors cursor-pointer"
                                onclick="openModal('modal-karir')">Karir</a></li>
                        <li><a class="hover:text-primary transition-colors cursor-pointer"
                                onclick="openModal('modal-blog')">Blog</a></li>
                        <li><a class="hover:text-primary transition-colors"
                                href="mailto:mudamudisinga@gmail.com">Kontak</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-bold text-slate-900 dark:text-white mb-4">Legal</h4>
                    <ul class="space-y-2 text-sm text-slate-500 dark:text-slate-400">
                        <li><a class="hover:text-primary transition-colors cursor-pointer"
                                onclick="openModal('modal-privasi')">Kebijakan Privasi</a></li>
                        <li><a class="hover:text-primary transition-colors cursor-pointer"
                                onclick="openModal('modal-syarat')">Syarat &amp; Ketentuan</a></li>
                        <li><a class="hover:text-primary transition-colors cursor-pointer"
                                onclick="openModal('modal-keamanan')">Keamanan</a></li>
                    </ul>
                </div>
            </div>
            <div
                class="pt-8 border-t border-slate-100 dark:border-slate-800 flex flex-col md:flex-row justify-between items-center gap-4">
                <p class="text-sm text-slate-400">© {{ date('Y') }} Generus.site — All rights reserved.</p>
                <div class="flex gap-6 text-sm text-slate-400">
                    <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        System Operational</span>
                </div>
            </div>
        </div>
    </footer>

    <!-- Modals -->
    <div id="modal-privasi"
        class="fixed inset-0 z-[100] hidden flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
        <div class="bg-white dark:bg-slate-800 rounded-2xl w-full max-w-2xl max-h-[80vh] overflow-y-auto shadow-2xl">
            <div class="p-6 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center">
                <h3 class="text-xl font-bold text-slate-900 dark:text-white">Kebijakan Privasi</h3>
                <button onclick="closeModal('modal-privasi')"
                    class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                    <span class="material-icons-round">close</span>
                </button>
            </div>
            <div class="p-6 text-slate-600 dark:text-slate-300 space-y-4 text-sm leading-relaxed">
                <h4 class="font-bold text-slate-800 dark:text-slate-200 text-base">1. Pengumpulan Informasi</h4>
                <p>Generus mengumpulkan informasi yang Anda berikan secara langsung saat mendaftar atau menggunakan
                    layanan kami, termasuk namun tidak terbatas pada nama lengkap, alamat email, nomor telepon, dan data
                    kehadiran/absensi.</p>

                <h4 class="font-bold text-slate-800 dark:text-slate-200 text-base mt-4">2. Penggunaan Informasi</h4>
                <p>Informasi yang kami kumpulkan digunakan untuk mengelola keanggotaan, mencatat kehadiran, meningkatkan
                    kualitas layanan operasional, serta memastikan keamanan dan kenyamanan pengguna di platform kami.
                </p>

                <h4 class="font-bold text-slate-800 dark:text-slate-200 text-base mt-4">3. Keamanan Data</h4>
                <p>Kami berkomitmen untuk melindungi data pribadi Anda. Kami mengimplementasikan berbagai langkah
                    keamanan teknis dan administratif untuk mencegah akses, penggunaan, atau pengungkapan yang tidak
                    sah.</p>

                <h4 class="font-bold text-slate-800 dark:text-slate-200 text-base mt-4">4. Berbagi Data</h4>
                <p>Kami tidak menjual atau menyewakan informasi pribadi Anda kepada pihak ketiga. Kami hanya dapat
                    membagikan informasi jika diwajibkan oleh hukum atau untuk keperluan perlindungan hak hukum
                    perusahaan kami.</p>

                <h4 class="font-bold text-slate-800 dark:text-slate-200 text-base mt-4">5. Perubahan Kebijakan</h4>
                <p>Kebijakan Privasi ini dapat diperbarui dari waktu ke waktu. Setiap perubahan akan diinformasikan
                    melalui platform ini. Dengan terus menggunakan layanan kami, Anda dianggap menyetujui pembaruan
                    tersebut.</p>
            </div>
        </div>
    </div>

    <div id="modal-syarat"
        class="fixed inset-0 z-[100] hidden flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
        <div class="bg-white dark:bg-slate-800 rounded-2xl w-full max-w-2xl max-h-[80vh] overflow-y-auto shadow-2xl">
            <div class="p-6 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center">
                <h3 class="text-xl font-bold text-slate-900 dark:text-white">Syarat &amp; Ketentuan</h3>
                <button onclick="closeModal('modal-syarat')"
                    class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                    <span class="material-icons-round">close</span>
                </button>
            </div>
            <div class="p-6 text-slate-600 dark:text-slate-300 space-y-4 text-sm leading-relaxed">
                <h4 class="font-bold text-slate-800 dark:text-slate-200 text-base">1. Ketentuan Umum</h4>
                <p>Dengan mengakses dan menggunakan platform Generus, Anda menyatakan telah membaca, memahami, dan
                    menyetujui untuk terikat dengan semua syarat dan ketentuan ini. Jika Anda tidak menyetujui syarat
                    dan ketentuan ini, Anda tidak diperkenankan untuk menggunakan layanan kami.</p>

                <h4 class="font-bold text-slate-800 dark:text-slate-200 text-base mt-4">2. Penggunaan Layanan</h4>
                <p>Layanan kami ditujukan untuk memfasilitasi manajemen absensi dan keanggotaan. Anda setuju untuk
                    menggunakan platform ini hanya untuk tujuan yang sah dan sesuai dengan hukum yang berlaku di wilayah
                    yurisdiksi Republik Indonesia.</p>

                <h4 class="font-bold text-slate-800 dark:text-slate-200 text-base mt-4">3. Kewajiban Pengguna</h4>
                <p>Anda bertanggung jawab penuh untuk menjaga kerahasiaan akun dan kata sandi Anda. Semua aktivitas yang
                    terjadi di bawah akun Anda adalah tanggung jawab Anda sepenuhnya. Anda wajib memberikan informasi
                    yang akurat, terkini, dan lengkap.</p>

                <h4 class="font-bold text-slate-800 dark:text-slate-200 text-base mt-4">4. Batasan Tanggung Jawab</h4>
                <p>Generus disediakan "sebagaimana adanya". Kami tidak menjamin bahwa layanan akan selalu bebas gangguan
                    atau bebas dari kesalahan. Kami tidak bertanggung jawab atas kerugian tidak langsung yang timbul
                    dari penggunaan platform.</p>

                <h4 class="font-bold text-slate-800 dark:text-slate-200 text-base mt-4">5. Pemutusan Akses</h4>
                <p>Kami berhak untuk menangguhkan atau menghentikan akses Anda ke platform kapan saja, tanpa
                    pemberitahuan sebelumnya, jika kami menemukan adanya pelanggaran terhadap Syarat &amp; Ketentuan ini
                    atau tindakan merugikan lainnya.</p>
            </div>
        </div>
    </div>

    <div id="modal-keamanan"
        class="fixed inset-0 z-[100] hidden flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
        <div class="bg-white dark:bg-slate-800 rounded-2xl w-full max-w-2xl max-h-[80vh] overflow-y-auto shadow-2xl">
            <div class="p-6 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center">
                <h3 class="text-xl font-bold text-slate-900 dark:text-white">Keamanan Data</h3>
                <button onclick="closeModal('modal-keamanan')"
                    class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                    <span class="material-icons-round">close</span>
                </button>
            </div>
            <div class="p-6 text-slate-600 dark:text-slate-300 space-y-4 text-sm leading-relaxed">
                <h4 class="font-bold text-slate-800 dark:text-slate-200 text-base">1. Enkripsi Data</h4>
                <p>Generus menerapkan standar keamanan tinggi dalam melindungi data Anda. Semua transmisi data, termasuk
                    kredensial login dan data absensi, dienkripsi menggunakan protokol keamanan standar industri untuk
                    mencegah akses oleh pihak yang tidak berwenang.</p>

                <h4 class="font-bold text-slate-800 dark:text-slate-200 text-base mt-4">2. Infrastruktur Penyimpanan
                </h4>
                <p>Data Anda disimpan pada infrastruktur server yang aman dengan kontrol akses jaringan yang ketat,
                    serta pemantauan aktif terhadap potensi ancaman siber.</p>

                <h4 class="font-bold text-slate-800 dark:text-slate-200 text-base mt-4">3. Pencadangan (Backup) Berkala
                </h4>
                <p>Untuk menghindari insiden kehilangan data, sistem kami melakukan mekanisme pencadangan (backup)
                    secara otomatis dan berkala. Data cadangan ini juga dijaga kerahasiaannya dengan sistem yang andal.
                </p>

                <h4 class="font-bold text-slate-800 dark:text-slate-200 text-base mt-4">4. Kontrol Akses (Role-Based
                    Access)</h4>
                <p>Hanya personel internal organisasi Anda (Administrator/Operator) yang memiliki otorisasi penuh yang
                    dapat mengakses laporan absensi dan informasi profil, diatur dalam sistem perizinan berbasis peran
                    yang fleksibel.</p>
            </div>
        </div>
    </div>

    <div id="modal-tentang"
        class="fixed inset-0 z-[100] hidden flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
        <div class="bg-white dark:bg-slate-800 rounded-2xl w-full max-w-2xl max-h-[80vh] overflow-y-auto shadow-2xl">
            <div class="p-6 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center">
                <h3 class="text-xl font-bold text-slate-900 dark:text-white">Tentang Kami</h3>
                <button onclick="closeModal('modal-tentang')"
                    class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                    <span class="material-icons-round">close</span>
                </button>
            </div>
            <div class="p-6 text-slate-600 dark:text-slate-300 space-y-4 text-sm leading-relaxed">
                <h4 class="font-bold text-slate-800 dark:text-slate-200 text-base">Latar Belakang</h4>
                <p>Generus hadir dari kesadaran akan tantangan yang dihadapi banyak organisasi dalam mengelola data
                    keanggotaan dan absensi yang seringkali manual, tersebar, dan rawan kesalahan. Kami membangun solusi
                    terpusat yang praktis dan efisien untuk menyelesaikan masalah tersebut.</p>

                <h4 class="font-bold text-slate-800 dark:text-slate-200 text-base mt-4">Visi Kami</h4>
                <p>Menjadi platform manajemen keanggotaan dan absensi terdepan di Indonesia yang membantu organisasi
                    dalam memaksimalkan potensi, produktivitas, dan kedisiplinan anggotanya.</p>

                <h4 class="font-bold text-slate-800 dark:text-slate-200 text-base mt-4">Misi Kami</h4>
                <ul class="list-disc list-inside space-y-1">
                    <li>Menyediakan teknologi pencatatan kehadiran yang akurat, real-time, dan mudah digunakan
                        (user-friendly).</li>
                    <li>Menghadirkan fitur pelaporan yang komprehensif untuk mendukung pengambilan keputusan.</li>
                    <li>Menjamin keamanan tingkat tinggi terhadap seluruh data pengguna dan entitas yang terhubung.</li>
                </ul>

                <h4 class="font-bold text-slate-800 dark:text-slate-200 text-base mt-4">Nilai Inti (Core Values)</h4>
                <div class="flex gap-4 mt-2">
                    <span class="px-3 py-1 bg-primary/10 text-primary font-semibold rounded-full text-xs">Inovasi</span>
                    <span
                        class="px-3 py-1 bg-primary/10 text-primary font-semibold rounded-full text-xs">Keamanan</span>
                    <span
                        class="px-3 py-1 bg-primary/10 text-primary font-semibold rounded-full text-xs">Efisiensi</span>
                </div>
            </div>
        </div>
    </div>

    <div id="modal-karir"
        class="fixed inset-0 z-[100] hidden flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
        <div class="bg-white dark:bg-slate-800 rounded-2xl w-full max-w-lg shadow-2xl">
            <div class="p-6 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center">
                <h3 class="text-xl font-bold text-slate-900 dark:text-white">Karir di Generus</h3>
                <button onclick="closeModal('modal-karir')"
                    class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                    <span class="material-icons-round">close</span>
                </button>
            </div>
            <div class="p-6 text-slate-600 dark:text-slate-300 space-y-4 text-center">
                <div class="mb-4 text-primary">
                    <span class="material-icons-round text-6xl">work_outline</span>
                </div>
                <p>Saat ini belum ada posisi yang terbuka.</p>
                <p class="text-sm">Silakan pantau terus halaman ini untuk informasi lowongan kerja terbaru di masa
                    mendatang.</p>
            </div>
        </div>
    </div>

    <div id="modal-integrasi"
        class="fixed inset-0 z-[100] hidden flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
        <div class="bg-white dark:bg-slate-800 rounded-2xl w-full max-w-lg shadow-2xl">
            <div class="p-6 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center">
                <h3 class="text-xl font-bold text-slate-900 dark:text-white">Integrasi Platform</h3>
                <button onclick="closeModal('modal-integrasi')"
                    class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                    <span class="material-icons-round">close</span>
                </button>
            </div>
            <div class="p-6 text-slate-600 dark:text-slate-300 space-y-4 text-center">
                <div class="mb-4 text-teal-accent">
                    <span class="material-icons-round text-6xl">hub</span>
                </div>
                <p>Fitur Integrasi dan Ekosistem sedang dalam tahap pengembangan.</p>
                <p class="text-sm">Generus akan segera meluncurkan <strong>Aplikasi Android</strong> untuk memudahkan
                    mobilitas dan akses anggota. Pantau terus informasi selanjutnya!</p>
            </div>
        </div>
    </div>

    <div id="modal-updates"
        class="fixed inset-0 z-[100] hidden flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
        <div class="bg-white dark:bg-slate-800 rounded-2xl w-full max-w-lg shadow-2xl">
            <div class="p-6 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center">
                <h3 class="text-xl font-bold text-slate-900 dark:text-white">Log Pembaruan (Updates)</h3>
                <button onclick="closeModal('modal-updates')"
                    class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                    <span class="material-icons-round">close</span>
                </button>
            </div>
            <div class="p-6 text-slate-600 dark:text-slate-300 space-y-6 max-h-[70vh] overflow-y-auto">
                <div class="border-l-4 border-primary pl-4">
                    <div class="flex items-center gap-2">
                        <h4 class="font-bold text-slate-800 dark:text-slate-200">Versi 2.0 (Saat Ini)</h4>
                        <span
                            class="px-2 py-0.5 rounded text-[10px] font-bold bg-primary/20 text-primary uppercase">Major
                            Release</span>
                    </div>
                    <ul class="list-disc list-inside text-sm mt-3 space-y-2 text-slate-600 dark:text-slate-400">
                        <li><strong>Template Kartu Anggota:</strong> Admin kini dapat mendesain dan mencetak kartu
                            anggota virtual maupun fisik secara langsung dengan QR Code unik terintegrasi.</li>
                        <li><strong>Perbaikan (Fixing) UI/UX:</strong> Navigasi utama dirancang ulang, tabel data
                            menjadi responsif di semua perangkat, serta penyesuaian kontras warna untuk meningkatkan
                            aksesibilitas.</li>
                        <li><strong>Kalkulasi Umur Real-time:</strong> Sistem secara otomatis menghitung usia
                            berdasarkan tanggal lahir tanpa perlu pembaruan manual.</li>
                    </ul>
                </div>

                <div class="border-l-4 border-slate-300 dark:border-slate-600 pl-4">
                    <div class="flex items-center gap-2">
                        <h4 class="font-bold text-slate-800 dark:text-slate-200">Versi 1.95</h4>
                        <span
                            class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300 uppercase">Security
                            Update</span>
                    </div>
                    <ul class="list-disc list-inside text-sm mt-3 space-y-2 text-slate-600 dark:text-slate-400">
                        <li><strong>Keamanan Ekspor Data:</strong> Refactoring hak akses pada fitur ekspor (Excel &amp;
                            PDF) yang kini terintegrasi penuh dengan sistem proteksi otorisasi hierarkis (Native Policy).</li>
                    </ul>
                </div>

                <div class="border-l-4 border-slate-300 dark:border-slate-600 pl-4">
                    <div class="flex items-center gap-2">
                        <h4 class="font-bold text-slate-800 dark:text-slate-200">Versi 1.9</h4>
                        <span
                            class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300 uppercase">Feature
                            Update</span>
                    </div>
                    <ul class="list-disc list-inside text-sm mt-3 space-y-2 text-slate-600 dark:text-slate-400">
                        <li><strong>Presensi Mandiri (Self-Presence):</strong> Anggota kini dapat menginput kehadiran
                            secara mandiri tanpa harus melalui admin.</li>
                        <li><strong>Perizinan Mandiri (Self-Permission):</strong> Fitur pengajuan izin ketidakhadiran
                            langsung dari antarmuka anggota.</li>
                    </ul>
                </div>

                <div class="border-l-4 border-slate-300 dark:border-slate-600 pl-4">
                    <div class="flex items-center gap-2">
                        <h4 class="font-bold text-slate-800 dark:text-slate-200">Versi 1.8</h4>
                        <span
                            class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300 uppercase">UI
                            / UX</span>
                    </div>
                    <ul class="list-disc list-inside text-sm mt-3 space-y-2 text-slate-600 dark:text-slate-400">
                        <li><strong>Dark Mode (Tema Gelap):</strong> Dukungan penuh tema gelap pada panel kontrol untuk
                            kenyamanan mata pengguna (Admin/Anggota).</li>
                    </ul>
                </div>

                <div class="border-l-4 border-slate-300 dark:border-slate-600 pl-4">
                    <div class="flex items-center gap-2">
                        <h4 class="font-bold text-slate-800 dark:text-slate-200">Versi 1.7</h4>
                        <span
                            class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300 uppercase">Analytics</span>
                    </div>
                    <ul class="list-disc list-inside text-sm mt-3 space-y-2 text-slate-600 dark:text-slate-400">
                        <li><strong>Dashboard Statistik:</strong> Penambahan widget statistik dan analitik pertemuan
                            pada halaman muka dashboard (Meeting Stats).</li>
                    </ul>
                </div>

                <div class="border-l-4 border-slate-300 dark:border-slate-600 pl-4">
                    <div class="flex items-center gap-2">
                        <h4 class="font-bold text-slate-800 dark:text-slate-200">Versi 1.5</h4>
                        <span
                            class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300 uppercase">Data
                            Tools</span>
                    </div>
                    <ul class="list-disc list-inside text-sm mt-3 space-y-2 text-slate-600 dark:text-slate-400">
                        <li><strong>Ekspor Laporan (Excel &amp; PDF):</strong> Fitur untuk mengunduh rekapitulasi data
                            kehadiran anggota ke dalam format Excel (.xlsx) dan PDF.</li>
                    </ul>
                </div>

                <div class="border-l-4 border-slate-300 dark:border-slate-600 pl-4">
                    <div class="flex items-center gap-2">
                        <h4 class="font-bold text-slate-800 dark:text-slate-200">Versi 1.4</h4>
                        <span
                            class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300 uppercase">Core
                            Feature</span>
                    </div>
                    <ul class="list-disc list-inside text-sm mt-3 space-y-2 text-slate-600 dark:text-slate-400">
                        <li><strong>Live QR Scanner:</strong> Implementasi pemindai QR Code langsung dari browser untuk
                            melakukan presensi secara cepat saat pertemuan offline.</li>
                    </ul>
                </div>

                <div class="border-l-4 border-slate-300 dark:border-slate-600 pl-4">
                    <div class="flex items-center gap-2">
                        <h4 class="font-bold text-slate-800 dark:text-slate-200">Versi 1.3</h4>
                        <span
                            class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300 uppercase">Feature
                            Update</span>
                    </div>
                    <ul class="list-disc list-inside text-sm mt-3 space-y-2 text-slate-600 dark:text-slate-400">
                        <li><strong>Profil Organisasi Mendalam:</strong> Penambahan klasifikasi anggota berdasarkan
                            Tingkatan (Levels), Kategori Pengurus (Position Categories), dan Kelompok Umur (Age Groups).
                        </li>
                    </ul>
                </div>

                <div class="border-l-4 border-slate-300 dark:border-slate-600 pl-4">
                    <div class="flex items-center gap-2">
                        <h4 class="font-bold text-slate-800 dark:text-slate-200">Versi 1.2</h4>
                        <span
                            class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300 uppercase">System
                            Update</span>
                    </div>
                    <ul class="list-disc list-inside text-sm mt-3 space-y-2 text-slate-600 dark:text-slate-400">
                        <li><strong>Manajemen Otorisasi Terpadu:</strong> Penerapan sistem Role dan Kebijakan Akses (Policy)
                            hierarkis yang ketat untuk berbagai tingkatan akses grup dan organisasi.</li>
                    </ul>
                </div>

                <div class="border-l-4 border-slate-300 dark:border-slate-600 pl-4">
                    <div class="flex items-center gap-2">
                        <h4 class="font-bold text-slate-800 dark:text-slate-200">Versi 1.0 (Rilis Awal)</h4>
                        <span
                            class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300 uppercase">Initial
                            Release</span>
                    </div>
                    <ul class="list-disc list-inside text-sm mt-3 space-y-2 text-slate-600 dark:text-slate-400">
                        <li><strong>Manajemen Anggota:</strong> Mengelola data dasar anggota organisasi.</li>
                        <li><strong>Manajemen Grup:</strong> Pembagian anggota ke dalam berbagai grup/kelompok.</li>
                        <li><strong>Manajemen Pertemuan:</strong> Pembuatan jadwal dan pencatatan kehadiran manual.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div id="modal-blog"
        class="fixed inset-0 z-[100] hidden flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
        <div class="bg-white dark:bg-slate-800 rounded-2xl w-full max-w-lg shadow-2xl">
            <div class="p-6 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center">
                <h3 class="text-xl font-bold text-slate-900 dark:text-white">Blog &amp; Artikel</h3>
                <button onclick="closeModal('modal-blog')"
                    class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                    <span class="material-icons-round">close</span>
                </button>
            </div>
            <div class="p-6 text-slate-600 dark:text-slate-300 space-y-4 text-center">
                <div class="mb-4 text-primary">
                    <span class="material-icons-round text-6xl">article</span>
                </div>
                <p>Halaman Blog masih dalam tahap pengembangan.</p>
                <p class="text-sm">Nantinya, Anda bisa membaca berbagai artikel menarik seputar manajemen komunitas,
                    tips produktivitas, dan pembaruan seputar Generus di sini.</p>
            </div>
        </div>
    </div> <!-- end modal-blog -->

    <div id="modal-demo"
        class="fixed inset-0 z-[100] hidden items-center justify-center bg-slate-900/80 backdrop-blur-sm p-4">
        <div
            class="bg-black rounded-2xl w-full max-w-4xl shadow-2xl relative overflow-hidden aspect-video border border-slate-700">
            <button onclick="closeModal('modal-demo')"
                class="absolute top-4 right-4 z-10 text-white/70 hover:text-white bg-black/50 rounded-full p-1 transition-colors">
                <span class="material-icons-round">close</span>
            </button>
            <div class="w-full h-full bg-slate-900">
                <iframe class="w-full h-full" src="https://www.youtube.com/embed/_DNlPlyXWyk"
                    title="YouTube video player" frameborder="0"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                    allowfullscreen></iframe>
            </div>
        </div>
    </div>
    <script>
        window.openModal = function (id) {
            const modal = document.getElementById(id);
            if (modal) {
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            }
        }
        window.closeModal = function (id) {
            const modal = document.getElementById(id);
            if (modal) {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                const iframe = modal.querySelector('iframe');
                if (iframe) {
                    const src = iframe.src;
                    iframe.src = src; // trick to stop video playback
                }
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            const mobileMenuButton = document.getElementById('mobile-menu-button');
            const mobileMenu = document.getElementById('mobile-menu');
            const menuIcon = document.getElementById('menu-icon');

            if (mobileMenuButton && mobileMenu && menuIcon) {
                mobileMenuButton.addEventListener('click', function () {
                    const isHidden = mobileMenu.classList.contains('hidden');

                    if (isHidden) {
                        mobileMenu.classList.remove('hidden');
                        menuIcon.textContent = 'close';
                    } else {
                        mobileMenu.classList.add('hidden');
                        menuIcon.textContent = 'menu';
                    }
                });

                // Close menu when clicking on links
                const links = mobileMenu.querySelectorAll('a');
                links.forEach(link => {
                    link.addEventListener('click', function () {
                        mobileMenu.classList.add('hidden');
                        menuIcon.textContent = 'menu';
                    });
                });
            }
        });
    </script>
</body>

</html>