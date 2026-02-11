<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>inTime - Sistem Absensi &amp; Keanggotaan Cerdas</title>
    <link href="https://fonts.googleapis.com" rel="preconnect"/>
    <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200;300;400;500;600;700;800&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#065f46","primary-dark": "#064e3b",
                        "background-light": "#f6f7f8",
                        "background-dark": "#0d1511",
                        "teal-accent": "#8da399","status-late": "#b45309",},
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
<body class="bg-background-light dark:bg-background-dark text-slate-800 dark:text-slate-100 font-display antialiased selection:bg-primary selection:text-white">
<nav class="fixed w-full z-50 bg-white/90 dark:bg-background-dark/90 backdrop-blur-md border-b border-slate-200 dark:border-slate-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-20">
            <div class="flex-shrink-0 flex items-center gap-2">
                <span class="material-icons-round text-primary text-3xl">schedule</span>
                <span class="font-bold text-2xl tracking-tight text-slate-900 dark:text-white">inTime</span>
            </div>
            <div class="hidden md:flex space-x-8 items-center">
                <a class="text-slate-600 dark:text-slate-300 hover:text-primary dark:hover:text-primary transition-colors font-medium" href="#">Beranda</a>
                <a class="text-slate-600 dark:text-slate-300 hover:text-primary dark:hover:text-primary transition-colors font-medium" href="#features">Fitur</a>
                <a class="text-slate-600 dark:text-slate-300 hover:text-primary dark:hover:text-primary transition-colors font-medium" href="#pricing">Harga</a>
                
                @auth
                    <a class="px-5 py-2.5 rounded-full bg-primary text-white font-semibold hover:bg-primary-dark transition-all shadow-lg shadow-primary/30" href="{{ route('filament.admin.pages.dashboard') }}">
                        Dashboard
                    </a>
                @else
                    <a class="px-5 py-2.5 rounded-full bg-primary text-white font-semibold hover:bg-primary-dark transition-all shadow-lg shadow-primary/30" href="{{ route('filament.admin.auth.login') }}">
                        Masuk
                    </a>
                @endauth
            </div>
            <div class="md:hidden flex items-center">
                <button class="text-slate-600 dark:text-slate-300 hover:text-primary focus:outline-none">
                    <span class="material-icons-round text-3xl">menu</span>
                </button>
            </div>
        </div>
    </div>
</nav>
<section class="relative pt-32 pb-20 lg:pt-40 lg:pb-32 overflow-hidden">
    <div class="absolute inset-0 bg-hero-gradient opacity-10 dark:opacity-20 pointer-events-none"></div>
    <div class="absolute -top-24 -right-24 w-96 h-96 bg-teal-accent/20 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute top-1/2 -left-24 w-72 h-72 bg-primary/20 rounded-full blur-3xl pointer-events-none"></div>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
            <div class="space-y-8">
                <div class="inline-flex items-center space-x-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-full px-4 py-1.5 shadow-sm">
                    <span class="flex h-2 w-2 rounded-full bg-teal-accent animate-pulse"></span>
                    <span class="text-sm font-semibold text-slate-600 dark:text-slate-300">Versi 2.0 Kini Tersedia</span>
                </div>
                <h1 class="text-5xl lg:text-6xl font-extrabold tracking-tight leading-tight text-slate-900 dark:text-white">
                    Efisiensi Tinggi dengan <span class="text-transparent bg-clip-text bg-gradient-to-r from-primary to-teal-accent">Scan QR</span>
                </h1>
                <p class="text-lg text-slate-600 dark:text-slate-300 max-w-lg leading-relaxed">
                    Solusi absensi dan keanggotaan cerdas untuk organisasi besar di Indonesia. Kelola ribuan anggota dengan data real-time dan analitik mendalam.
                </p>
                <div class="flex flex-col sm:flex-row gap-4">
                    <a class="inline-flex justify-center items-center px-8 py-4 rounded-xl bg-primary text-white font-bold text-lg hover:bg-primary-dark transition-all shadow-xl shadow-primary/30 transform hover:-translate-y-1" href="{{ route('filament.admin.auth.login') }}">
                        Mulai Sekarang
                        <span class="material-icons-round ml-2">arrow_forward</span>
                    </a>
                    <a class="inline-flex justify-center items-center px-8 py-4 rounded-xl bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 font-bold text-lg hover:bg-slate-50 dark:hover:bg-slate-700 transition-all" href="#">
                        <span class="material-icons-round mr-2 text-primary">play_circle</span>
                        Demo Video
                    </a>
                </div>
                <div class="pt-4 flex items-center gap-4 text-sm text-slate-500 dark:text-slate-400 font-medium">
                    <div class="flex -space-x-2">
                        <img alt="User Avatar 1" class="w-8 h-8 rounded-full border-2 border-white dark:border-background-dark object-cover" src="https://lh3.googleusercontent.com/aida-public/AB6AXuCBNKQmBgYtawx0eWZJ9vfZslP1SCrNkTbPnDU0LbkyknqRLaZqHStYlYV-wxMyrnQW4AEU4KwR-V03xoPsSLVq7oBh_dX_uBjwQQO24FeOtgmXJQKvEUOBcMTm3xdTuimkv0HDIP-VVT6d7IEcIygwWYuL5OHbOZB-fbwaVyJB8w5ic66zYbtMg1uAzkFZSQo1gP40Gn4AWVfZ3zE-6ILsPyt3vBCaZicqBniyM_Qjt9G1S2JnokRdbgrNByPvQQag6bC0a-r-Jro"/>
                        <img alt="User Avatar 2" class="w-8 h-8 rounded-full border-2 border-white dark:border-background-dark object-cover" src="https://lh3.googleusercontent.com/aida-public/AB6AXuB2g_FnELCV1SJGSJXe5e82Qtog8XmqaupdV09ss2MV-ohF-xGsTujydgMOWlgPxOy1mvZtzgYhj13SZL6-O-tTi3eWFM6ilyTxopI2lSSTdlnWE5P4SgFGWV4Lho5qp1DGPx1XXUT2OpWqD7Z8T0S9tk-1MFNM1y1QUkUHJbZmzNHw8gTWztd5UD0ITdFFQQT4lw3td6A5kebRIsM6M9Zk1UlYrEzYqIHCc7NjmH35R7suk6QE6Jx-dDUJhr7fwRLQdigpIIWx0vE"/>
                        <img alt="User Avatar 3" class="w-8 h-8 rounded-full border-2 border-white dark:border-background-dark object-cover" src="https://lh3.googleusercontent.com/aida-public/AB6AXuC-t8Y29C2_-hQ5HmYekZa_MRZFnsOp3v9bMnmlqHtC81JnNqYJjWq57__HmCL1HWJzLBjSdhY3Gxgqhwzky4JfWqPe5oZah-QOyCRR76NPRgTlEuVEANBQB0bRaRKOAKNRs_CXSoxCZeyiqH8v8haevl9lwDLLv-bzQYKTdTzsKlLMxwkMi91gjg_lB7pwjphxlrriC9-sTuxWT7zEIM-VWBtf8-irahnOb6BjeFxVnZn1bOscVlZJWdIjrdb_qA-1Zppeit-o8Hs"/>
                        <div class="w-8 h-8 rounded-full border-2 border-white dark:border-background-dark bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-xs text-slate-600 dark:text-slate-300 font-bold">+2k</div>
                    </div>
                    <p>Organisasi telah bergabung</p>
                </div>
            </div>
            <div class="relative lg:h-[600px] flex items-center justify-center">
                <div class="relative w-full max-w-md aspect-[4/5] bg-white dark:bg-slate-800 rounded-2xl shadow-2xl border border-slate-100 dark:border-slate-700 p-6 overflow-hidden transform rotate-2 hover:rotate-0 transition-transform duration-500">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h3 class="font-bold text-slate-900 dark:text-white text-lg">Dashboard Absensi</h3>
                            <p class="text-xs text-slate-500">Senin, 24 Okt 2023</p>
                        </div>
                        <div class="h-10 w-10 bg-primary/10 rounded-full flex items-center justify-center text-primary">
                            <span class="material-icons-round">qr_code_scanner</span>
                        </div>
                    </div>
                    <div class="h-40 w-full bg-gradient-to-b from-primary/5 to-transparent rounded-xl border border-primary/10 mb-6 relative overflow-hidden flex items-end px-2 pb-2 gap-2">
                        <div class="w-1/6 bg-primary/40 h-1/3 rounded-t-sm"></div>
                        <div class="w-1/6 bg-primary/60 h-2/3 rounded-t-sm"></div>
                        <div class="w-1/6 bg-primary h-full rounded-t-sm"></div>
                        <div class="w-1/6 bg-primary/80 h-4/5 rounded-t-sm"></div>
                        <div class="w-1/6 bg-primary/50 h-1/2 rounded-t-sm"></div>
                        <div class="w-1/6 bg-primary/30 h-1/4 rounded-t-sm"></div>
                    </div>
                    <div class="space-y-3">
                        <div class="flex items-center p-3 rounded-lg bg-slate-50 dark:bg-slate-700/50 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                            <div class="h-10 w-10 rounded-full bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mr-3">
                                <span class="material-icons-round text-lg">check_circle</span>
                            </div>
                            <div>
                                <p class="font-bold text-sm text-slate-800 dark:text-slate-200">Ahmad Fauzi</p>
                                <p class="text-xs text-slate-500">Hadir • 07:45 WIB</p>
                            </div>
                            <span class="ml-auto text-xs font-semibold text-emerald-600 dark:text-emerald-400 bg-emerald-100 dark:bg-emerald-900/20 px-2 py-1 rounded">Tepat Waktu</span>
                        </div>
                        <div class="flex items-center p-3 rounded-lg bg-slate-50 dark:bg-slate-700/50 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                            <div class="h-10 w-10 rounded-full bg-amber-100 dark:bg-amber-900/30 text-status-late flex items-center justify-center mr-3">
                                <span class="material-icons-round text-lg">schedule</span>
                            </div>
                            <div>
                                <p class="font-bold text-sm text-slate-800 dark:text-slate-200">Siti Aminah</p>
                                <p class="text-xs text-slate-500">Terlambat • 08:15 WIB</p>
                            </div>
                            <span class="ml-auto text-xs font-semibold text-status-late bg-amber-100 dark:bg-amber-900/20 px-2 py-1 rounded">Terlambat</span>
                        </div>
                        <div class="flex items-center p-3 rounded-lg bg-slate-50 dark:bg-slate-700/50 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                            <div class="h-10 w-10 rounded-full bg-primary/10 text-primary flex items-center justify-center mr-3">
                                <span class="material-icons-round text-lg">check_circle</span>
                            </div>
                            <div>
                                <p class="font-bold text-sm text-slate-800 dark:text-slate-200">Budi Santoso</p>
                                <p class="text-xs text-slate-500">Hadir • 07:50 WIB</p>
                            </div>
                            <span class="ml-auto text-xs font-semibold text-primary bg-primary/10 px-2 py-1 rounded">Tepat Waktu</span>
                        </div>
                    </div>
                </div>
                <div class="absolute bottom-10 -left-4 bg-white dark:bg-slate-800 p-4 rounded-xl shadow-xl border border-slate-100 dark:border-slate-700 animate-bounce" style="animation-duration: 3s;">
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
        <div class="grid grid-cols-2 md:grid-cols-4 gap-8 text-center divide-x divide-slate-100 dark:divide-slate-800">
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
            <h2 class="text-primary font-bold tracking-wide uppercase text-sm mb-2">Mengapa inTime?</h2>
            <h3 class="text-3xl md:text-4xl font-extrabold text-slate-900 dark:text-white mb-4">Fitur Unggulan untuk Produktivitas</h3>
            <p class="text-lg text-slate-600 dark:text-slate-400">Kami merancang setiap fitur untuk memudahkan manajemen SDM dan keanggotaan Anda tanpa hambatan.</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="group bg-white dark:bg-slate-800 rounded-2xl p-8 border border-slate-100 dark:border-slate-700 shadow-sm hover:shadow-xl transition-all duration-300 relative overflow-hidden">
                <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                    <span class="material-icons-round text-9xl text-teal-accent transform rotate-12">cake</span>
                </div>
                <div class="relative z-10">
                    <span class="inline-block px-3 py-1 rounded-full text-xs font-bold bg-teal-accent/20 text-teal-accent dark:bg-teal-accent/40 dark:text-white mb-6">
                        Usia Otomatis
                    </span>
                    <div class="h-14 w-14 bg-teal-accent/10 rounded-xl flex items-center justify-center text-teal-accent mb-6 group-hover:scale-110 transition-transform">
                        <span class="material-icons-round text-3xl">cake</span>
                    </div>
                    <h4 class="text-xl font-bold text-slate-900 dark:text-white mb-3">Kalkulasi Umur Real-time</h4>
                    <p class="text-slate-600 dark:text-slate-400 leading-relaxed">
                        Sistem secara otomatis menghitung dan memperbarui usia anggota berdasarkan tanggal lahir, memudahkan pengelompokan demografi.
                    </p>
                </div>
            </div>
            <div class="group bg-white dark:bg-slate-800 rounded-2xl p-8 border border-slate-100 dark:border-slate-700 shadow-sm hover:shadow-xl transition-all duration-300 relative overflow-hidden">
                <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                    <span class="material-icons-round text-9xl text-status-late transform rotate-12">layers</span>
                </div>
                <div class="relative z-10">
                    <span class="inline-block px-3 py-1 rounded-full text-xs font-bold bg-status-late/10 text-status-late dark:bg-status-late/30 dark:text-amber-200 mb-6">
                        Level Tak Terbatas
                    </span>
                    <div class="h-14 w-14 bg-status-late/10 rounded-xl flex items-center justify-center text-status-late mb-6 group-hover:scale-110 transition-transform">
                        <span class="material-icons-round text-3xl">layers</span>
                    </div>
                    <h4 class="text-xl font-bold text-slate-900 dark:text-white mb-3">Struktur Organisasi Fleksibel</h4>
                    <p class="text-slate-600 dark:text-slate-400 leading-relaxed">
                        Buat tingkatan keanggotaan atau departemen tanpa batas. inTime beradaptasi dengan kompleksitas hierarki organisasi Anda.
                    </p>
                </div>
            </div>
            <div class="group bg-white dark:bg-slate-800 rounded-2xl p-8 border border-slate-100 dark:border-slate-700 shadow-sm hover:shadow-xl transition-all duration-300 relative overflow-hidden">
                <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                    <span class="material-icons-round text-9xl text-primary transform rotate-12">verified_user</span>
                </div>
                <div class="relative z-10">
                    <span class="inline-block px-3 py-1 rounded-full text-xs font-bold bg-primary/10 text-primary dark:bg-primary/20 mb-6">
                        Keamanan Terjamin
                    </span>
                    <div class="h-14 w-14 bg-primary/10 rounded-xl flex items-center justify-center text-primary mb-6 group-hover:scale-110 transition-transform">
                        <span class="material-icons-round text-3xl">verified_user</span>
                    </div>
                    <h4 class="text-xl font-bold text-slate-900 dark:text-white mb-3">Enkripsi Data Tingkat Lanjut</h4>
                    <p class="text-slate-600 dark:text-slate-400 leading-relaxed">
                        Data kehadiran dan identitas anggota dilindungi dengan enkripsi standar industri dan backup berkala otomatis.
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
                <div class="relative rounded-2xl bg-background-light dark:bg-background-dark p-6 border border-slate-200 dark:border-slate-800 shadow-2xl">
                    <div class="flex justify-between items-end mb-4">
                        <div>
                            <h5 class="text-sm uppercase text-slate-500 font-bold">Trend Kehadiran</h5>
                            <h3 class="text-2xl font-bold text-slate-900 dark:text-white">Minggu Ini</h3>
                        </div>
                        <div class="flex gap-2">
                            <div class="px-3 py-1 rounded bg-white dark:bg-slate-700 shadow text-xs font-medium">Harian</div>
                            <div class="px-3 py-1 rounded bg-transparent hover:bg-white dark:hover:bg-slate-700 text-slate-500 text-xs font-medium cursor-pointer">Bulanan</div>
                        </div>
                    </div>
                    <div class="h-64 w-full flex items-end justify-between gap-2 px-2">
                        <div class="w-full bg-slate-200 dark:bg-slate-800 rounded-t-md relative group">
                            <div class="absolute bottom-0 w-full bg-primary/30 h-[40%] rounded-t-md group-hover:bg-primary/50 transition-colors"></div>
                            <div class="absolute -top-8 left-1/2 -translate-x-1/2 opacity-0 group-hover:opacity-100 text-xs bg-slate-800 text-white px-2 py-1 rounded transition-opacity">Mon</div>
                        </div>
                        <div class="w-full bg-slate-200 dark:bg-slate-800 rounded-t-md relative group">
                            <div class="absolute bottom-0 w-full bg-primary/40 h-[65%] rounded-t-md group-hover:bg-primary/60 transition-colors"></div>
                            <div class="absolute -top-8 left-1/2 -translate-x-1/2 opacity-0 group-hover:opacity-100 text-xs bg-slate-800 text-white px-2 py-1 rounded transition-opacity">Tue</div>
                        </div>
                        <div class="w-full bg-slate-200 dark:bg-slate-800 rounded-t-md relative group">
                            <div class="absolute bottom-0 w-full bg-primary/60 h-[55%] rounded-t-md group-hover:bg-primary/80 transition-colors"></div>
                            <div class="absolute -top-8 left-1/2 -translate-x-1/2 opacity-0 group-hover:opacity-100 text-xs bg-slate-800 text-white px-2 py-1 rounded transition-opacity">Wed</div>
                        </div>
                        <div class="w-full bg-slate-200 dark:bg-slate-800 rounded-t-md relative group">
                            <div class="absolute bottom-0 w-full bg-primary h-[85%] rounded-t-md shadow-[0_0_15px_rgba(6,95,70,0.5)]"></div>
                            <div class="absolute -top-8 left-1/2 -translate-x-1/2 opacity-100 text-xs bg-primary text-white px-2 py-1 rounded font-bold">Thu</div>
                        </div>
                        <div class="w-full bg-slate-200 dark:bg-slate-800 rounded-t-md relative group">
                            <div class="absolute bottom-0 w-full bg-primary/50 h-[60%] rounded-t-md group-hover:bg-primary/70 transition-colors"></div>
                            <div class="absolute -top-8 left-1/2 -translate-x-1/2 opacity-0 group-hover:opacity-100 text-xs bg-slate-800 text-white px-2 py-1 rounded transition-opacity">Fri</div>
                        </div>
                        <div class="w-full bg-slate-200 dark:bg-slate-800 rounded-t-md relative group">
                            <div class="absolute bottom-0 w-full bg-teal-accent/60 h-[30%] rounded-t-md group-hover:bg-teal-accent/80 transition-colors"></div>
                            <div class="absolute -top-8 left-1/2 -translate-x-1/2 opacity-0 group-hover:opacity-100 text-xs bg-slate-800 text-white px-2 py-1 rounded transition-opacity">Sat</div>
                        </div>
                        <div class="w-full bg-slate-200 dark:bg-slate-800 rounded-t-md relative group">
                            <div class="absolute bottom-0 w-full bg-teal-accent/40 h-[20%] rounded-t-md group-hover:bg-teal-accent/60 transition-colors"></div>
                            <div class="absolute -top-8 left-1/2 -translate-x-1/2 opacity-0 group-hover:opacity-100 text-xs bg-slate-800 text-white px-2 py-1 rounded transition-opacity">Sun</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="w-full lg:w-1/2 order-1 lg:order-2 space-y-6">
                <h2 class="text-3xl lg:text-4xl font-extrabold text-slate-900 dark:text-white leading-tight">
                    Wawasan Data untuk <br/><span class="text-primary">Keputusan Lebih Baik</span>
                </h2>
                <p class="text-lg text-slate-600 dark:text-slate-400">
                    Jangan biarkan data absensi hanya menjadi tumpukan angka. Dashboard inTime menyajikan visualisasi yang intuitif, membantu Anda memahami pola kehadiran, keterlambatan, dan produktivitas tim secara instan.
                </p>
                <ul class="space-y-4 pt-4">
                    <li class="flex items-start">
                        <span class="material-icons-round text-primary mr-3 mt-1">analytics</span>
                        <span class="text-slate-700 dark:text-slate-300 font-medium">Laporan analitik bulanan yang dapat diunduh (PDF/Excel)</span>
                    </li>
                    <li class="flex items-start">
                        <span class="material-icons-round text-primary mr-3 mt-1">notifications_active</span>
                        <span class="text-slate-700 dark:text-slate-300 font-medium">Notifikasi anomali kehadiran secara real-time</span>
                    </li>
                    <li class="flex items-start">
                        <span class="material-icons-round text-primary mr-3 mt-1">people</span>
                        <span class="text-slate-700 dark:text-slate-300 font-medium">Pemetaan demografi anggota otomatis</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</section>
<section class="py-12 bg-slate-50 dark:bg-slate-900/50 border-t border-slate-200 dark:border-slate-800">
    <div class="max-w-7xl mx-auto px-4 text-center">
        <p class="text-sm font-semibold text-slate-400 uppercase tracking-widest mb-8">Built with Modern Technology Stack</p>
        <div class="flex flex-wrap justify-center items-center gap-12 opacity-60 grayscale hover:grayscale-0 transition-all duration-500">
            <div class="flex items-center gap-2 group">
                <div class="w-8 h-8 bg-red-500 rounded flex items-center justify-center text-white font-bold text-xs">L</div>
                <span class="font-bold text-xl text-slate-600 dark:text-slate-300 group-hover:text-red-500 transition-colors">Laravel</span>
            </div>
            <div class="flex items-center gap-2 group">
                <div class="w-8 h-8 bg-amber-500 rounded flex items-center justify-center text-white font-bold text-xs">F</div>
                <span class="font-bold text-xl text-slate-600 dark:text-slate-300 group-hover:text-amber-500 transition-colors">Filament</span>
            </div>
            <div class="flex items-center gap-2 group">
                <div class="w-8 h-8 bg-cyan-500 rounded flex items-center justify-center text-white font-bold text-xs">T</div>
                <span class="font-bold text-xl text-slate-600 dark:text-slate-300 group-hover:text-cyan-500 transition-colors">Tailwind CSS</span>
            </div>
        </div>
    </div>
</section>
<section class="py-24 bg-background-light dark:bg-background-dark">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="relative bg-primary rounded-3xl p-10 md:p-16 text-center overflow-hidden shadow-2xl shadow-primary/40">
            <div class="absolute top-0 left-0 w-full h-full overflow-hidden opacity-20 pointer-events-none">
                <div class="absolute -top-24 -left-24 w-96 h-96 bg-white rounded-full blur-3xl opacity-30"></div>
                <div class="absolute top-1/2 -right-24 w-72 h-72 bg-teal-accent rounded-full blur-3xl opacity-40"></div>
            </div>
            <div class="relative z-10">
                <h2 class="text-3xl md:text-5xl font-extrabold text-white mb-6">Siap Meningkatkan Efisiensi?</h2>
                <p class="text-emerald-100 text-lg md:text-xl max-w-2xl mx-auto mb-10">
                    Bergabunglah dengan ratusan organisasi yang telah beralih ke inTime. Coba demo gratis tanpa kartu kredit.
                </p>
                <div class="flex flex-col sm:flex-row justify-center gap-4">
                    <a href="{{ route('filament.admin.auth.login') }}" class="px-8 py-4 bg-white text-primary font-bold rounded-xl shadow-lg hover:bg-slate-50 transition-colors transform hover:-translate-y-1 inline-block">
                        Jadwalkan Demo
                    </a>
                    <a href="mailto:support@intime.id" class="px-8 py-4 bg-transparent border-2 border-white/30 text-white font-bold rounded-xl hover:bg-white/10 transition-colors inline-block">
                        Hubungi Sales
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
                    <span class="font-bold text-2xl tracking-tight text-slate-900 dark:text-white">inTime</span>
                </div>
                <p class="text-slate-500 text-sm leading-relaxed mb-4">
                    Platform manajemen absensi modern untuk efisiensi maksimal organisasi Anda.
                </p>
                <div class="flex gap-4">
                    <a class="text-slate-400 hover:text-primary transition-colors" href="#"><span class="material-icons-round">facebook</span></a>
                    <a class="text-slate-400 hover:text-primary transition-colors" href="#"><span class="material-icons-round">smart_display</span></a>
                    <a class="text-slate-400 hover:text-primary transition-colors" href="#"><span class="material-icons-round">alternate_email</span></a>
                </div>
            </div>
            <div>
                <h4 class="font-bold text-slate-900 dark:text-white mb-4">Produk</h4>
                <ul class="space-y-2 text-sm text-slate-500 dark:text-slate-400">
                    <li><a class="hover:text-primary transition-colors" href="#">Fitur</a></li>
                    <li><a class="hover:text-primary transition-colors" href="#">Integrasi</a></li>
                    <li><a class="hover:text-primary transition-colors" href="#">Harga</a></li>
                    <li><a class="hover:text-primary transition-colors" href="#">Updates</a></li>
                </ul>
            </div>
            <div>
                <h4 class="font-bold text-slate-900 dark:text-white mb-4">Perusahaan</h4>
                <ul class="space-y-2 text-sm text-slate-500 dark:text-slate-400">
                    <li><a class="hover:text-primary transition-colors" href="#">Tentang Kami</a></li>
                    <li><a class="hover:text-primary transition-colors" href="#">Karir</a></li>
                    <li><a class="hover:text-primary transition-colors" href="#">Blog</a></li>
                    <li><a class="hover:text-primary transition-colors" href="#">Kontak</a></li>
                </ul>
            </div>
            <div>
                <h4 class="font-bold text-slate-900 dark:text-white mb-4">Legal</h4>
                <ul class="space-y-2 text-sm text-slate-500 dark:text-slate-400">
                    <li><a class="hover:text-primary transition-colors" href="#">Kebijakan Privasi</a></li>
                    <li><a class="hover:text-primary transition-colors" href="#">Syarat &amp; Ketentuan</a></li>
                    <li><a class="hover:text-primary transition-colors" href="#">Keamanan</a></li>
                </ul>
            </div>
        </div>
        <div class="pt-8 border-t border-slate-100 dark:border-slate-800 flex flex-col md:flex-row justify-between items-center gap-4">
            <p class="text-sm text-slate-400">© {{ date('Y') }} inTime Indonesia. All rights reserved.</p>
            <div class="flex gap-6 text-sm text-slate-400">
                <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-emerald-500"></span> System Operational</span>
            </div>
        </div>
    </div>
</footer>
</body>
</html>
