<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">

        <title>{{ config('app.name', 'inTime') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=manrope:400,500,600,700,800" rel="stylesheet" />

        <!-- Styles / Scripts -->
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @else
            <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
            <script>
                tailwind.config = {
                    darkMode: "class",
                    theme: {
                        extend: {
                            colors: {
                                "primary": "#065f46",
                                "primary-dark": "#064e3b",
                                "background-light": "#f6f7f8",
                                "background-dark": "#0d1511",
                                "teal-accent": "#8da399",
                            },
                            fontFamily: {
                                "display": ["Manrope", "sans-serif"]
                            },
                        },
                    },
                }
            </script>
        @endif

        <style>
            body {
                font-family: 'Manrope', sans-serif;
            }
        </style>
    </head>
    <body class="bg-background-light dark:bg-background-dark text-slate-800 dark:text-slate-100 font-display antialiased min-h-screen">
        <!-- Main Container -->
        <div class="min-h-screen flex flex-col items-center justify-center px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
            
            <!-- Header / Navigation -->
            <header class="w-full max-w-4xl mb-6 sm:mb-8">
                @if (Route::has('login'))
                    <nav class="flex items-center justify-between gap-4">
                        <!-- Logo -->
                        <div class="flex items-center gap-2">
                            <span class="material-icons-round text-primary text-2xl sm:text-3xl">schedule</span>
                            <span class="font-bold text-xl sm:text-2xl text-slate-900 dark:text-white">inTime</span>
                        </div>

                        <!-- Auth Buttons -->
                        <div class="flex items-center gap-2 sm:gap-3">
                            @auth
                                <a
                                    href="{{ url('/dashboard') }}"
                                    class="inline-flex items-center justify-center px-4 sm:px-5 py-2 sm:py-2.5 rounded-full bg-primary text-white font-semibold text-sm hover:bg-primary-dark transition-all shadow-lg shadow-primary/30 min-h-[44px]"
                                >
                                    Dashboard
                                </a>
                            @else
                                <a
                                    href="{{ route('login') }}"
                                    class="inline-flex items-center justify-center px-4 sm:px-5 py-2 sm:py-2.5 rounded-full bg-primary text-white font-semibold text-sm hover:bg-primary-dark transition-all shadow-lg shadow-primary/30 min-h-[44px]"
                                >
                                    Masuk
                                </a>

                                @if (Route::has('register'))
                                    <a
                                        href="{{ route('register') }}"
                                        class="hidden sm:inline-flex items-center justify-center px-4 sm:px-5 py-2 sm:py-2.5 rounded-full bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 font-semibold text-sm hover:bg-slate-50 dark:hover:bg-slate-700 transition-all min-h-[44px]"
                                    >
                                        Register
                                    </a>
                                @endif
                            @endauth
                        </div>
                    </nav>
                @endif
            </header>

            <!-- Main Content Card -->
            <main class="w-full max-w-4xl flex flex-col lg:flex-row gap-0 lg:gap-6">
                
                <!-- Left Card (Info) -->
                <div class="flex-1 bg-white dark:bg-slate-800 rounded-2xl shadow-xl border border-slate-100 dark:border-slate-700 p-6 sm:p-8 lg:p-10 order-2 lg:order-1">
                    <div class="mb-6 sm:mb-8">
                        <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white mb-3 sm:mb-4">
                            Selamat Datang di <span class="text-primary">inTime</span>
                        </h1>
                        <p class="text-base sm:text-lg text-slate-600 dark:text-slate-300 leading-relaxed">
                            Sistem Absensi & Keanggotaan Cerdas untuk organisasi modern di Indonesia.
                        </p>
                    </div>

                    <!-- Features List -->
                    <ul class="space-y-4 sm:space-y-5 mb-6 sm:mb-8">
                        <li class="flex items-start gap-3 sm:gap-4">
                            <div class="flex-shrink-0 w-8 h-8 sm:w-10 sm:h-10 rounded-full bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center">
                                <svg class="w-4 h-4 sm:w-5 sm:h-5 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-semibold text-slate-900 dark:text-white text-sm sm:text-base">Scan QR Code</h3>
                                <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Absensi cepat dengan scan QR Code personal</p>
                            </div>
                        </li>

                        <li class="flex items-start gap-3 sm:gap-4">
                            <div class="flex-shrink-0 w-8 h-8 sm:w-10 sm:h-10 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                                <svg class="w-4 h-4 sm:w-5 sm:h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-semibold text-slate-900 dark:text-white text-sm sm:text-base">Real-time Analytics</h3>
                                <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Dashboard interaktif dengan data kehadiran live</p>
                            </div>
                        </li>

                        <li class="flex items-start gap-3 sm:gap-4">
                            <div class="flex-shrink-0 w-8 h-8 sm:w-10 sm:h-10 rounded-full bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center">
                                <svg class="w-4 h-4 sm:w-5 sm:h-5 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-semibold text-slate-900 dark:text-white text-sm sm:text-base">Multi-level Organization</h3>
                                <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Struktur organisasi bertingkat tanpa batas</p>
                            </div>
                        </li>
                    </ul>

                    <!-- CTA Buttons -->
                    <div class="flex flex-col sm:flex-row gap-3 sm:gap-4">
                        @auth
                            <a
                                href="{{ url('/dashboard') }}"
                                class="inline-flex items-center justify-center px-6 py-3 sm:py-4 rounded-xl bg-primary text-white font-bold text-base hover:bg-primary-dark transition-all shadow-xl shadow-primary/30 min-h-[48px]"
                            >
                                <span>Buka Dashboard</span>
                                <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                                </svg>
                            </a>
                        @else
                            <a
                                href="{{ route('login') }}"
                                class="inline-flex items-center justify-center px-6 py-3 sm:py-4 rounded-xl bg-primary text-white font-bold text-base hover:bg-primary-dark transition-all shadow-xl shadow-primary/30 min-h-[48px]"
                            >
                                Mulai Sekarang
                                <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                                </svg>
                            </a>
                        @endauth

                        <a
                            href="#learn-more"
                            class="inline-flex items-center justify-center px-6 py-3 sm:py-4 rounded-xl bg-white dark:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 font-bold text-base hover:bg-slate-50 dark:hover:bg-slate-600 transition-all min-h-[48px]"
                        >
                            Pelajari Lebih Lanjut
                        </a>
                    </div>
                </div>

                <!-- Right Card (Visual/Stats) -->
                <div class="w-full lg:w-80 flex-shrink-0 order-1 lg:order-2 mb-6 lg:mb-0">
                    <div class="bg-gradient-to-br from-primary to-primary-dark rounded-2xl shadow-xl p-6 sm:p-8 text-white h-full">
                        <div class="mb-6 sm:mb-8">
                            <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-2xl bg-white/20 flex items-center justify-center mb-4 backdrop-blur-sm">
                                <svg class="w-6 h-6 sm:w-8 sm:h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <h2 class="text-xl sm:text-2xl font-bold mb-2">inTime v2.0</h2>
                            <p class="text-emerald-100 text-sm">Versi terbaru kini tersedia</p>
                        </div>

                        <!-- Stats -->
                        <div class="space-y-4 sm:space-y-6">
                            <div class="flex items-center justify-between">
                                <span class="text-emerald-100 text-sm">Organisasi Aktif</span>
                                <span class="font-bold text-lg sm:text-xl">500+</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-emerald-100 text-sm">Scan Bulanan</span>
                                <span class="font-bold text-lg sm:text-xl">1.2M</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-emerald-100 text-sm">Uptime Server</span>
                                <span class="font-bold text-lg sm:text-xl">99.9%</span>
                            </div>
                        </div>

                        <!-- Status Indicator -->
                        <div class="mt-6 sm:mt-8 pt-6 sm:pt-8 border-t border-white/20">
                            <div class="flex items-center gap-3">
                                <div class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></div>
                                <span class="text-sm font-medium">Sistem Online & Stabil</span>
                            </div>
                        </div>
                    </div>
                </div>

            </main>

            <!-- Footer -->
            <footer class="w-full max-w-4xl mt-8 sm:mt-12 pt-6 sm:pt-8 border-t border-slate-200 dark:border-slate-700">
                <div class="flex flex-col sm:flex-row justify-between items-center gap-4 text-sm text-slate-500 dark:text-slate-400">
                    <p>&copy; {{ date('Y') }} inTime Indonesia. All rights reserved.</p>
                    <div class="flex items-center gap-4">
                        <a href="#" class="hover:text-primary transition-colors">Privacy</a>
                        <a href="#" class="hover:text-primary transition-colors">Terms</a>
                        <a href="#" class="hover:text-primary transition-colors">Support</a>
                    </div>
                </div>
            </footer>

        </div>

        <!-- Material Icons -->
        <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    </body>
</html>
