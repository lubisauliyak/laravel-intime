<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Perizinan: {{ $meeting->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
        }
    </script>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    @include('components.theme-script')
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap');

        body {
            font-family: 'Manrope', sans-serif;
        }

        .glass {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(0, 0, 0, 0.1);
        }

        .dark .glass {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .scan-line {
            animation: scan 2s linear infinite;
        }

        @keyframes scan {
            0% {
                top: 0;
            }

            100% {
                top: 100%;
            }
        }

        #reader {
            border: none !important;
        }

        #reader video {
            object-fit: cover !important;
            width: 100% !important;
            height: 100% !important;
            border-radius: 1rem;
        }

        .mirror-mode {
            transform: scaleX(-1);
        }

        #reader__scan_region {
            background: transparent !important;
        }

        #reader__dashboard {
            display: none !important;
        }

        .step-container {
            display: none;
        }

        .step-container.active {
            display: block;
            animation: fade-in 0.5s ease-out forwards;
        }

        @keyframes fade-in {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Select2 Theme Fixes */
        .select2-container--default .select2-selection--single {
            background: #ffffff !important;
            border: 1px solid #d1d5db !important;
            border-radius: 1rem !important;
            height: auto !important;
            padding: 0.75rem 1rem !important;
        }

        .dark .select2-container--default .select2-selection--single {
            background: #0d1511 !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: #111827 !important;
            font-size: 0.875rem !important;
            font-weight: 900 !important;
            letter-spacing: 0.05em !important;
            text-transform: uppercase !important;
            line-height: 1.25rem !important;
            padding: 0 !important;
        }

        .dark .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: #fff !important;
        }

        .select2-container--default .select2-selection--single .select2-selection__placeholder {
            color: #9ca3af !important;
            text-transform: none !important;
        }

        .dark .select2-container--default .select2-selection--single .select2-selection__placeholder {
            color: rgba(255, 255, 255, 0.3) !important;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 100% !important;
            right: 12px !important;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow b {
            border-color: #9ca3af transparent transparent transparent !important;
        }

        .dark .select2-container--default .select2-selection--single .select2-selection__arrow b {
            border-color: rgba(255, 255, 255, 0.5) transparent transparent transparent !important;
        }

        .select2-dropdown {
            background: #ffffff !important;
            border: 1px solid #d1d5db !important;
            border-radius: 1rem !important;
            overflow: hidden !important;
        }

        .dark .select2-dropdown {
            background: #0d1511 !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
        }

        .select2-container--default .select2-results__option {
            color: #111827 !important;
            font-size: 0.875rem !important;
            font-weight: 700 !important;
            padding: 0.75rem 1rem !important;
            border-bottom: 1px solid #f3f4f6 !important;
        }

        .dark .select2-container--default .select2-results__option {
            color: #fff !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05) !important;
        }

        .select2-container--default .select2-results__option--highlighted {
            background: rgba(16, 185, 129, 0.1) !important;
            color: #059669 !important;
        }

        .dark .select2-container--default .select2-results__option--highlighted {
            background: rgba(16, 185, 129, 0.2) !important;
            color: #34d399 !important;
        }

        .select2-container--default .select2-results__option--selected {
            background: rgba(16, 185, 129, 0.2) !important;
        }

        .dark .select2-container--default .select2-results__option--selected {
            background: rgba(16, 185, 129, 0.3) !important;
        }

        .select2-container--default .select2-search--dropdown .select2-search__field {
            background: #f9fafb !important;
            border: 1px solid #d1d5db !important;
            border-radius: 0.75rem !important;
            color: #111827 !important;
            padding: 0.5rem 0.75rem !important;
            font-size: 0.875rem !important;
            outline: none !important;
        }

        .dark .select2-container--default .select2-search--dropdown .select2-search__field {
            background: rgba(255, 255, 255, 0.05) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            color: #fff !important;
        }

        .select2-container--default .select2-search--dropdown .select2-search__field:focus {
            border-color: #10b981 !important;
            box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.2) !important;
        }

        .select2-container--default.select2-container--disabled .select2-selection--single {
            background: #f3f4f6 !important;
            opacity: 0.5 !important;
            cursor: not-allowed !important;
        }

        .dark .select2-container--default.select2-container--disabled .select2-selection--single {
            background: rgba(255, 255, 255, 0.03) !important;
        }

        .select2-container--default .select2-results>.select2-results__options {
            max-height: 240px !important;
        }

        .select2-container--default .select2-results__option[aria-selected=true] {
            background: rgba(16, 185, 129, 0.1) !important;
        }

        .dark .select2-container--default .select2-results__option[aria-selected=true] {
            background: rgba(16, 185, 129, 0.15) !important;
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
        }
    </style>
</head>

<body
    class="bg-gray-50 dark:bg-[#0d1511] text-gray-900 dark:text-white transition-colors duration-300 min-h-screen overflow-x-hidden flex flex-col">
    <!-- Navbar -->
    <nav
        class="p-4 md:p-6 flex items-center justify-between border-b border-gray-200 dark:border-white/5 bg-white/80 dark:bg-[#0d1511]/80 backdrop-blur-md sticky top-0 z-50">
        <div class="flex items-center gap-3 md:gap-4 overflow-hidden">
            <div class="truncate">
                <h1 class="text-base md:text-lg font-extrabold tracking-tight truncate">{{ $meeting->name }}</h1>
                <p class="text-[9px] md:text-[10px] text-gray-500 uppercase tracking-widest truncate">
                    {{ $meeting->group->name }} • {{ $meeting->meeting_date->format('d M Y') }} •
                    {{ $meeting->start_time->format('H:i') }}-{{ $meeting->end_time->format('H:i') }}
                </p>
            </div>
        </div>
        <div class="flex items-center gap-2 md:gap-3 shrink-0">
            @if($isExpired)
                <div
                    class="flex items-center gap-2 px-3 py-1 md:px-4 md:py-1.5 bg-rose-500/10 border border-rose-500/20 rounded-full">
                    <div class="w-1.5 h-1.5 rounded-full bg-rose-500"></div>
                    <span class="text-[9px] md:text-[10px] font-black text-rose-400 uppercase tracking-widest">Sesi
                        Berakhir</span>
                </div>
            @else
                <div
                    class="flex items-center gap-2 px-3 py-1 md:px-4 md:py-1.5 bg-emerald-500/10 border border-emerald-500/20 rounded-full text-nowrap">
                    <div class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></div>
                    <span class="text-[9px] md:text-[10px] font-black text-emerald-400 uppercase tracking-widest">Menerima
                        Perizinan</span>
                </div>
            @endif
            <div class="ml-2 border-l border-gray-200 dark:border-gray-800 pl-3">
                @include('components.theme-switcher')
            </div>
        </div>
    </nav>

    <main class="max-w-2xl mx-auto w-full p-4 md:p-8 flex-1 flex flex-col justify-center">
        @if($isExpired)
            <div
                class="glass p-10 rounded-2xl border-gray-200 dark:border-white/5 relative overflow-hidden text-center shadow-2xl animate-in fade-in duration-1000">
                <div
                    class="relative w-24 h-24 mx-auto mb-8 bg-rose-500/20 rounded-[2rem] flex items-center justify-center shadow-2xl border border-rose-500/30">
                    <svg class="w-12 h-12 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                            d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <h3 class="text-2xl font-black mb-3 tracking-tighter uppercase text-rose-400">Sesi Berakhir</h3>
                <p class="text-gray-600 dark:text-gray-400 text-sm mb-6 leading-relaxed font-medium">Batas waktu untuk
                    mengajukan perizinan
                    pada pertemuan ini telah berakhir.</p>
            </div>
        @else
            <!-- STEP 1: Identifikasi -->
            <div id="step-1" class="step-container active space-y-8">
                <div class="text-center mb-8">
                    <h2 class="text-3xl font-black tracking-tighter mb-2 text-gray-900 dark:text-white">Form Perizinan</h2>
                    <p class="text-gray-600 dark:text-gray-400 text-sm font-medium">Silakan scan QR KTA Anda atau masukkan
                        secara manual untuk mengajukan perizinan.</p>
                </div>

                <!-- Tabs -->
                <div
                    class="flex p-1 bg-white border border-gray-200 dark:bg-white/5 rounded-2xl dark:border-white/5 shadow-sm">
                    <button type="button" onclick="switchTab('scan')" id="tab-scan"
                        class="flex-1 py-3 text-sm font-black uppercase tracking-widest rounded-xl transition-all bg-emerald-500 text-white shadow-lg flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                        </svg>
                        Scan QR
                    </button>
                    <button type="button" onclick="switchTab('manual')" id="tab-manual"
                        class="flex-1 py-3 text-sm font-black uppercase tracking-widest rounded-xl transition-all text-gray-500 hover:text-gray-900 dark:hover:text-white bg-transparent flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        Cari Nama / Manual
                    </button>
                </div>
                <!-- Helper Guide Caption -->
                <p class="text-xs text-center text-gray-500 dark:text-gray-400 mt-2 mb-6">
                    💡 Tidak membawa KTA fisik? Pilih tab <button type="button" onclick="switchTab('manual')" class="font-bold text-emerald-600 dark:text-emerald-400 underline underline-offset-2 hover:text-emerald-700">Cari Nama / Manual</button> untuk mencari data Anda.
                </p>

                <!-- Scan Section -->
                <div id="section-scan" class="relative group">
                    <div
                        class="absolute -inset-1 bg-gradient-to-r from-emerald-500 to-blue-500 rounded-2xl blur opacity-20 group-hover:opacity-40 transition duration-1000">
                    </div>
                    <div id="reader-container"
                        class="relative bg-gray-100 dark:bg-black rounded-2xl aspect-square overflow-hidden border-8 border-gray-200 dark:border-white/5 shadow-2xl">
                        <div id="reader" style="width: 100%; height: 100%;"></div>

                        <!-- HUD Overlay -->
                        <div class="absolute inset-0 pointer-events-none z-10 flex flex-col justify-between p-6 md:p-10">
                            <div class="flex justify-between">
                                <div
                                    class="w-10 h-10 md:w-14 md:h-14 border-t-4 border-l-4 border-emerald-500 rounded-tl-2xl">
                                </div>
                                <div
                                    class="w-10 h-10 md:w-14 md:h-14 border-t-4 border-r-4 border-emerald-500 rounded-tr-2xl">
                                </div>
                            </div>
                            <div id="scanner-line"
                                class="absolute left-6 right-6 md:left-10 md:right-10 h-0.5 bg-emerald-500/50 shadow-[0_0_20px_rgba(16,185,129,0.8)] scan-line hidden">
                            </div>
                            <div class="flex justify-between">
                                <div
                                    class="w-10 h-10 md:w-14 md:h-14 border-b-4 border-l-4 border-emerald-500 rounded-bl-2xl">
                                </div>
                                <div
                                    class="w-10 h-10 md:w-14 md:h-14 border-b-4 border-r-4 border-emerald-500 rounded-br-2xl">
                                </div>
                            </div>
                        </div>

                        <!-- Stop Camera Button -->
                        <button id="btn-stop-camera" onclick="stopScanning()"
                            class="absolute top-8 right-8 z-30 px-6 py-2.5 bg-rose-500/90 hover:bg-rose-600 text-white font-black rounded-2xl shadow-xl transition-all active:scale-95 uppercase tracking-tighter text-xs hidden backdrop-blur-md border border-white/10">
                            Tutup Kamera
                        </button>

                        <!-- Start Button Overlay -->
                        <div id="start-overlay"
                            class="absolute inset-0 bg-white/90 dark:bg-[#0d1511]/90 backdrop-blur-xl flex flex-col items-center justify-center p-12 text-center z-20 transition-all duration-700">
                            <div class="relative mb-8">
                                <div class="absolute -inset-6 bg-emerald-500/20 blur-2xl rounded-full animate-pulse"></div>
                                <div
                                    class="relative w-24 h-24 bg-emerald-500 rounded-[2rem] flex items-center justify-center shadow-2xl shadow-emerald-500/40 rotate-12 group-hover:rotate-0 transition-transform duration-500">
                                    <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path
                                            d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"
                                            stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </div>
                            </div>
                            <h3 class="text-2xl font-black mb-3 tracking-tighter uppercase text-gray-900 dark:text-white">
                                Scanner Siap</h3>
                            <p class="text-gray-600 dark:text-gray-400 text-sm mb-8 max-w-xs leading-relaxed font-medium">
                                Klik tombol di bawah untuk mulai memindai Kode QR.</p>
                            <button onclick="startScanning()"
                                class="w-full px-8 py-4 bg-gray-900 dark:bg-white text-white dark:text-black font-black rounded-2xl hover:bg-emerald-500 dark:hover:bg-emerald-500 hover:text-white transition-all shadow-xl active:scale-95 uppercase tracking-tighter text-base">AKTIFKAN
                                KAMERA</button>
                        </div>
                    </div>
                </div>

                <!-- Manual Input Section -->
                <div id="section-manual" class="hidden glass p-8 rounded-2xl border-gray-200 dark:border-white/5 relative">
                    <div class="space-y-4">
                        <label class="text-xs font-black uppercase tracking-widest text-gray-500 ml-1">Asal Kelompok</label>
                        <select id="manual-group" class="w-full">
                            <option value="" selected>Pilih Grup...</option>
                            @foreach($groups as $grp)
                                <option value="{{ $grp->id }}">{{ $grp->full_name }}</option>
                            @endforeach
                        </select>

                        <label class="text-xs font-black uppercase tracking-widest text-gray-500 ml-1 block">Nama
                            Anggota</label>
                        <select id="manual-member" class="w-full" disabled>
                            <option value="" selected>Pilih Grup Terlebih Dahulu...</option>
                        </select>

                        <button onclick="lookupManual()" id="btn-lookup"
                            class="w-full py-4 bg-emerald-500 hover:bg-emerald-600 text-white font-black rounded-2xl transition-all shadow-xl shadow-emerald-500/20 uppercase tracking-tighter disabled:opacity-50 disabled:cursor-not-allowed"
                            disabled>LANJUTKAN</button>
                    </div>
                    <div class="mt-6 text-center border-t border-gray-200 dark:border-white/10 pt-6">
                        <button type="button" onclick="goToStep1b()"
                            class="text-xs font-bold text-emerald-500 dark:text-emerald-400 hover:text-emerald-600 dark:hover:text-emerald-300 transition-colors uppercase tracking-wider">Belum
                            punya KTA / Belum Terdaftar?</button>
                    </div>
                </div>
            </div>

            <!-- STEP 1b: Pendaftaran Sementara -->
            <div id="step-1b" class="step-container space-y-8">
                <div class="text-center mb-8">
                    <h2 class="text-3xl font-black tracking-tighter mb-2 text-gray-900 dark:text-white">Pendaftaran Instan
                    </h2>
                    <p class="text-gray-600 dark:text-gray-400 text-sm font-medium">Isi form ini untuk melanjutkan jika Anda
                        belum terdaftar /
                        belum memiliki KTA.</p>
                </div>

                <div class="glass p-8 rounded-2xl border-gray-200 dark:border-white/5 relative">
                    <form id="register-temporary-form" class="space-y-6"
                        onsubmit="event.preventDefault(); submitTemporaryRegistration();">
                        <div class="space-y-2">
                            <label class="text-xs font-black uppercase tracking-widest text-gray-500 ml-1">Nama Lengkap
                                <span class="text-rose-500">*</span></label>
                            <input type="text" name="full_name" required
                                class="w-full bg-white dark:bg-white/5 border border-gray-300 dark:border-white/10 rounded-xl p-4 text-sm focus:ring-2 focus:ring-emerald-500 outline-none transition-all placeholder:text-gray-400 dark:placeholder:text-white/30"
                                placeholder="Masukkan nama lengkap">
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <label class="text-xs font-black uppercase tracking-widest text-gray-500 ml-1">Jenis Kelamin
                                    <span class="text-rose-500">*</span></label>
                                <select name="gender" required
                                    class="w-full bg-white dark:bg-[#0d1511] border border-gray-300 dark:border-white/10 rounded-xl p-4 text-sm focus:ring-2 focus:ring-emerald-500 outline-none transition-all text-gray-900 dark:text-white/90 appearance-none">
                                    <option value="" disabled selected>Pilih...</option>
                                    <option value="male">Laki-laki</option>
                                    <option value="female">Perempuan</option>
                                </select>
                            </div>
                            <div class="space-y-2">
                                <label class="text-xs font-black uppercase tracking-widest text-gray-500 ml-1">Tanggal Lahir
                                    <span class="text-rose-500">*</span></label>
                                <input type="date" name="birth_date" required
                                    class="w-full bg-white dark:bg-white/5 border border-gray-300 dark:border-white/10 rounded-xl p-4 text-sm focus:ring-2 focus:ring-emerald-500 outline-none transition-all text-gray-900 dark:text-white/90"
                                    style="color-scheme: dark;">
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label class="text-xs font-black uppercase tracking-widest text-gray-500 ml-1">Asal Kelompok
                                <span class="text-rose-500">*</span></label>
                            <select name="group_id" required
                                class="w-full bg-white dark:bg-[#0d1511] border border-gray-300 dark:border-white/10 rounded-xl p-4 text-sm focus:ring-2 focus:ring-emerald-500 outline-none transition-all text-gray-900 dark:text-white/90 appearance-none">
                                <option value="" disabled selected>Pilih Grup...</option>
                                @foreach($groups as $grp)
                                    <option value="{{ $grp->id }}">{{ $grp->full_name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="pt-4 grid grid-cols-3 gap-4 border-t border-gray-300 dark:border-white/10 mt-6 pt-6">
                            <button type="button" onclick="resetToStep1()"
                                class="col-span-1 py-4 bg-gray-100 dark:bg-white/5 hover:bg-gray-200 dark:hover:bg-white/10 text-gray-700 dark:text-white font-black rounded-2xl transition-all uppercase tracking-tighter text-sm border border-gray-300 dark:border-transparent">Kembali</button>
                            <button type="submit" id="btn-submit-temp"
                                class="col-span-2 py-4 bg-emerald-500 hover:bg-emerald-600 text-white font-black rounded-2xl shadow-xl shadow-emerald-500/20 transition-all uppercase tracking-tighter text-sm">Daftar
                                & Lanjut</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- STEP 1c: Lengkapi Biodata -->
            <div id="step-1c" class="step-container space-y-8">
                <div class="text-center mb-8">
                    <h2 id="biodata-title" class="text-3xl font-black tracking-tighter mb-2 text-gray-900 dark:text-white">Lengkapi Biodata</h2>
                    <p id="biodata-desc" class="text-gray-600 dark:text-gray-400 text-sm font-medium">Data biodata Anda belum lengkap. Silakan lengkapi terlebih dahulu untuk melanjutkan.</p>
                </div>

                <div class="glass p-8 rounded-2xl border-gray-200 dark:border-white/5 relative">
                    <form id="complete-biodata-form" class="space-y-6"
                        onsubmit="event.preventDefault(); submitCompleteBiodata();">
                        <input type="hidden" name="member_id" id="biodata-member-id">

                        <div class="space-y-2">
                            <label class="text-xs font-black uppercase tracking-widest text-gray-500 ml-1">Nama
                                Lengkap</label>
                            <input type="text" id="biodata-full-name" disabled
                                class="w-full bg-gray-100 dark:bg-white/5 border border-gray-300 dark:border-white/10 rounded-xl p-4 text-sm outline-none text-gray-700 dark:text-white/80 font-bold cursor-not-allowed">
                        </div>

                        <div id="biodata-fields-grid" class="grid grid-cols-2 gap-4">
                            <div id="container-biodata-gender" class="space-y-2">
                                <label class="text-xs font-black uppercase tracking-widest text-gray-500 ml-1">Jenis Kelamin
                                    <span class="text-rose-500">*</span></label>
                                <select name="gender" id="biodata-gender" required
                                    class="w-full bg-white dark:bg-[#0d1511] border border-gray-300 dark:border-white/10 rounded-xl p-4 text-sm focus:ring-2 focus:ring-emerald-500 outline-none transition-all text-gray-900 dark:text-white/90 appearance-none">
                                    <option value="" disabled selected>Pilih...</option>
                                    <option value="male">Laki-laki</option>
                                    <option value="female">Perempuan</option>
                                </select>
                            </div>
                            <div id="container-biodata-birth-date" class="space-y-2">
                                <label class="text-xs font-black uppercase tracking-widest text-gray-500 ml-1">Tanggal Lahir
                                    <span class="text-rose-500">*</span></label>
                                <input type="date" name="birth_date" id="biodata-birth-date" required
                                    class="w-full bg-white dark:bg-white/5 border border-gray-300 dark:border-white/10 rounded-xl p-4 text-sm focus:ring-2 focus:ring-emerald-500 outline-none transition-all text-gray-900 dark:text-white/90 dark:[color-scheme:dark]">
                            </div>
                        </div>

                        <div class="pt-4 grid grid-cols-3 gap-4 border-t border-gray-300 dark:border-white/10 mt-6 pt-6">
                            <button type="button" onclick="resetToStep1()"
                                class="col-span-1 py-4 bg-gray-100 dark:bg-white/5 hover:bg-gray-200 dark:hover:bg-white/10 text-gray-700 dark:text-white font-black rounded-2xl transition-all uppercase tracking-tighter text-sm border border-gray-300 dark:border-transparent">Batal</button>
                            <button type="submit" id="btn-submit-biodata"
                                class="col-span-2 py-4 bg-emerald-500 hover:bg-emerald-600 text-white font-black rounded-2xl shadow-xl shadow-emerald-500/20 transition-all uppercase tracking-tighter text-sm">Simpan
                                & Lanjut</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- STEP 2: Konfirmasi & Form -->
            <div id="step-2" class="step-container space-y-6">
                <!-- Member Card -->
                <div class="glass p-6 rounded-2xl border-gray-200 dark:border-white/5 flex items-center gap-5">
                    <div id="member-photo"
                        class="w-16 h-16 bg-gray-200 dark:bg-white/10 rounded-2xl bg-cover bg-center shrink-0 border border-gray-300 dark:border-white/10">
                    </div>
                    <div>
                        <h3 id="member-name"
                            class="text-lg font-black uppercase tracking-tighter leading-none mb-1 text-gray-900 dark:text-white">
                            Nama
                            Anggota</h3>
                        <p id="member-code"
                            class="text-[10px] font-bold text-gray-600 dark:text-gray-400 uppercase tracking-widest mb-1">
                            KTA
                        </p>
                        <p id="member-group" class="text-xs font-bold text-emerald-400">Grup</p>
                    </div>
                </div>

                <!-- Form Section -->
                <form id="perizinan-form" class="space-y-6" onsubmit="event.preventDefault(); submitPerizinan();">
                    <input type="hidden" id="form-member-id" name="member_id">

                    <div class="space-y-3">
                        <label class="text-xs font-black uppercase tracking-widest text-gray-500 ml-1">Pilih Status
                            Kehadiran</label>
                        <div class="grid grid-cols-2 gap-4">
                            <label class="relative cursor-pointer group">
                                <input type="radio" name="status" value="izin" class="peer sr-only" required
                                    onchange="toggleForm(true)">
                                <div
                                    class="py-4 border-2 border-gray-300 dark:border-white/10 rounded-2xl text-center font-black uppercase tracking-tighter text-gray-500 peer-checked:border-amber-500 peer-checked:bg-amber-500/10 peer-checked:text-amber-500 transition-all">
                                    IZIN</div>
                            </label>
                            <label class="relative cursor-pointer group">
                                <input type="radio" name="status" value="sakit" class="peer sr-only" required
                                    onchange="toggleForm(true)">
                                <div
                                    class="py-4 border-2 border-gray-300 dark:border-white/10 rounded-2xl text-center font-black uppercase tracking-tighter text-gray-500 peer-checked:border-red-500 peer-checked:bg-red-500/10 peer-checked:text-red-500 transition-all">
                                    SAKIT</div>
                            </label>
                        </div>
                    </div>

                    <div id="optional-fields" class="space-y-6 hidden animate-in slide-in-from-top-4 duration-500">
                        @if($meeting->permission_opening_text || $meeting->permission_statement_text || $meeting->permission_closing_text)
                        <div class="bg-emerald-50/50 dark:bg-emerald-900/10 p-5 rounded-2xl border border-emerald-100 dark:border-emerald-800/30 text-sm text-gray-700 dark:text-gray-300 space-y-4 font-medium leading-relaxed">
                            <div id="pembuka-container" class="prose dark:prose-invert max-w-none text-sm"></div>
                            <div id="pernyataan-container" class="prose dark:prose-invert max-w-none text-sm"></div>
                        </div>
                        @endif

                        <div class="space-y-2">
                            <label class="text-xs font-black uppercase tracking-widest text-gray-500 ml-1">Keterangan
                                <span class="text-gray-400 dark:text-gray-500 font-normal">(Wajib
                                    diisi)</span></label>
                            <textarea name="notes" rows="3" required
                                class="w-full bg-white dark:bg-[#0d1511] border border-gray-300 dark:border-white/10 rounded-2xl p-4 text-sm focus:ring-2 focus:ring-emerald-500 outline-none transition-all text-gray-900 dark:text-white placeholder:text-gray-400 dark:placeholder:text-gray-600"
                                placeholder="Jelaskan alasan izin / sakit..."></textarea>
                        </div>

                        <div class="space-y-2">
                            <label class="text-xs font-black uppercase tracking-widest text-gray-500 ml-1 block">Bukti Pendukung <span class="text-gray-400 dark:text-gray-500 font-normal">(Opsional)</span></label>
                            <div class="flex items-center gap-4">
                                <label class="flex-1 flex flex-col items-center justify-center p-6 border-2 border-dashed border-gray-300 dark:border-white/10 rounded-2xl hover:border-emerald-500 dark:hover:border-emerald-500 transition-colors cursor-pointer group bg-white dark:bg-[#0d1511]">
                                    <div class="w-10 h-10 bg-emerald-500/10 text-emerald-500 rounded-xl flex items-center justify-center mb-2 group-hover:scale-110 transition-transform">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                                    </div>
                                    <span class="text-xs font-bold text-gray-500 dark:text-gray-400 group-hover:text-emerald-500 transition-colors">Unggah Foto (Max 2MB)</span>
                                    <input type="file" name="evidence" id="evidence-input" class="hidden" accept="image/*">
                                </label>
                                <div id="preview-container" class="hidden w-28 h-28 rounded-2xl border border-gray-200 dark:border-white/10 overflow-hidden relative shrink-0">
                                    <img id="evidence-preview" class="w-full h-full object-cover">
                                    <button type="button" onclick="clearEvidence()" class="absolute top-1 right-1 w-6 h-6 bg-red-500 text-white rounded-full flex items-center justify-center shadow-lg hover:bg-red-600 transition-colors">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"></path></svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        @if($meeting->permission_opening_text || $meeting->permission_statement_text || $meeting->permission_closing_text)
                        <div class="bg-emerald-50/50 dark:bg-emerald-900/10 p-5 rounded-2xl border border-emerald-100 dark:border-emerald-800/30 text-sm text-gray-700 dark:text-gray-300 font-medium leading-relaxed">
                            <div id="penutup-container" class="prose dark:prose-invert max-w-none text-sm"></div>
                        </div>
                        @endif
                    </div>

                    <div class="pt-4 grid grid-cols-3 gap-4 border-t border-gray-300 dark:border-white/10 mt-6 pt-6">
                        <button type="button" onclick="resetToStep1()"
                            class="col-span-1 py-4 bg-gray-100 dark:bg-white/5 hover:bg-gray-200 dark:hover:bg-white/10 text-gray-700 dark:text-white font-black rounded-2xl transition-all uppercase tracking-tighter text-sm border border-gray-300 dark:border-transparent">KEMBALI</button>

                        <button type="submit" id="btn-submit"
                            class="col-span-2 py-4 bg-emerald-500 hover:bg-emerald-600 text-white font-black rounded-2xl shadow-xl shadow-emerald-500/20 transition-all uppercase tracking-tighter">KIRIM
                            PERIZINAN</button>
                    </div>
            </div>
            </form>
            </div>
        @endif
    </main>

    <!-- Global Floating Feedback -->
    <div id="feedback"
        class="fixed bottom-8 left-1/2 -translate-x-1/2 z-[100] pointer-events-none hidden max-w-sm w-full px-4">
        <div id="feedback-card"
            class="bg-emerald-500 text-white p-5 rounded-2xl shadow-2xl flex items-center gap-4 border border-white/20 transform transition-all duration-500 scale-90 opacity-0">
            <div id="feedback-icon"
                class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center shrink-0 shadow-inner">
                <svg id="icon-success" class="w-6 h-6 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M5 13l4 4L19 7" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                <svg id="icon-warning" class="w-6 h-6 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M12 9v2m0 4h.01M3 12a9 9 0 1118 0 9 9 0 01-18 0z" stroke-width="2.5" />
                </svg>
            </div>
            <div>
                <h4 id="feedback-title" class="font-black text-base uppercase tracking-tighter leading-none mb-1">Status
                </h4>
                <p id="feedback-message" class="text-xs font-bold opacity-80 leading-snug"></p>
            </div>
        </div>
    </div>

    <script>
        @php
            $rawPembuka = $meeting->permission_opening_text ?? '';
            $rawPernyataan = $meeting->permission_statement_text ?? '';
            $rawPenutup = $meeting->permission_closing_text ?? '';

            $shortcodes = [
                '[nama-pertemuan]' => $meeting->name,
                '[hari-pertemuan]' => $meeting->meeting_date->locale('id')->dayName,
                '[tanggal-pertemuan]' => $meeting->meeting_date->format('d M Y'),
                '[nama-penyelenggara]' => $meeting->group->name,
                '[tingkatan-pertemuan]' => $meeting->group?->level?->name ?? '',
                '[grup-pertemuan]' => $meeting->group->name,
            ];

            $rawPembuka = str_replace(array_keys($shortcodes), array_values($shortcodes), $rawPembuka);
            $rawPernyataan = str_replace(array_keys($shortcodes), array_values($shortcodes), $rawPernyataan);
            $rawPenutup = str_replace(array_keys($shortcodes), array_values($shortcodes), $rawPenutup);
        @endphp

        const templatePembuka = `{!! addslashes($rawPembuka) !!}`;
        const templatePernyataan = `{!! addslashes($rawPernyataan) !!}`;
        const templatePenutup = `{!! addslashes($rawPenutup) !!}`;

        let html5QrCode;
        let isProcessingLookup = false;

        // Client-side image compression helper (Handles iPhone HEIC/JPEG/PNG & large files)
        function compressImageFile(file, maxDimension = 1200, quality = 0.7) {
            return new Promise((resolve) => {
                if (!file || !file.type.startsWith('image/')) {
                    return resolve(file);
                }
                const reader = new FileReader();
                reader.onerror = () => resolve(file);
                reader.onload = function (e) {
                    const img = new Image();
                    img.onerror = () => resolve(file);
                    img.onload = function () {
                        let w = img.width;
                        let h = img.height;
                        if (w > maxDimension || h > maxDimension) {
                            if (w > h) {
                                h = Math.round((h * maxDimension) / w);
                                w = maxDimension;
                            } else {
                                w = Math.round((w * maxDimension) / h);
                                h = maxDimension;
                            }
                        }
                        const canvas = document.createElement('canvas');
                        canvas.width = w;
                        canvas.height = h;
                        const ctx = canvas.getContext('2d');
                        ctx.drawImage(img, 0, 0, w, h);
                        canvas.toBlob((blob) => {
                            if (!blob) return resolve(file);
                            resolve(blob);
                        }, 'image/jpeg', quality);
                    };
                    img.src = e.target.result;
                };
                reader.readAsDataURL(file);
            });
        }

        let compressedEvidenceBlob = null;

        $(document).ready(function () {
            // Preview image logic with instant client-side compression
            $('#evidence-input').change(async function () {
                if (this.files && this.files[0]) {
                    const file = this.files[0];
                    try {
                        compressedEvidenceBlob = await compressImageFile(file, 1200, 0.7);
                        const previewUrl = URL.createObjectURL(compressedEvidenceBlob);
                        $('#evidence-preview').attr('src', previewUrl);
                        $('#preview-container').removeClass('hidden');
                    } catch (err) {
                        console.error('Compression error:', err);
                        compressedEvidenceBlob = file;
                    }
                }
            });

            // Initialize Select2
            $('#manual-group').select2({
                dropdownParent: $('#section-manual'),
                placeholder: 'Cari Kelompok...',
                allowClear: false,
                width: '100%',
                language: {
                    noResults: function () { return 'Tidak ditemukan.'; },
                    searching: function () { return 'Mencari...'; }
                }
            }).on('select2:select', function (e) {
                loadMembersByGroup(e.params.data.id);
            });

            $('#manual-member').select2({
                dropdownParent: $('#section-manual'),
                placeholder: 'Pilih Grup Terlebih Dahulu...',
                disabled: true,
                allowClear: false,
                width: '100%',
                language: {
                    noResults: function () { return 'Tidak ditemukan.'; },
                    searching: function () { return 'Mencari...'; }
                }
            }).on('select2:select', function (e) {
                $('#btn-lookup').prop('disabled', false);
            });
        });

        function clearEvidence() {
            $('#evidence-input').val('');
            compressedEvidenceBlob = null;
            $('#preview-container').addClass('hidden');
            $('#evidence-preview').attr('src', '');
        }

        function switchTab(tab) {
            if (tab === 'scan') {
                $('#tab-scan').removeClass('text-gray-500 hover:text-gray-900 dark:hover:text-white bg-transparent').addClass('bg-emerald-500 text-white shadow-lg');
                $('#tab-manual').removeClass('bg-emerald-500 text-white shadow-lg').addClass('text-gray-500 hover:text-gray-900 dark:hover:text-white bg-transparent');
                $('#section-scan').removeClass('hidden');
                $('#section-manual').addClass('hidden');
            } else {
                stopScanning();
                $('#tab-manual').removeClass('text-gray-500 hover:text-gray-900 dark:hover:text-white bg-transparent').addClass('bg-emerald-500 text-white shadow-lg');
                $('#tab-scan').removeClass('bg-emerald-500 text-white shadow-lg').addClass('text-gray-500 hover:text-gray-900 dark:hover:text-white bg-transparent');
                $('#section-manual').removeClass('hidden');
                $('#section-scan').addClass('hidden');
                setTimeout(() => $('#manual-group').select2('open'), 200);
            }
        }

        // --- SCANNER LOGIC ---
        function startScanning() {
            if (!window.isSecureContext && location.hostname !== "localhost") {
                alert('ERROR: Kamera hanya bisa diakses melalui HTTPS.');
                return;
            }

            $('#start-overlay').addClass('opacity-0 pointer-events-none scale-110');
            $('#scanner-line').removeClass('hidden');
            $('#btn-stop-camera').removeClass('hidden');

            html5QrCode = new Html5Qrcode("reader");
            const config = { fps: 30 };

            html5QrCode.start({ facingMode: "environment" }, config,
                (decodedText) => {
                    if (isProcessingLookup) return;
                    lookupCode(decodedText);
                },
                (errorMessage) => { }
            ).then(() => {
                const videoElement = document.querySelector('#reader video');
                if (videoElement && videoElement.srcObject) {
                    const track = videoElement.srcObject.getVideoTracks()[0];
                    if (track.getSettings().facingMode === 'user') {
                        $(videoElement).addClass('mirror-mode');
                    }
                }
            }).catch(err => {
                alert('Gagal akses kamera.');
                stopScanning();
            });
        }

        function stopScanning() {
            if (html5QrCode) {
                if (html5QrCode.isScanning) {
                    html5QrCode.stop().then(() => {
                        try { html5QrCode.clear(); } catch (e) {}
                        resetScannerUI();
                        html5QrCode = null;
                    }).catch(() => {
                        resetScannerUI();
                        html5QrCode = null;
                    });
                } else {
                    try { html5QrCode.clear(); } catch (e) {}
                    resetScannerUI();
                    html5QrCode = null;
                }
            } else {
                resetScannerUI();
            }

            const videoElement = document.querySelector('#reader video');
            if (videoElement && videoElement.srcObject) {
                videoElement.srcObject.getTracks().forEach(track => track.stop());
                videoElement.srcObject = null;
            }
        }

        function resetScannerUI() {
            $('#start-overlay').removeClass('opacity-0 pointer-events-none scale-110');
            $('#scanner-line').addClass('hidden');
            $('#btn-stop-camera').addClass('hidden');
        }

        // --- LOOKUP LOGIC ---
        function loadMembersByGroup(groupId) {
            const memberSelect = $('#manual-member');
            const btnLookup = $('#btn-lookup');

            memberSelect.empty().append('<option value="">Memuat...</option>');
            if (memberSelect.hasClass('select2-hidden-accessible')) {
                memberSelect.val(null).trigger('change');
            }
            memberSelect.prop('disabled', true);
            if (memberSelect.data('select2')) {
                memberSelect.select2('destroy');
            }
            memberSelect.select2({
                dropdownParent: $('#section-manual'),
                placeholder: 'Memuat...',
                disabled: true,
                allowClear: false,
                width: '100%',
                language: {
                    noResults: function () { return 'Tidak ditemukan.'; },
                    searching: function () { return 'Mencari...'; }
                }
            });
            btnLookup.prop('disabled', true);

            $.get('/self-permission/{{ $meeting->uid }}/members', { group_id: groupId }, function (res) {
                if (res.status === 'success') {
                    memberSelect.empty();
                    memberSelect.prop('disabled', false);
                    memberSelect.select2('destroy').select2({
                        dropdownParent: $('#section-manual'),
                        placeholder: 'Cari Anggota...',
                        allowClear: false,
                        width: '100%',
                        language: {
                            noResults: function () { return 'Tidak ditemukan.'; },
                            searching: function () { return 'Mencari...'; }
                        },
                        data: res.members.map(m => ({
                            id: m.id,
                            text: m.full_name,
                            code: m.member_code || '',
                            badge: m.badge || '',
                        })),
                        templateResult: function (data) {
                            if (!data.id) return data.text;
                            const badge = data.badge ? `<span class="inline-block text-[8px] font-black bg-blue-500/20 text-blue-400 px-1.5 py-0.5 rounded border border-blue-500/30 uppercase tracking-tighter ml-2">${data.badge}</span>` : '';
                            const code = data.code ? `<span class="text-gray-500 font-bold ml-1 text-[10px]">(${data.code})</span>` : '';
                            return $(`<span class="flex items-center">${data.text}${code}${badge}</span>`);
                        },
                        templateSelection: function (data) {
                            if (!data.id) return data.text;
                            const badge = data.badge ? ` [${data.badge}]` : '';
                            return data.text + badge;
                        }
                    }).on('select2:select', function (e) {
                        btnLookup.prop('disabled', false);
                    });
                }
            });
        }

        function lookupManual() {
            const memberId = $('#manual-member').val();
            if (!memberId || memberId === '') {
                showFeedback('warning', 'Pilih anggota terlebih dahulu.');
                return;
            }
            $('#btn-lookup').text('MENCARI...').attr('disabled', true);
            lookupCodeByData({ member_id: memberId }, true);
        }

        function lookupCode(code, isManual = false) {
            lookupCodeByData({ code: code }, isManual);
        }

        function lookupCodeByData(data, isManual = false) {
            isProcessingLookup = true;
            $.get('/self-permission/{{ $meeting->uid }}/lookup', data, function (res) {
                if (isManual) $('#btn-lookup').text('LANJUTKAN').attr('disabled', false);

                if (res.status === 'success') {
                    if (res.already_submitted) {
                        showFeedback('warning', res.message);
                        isProcessingLookup = false;
                        if (isManual) $('#manual-member').val(null).trigger('change');
                    } else {
                        // Go to step 2
                        setupStep2(res.member);
                    }
                } else if (res.status === 'incomplete_biodata') {
                    setupStep1c(res.member);
                } else {
                    showFeedback('error', res.message);
                    isProcessingLookup = false;
                    if (isManual) $('#manual-member').val('').change();
                }
            }).fail(function () {
                if (isManual) $('#btn-lookup').text('LANJUTKAN').attr('disabled', false);
                showFeedback('error', 'Terjadi kesalahan sistem.');
                isProcessingLookup = false;
            });
        }

        // --- BIODATA LOGIC ---
        function setupStep1c(member) {
            stopScanning();
            $('#step-1').removeClass('active');
            $('#step-2').removeClass('active');
            $('#step-1b').removeClass('active');
            $('#step-1c').addClass('active');

            $('#biodata-member-id').val(member.id);
            $('#biodata-full-name').val(member.full_name);
            $('#biodata-title').html(`Halo, <span class="text-emerald-500">${member.full_name}</span>! 👋`);

            const hasGender = Boolean(member.gender || member.has_gender);
            const hasBirthDate = Boolean(member.birth_date || member.has_birth_date);

            if (hasGender) {
                // Gender already recorded: hide gender input, make birth_date full-width
                $('#container-biodata-gender').addClass('hidden');
                $('#biodata-gender').prop('required', false).val(member.gender || '');
                $('#container-biodata-birth-date').removeClass('col-span-1').addClass('col-span-2');
                $('#biodata-desc').text('Tanggal lahir Anda belum tercatat. Mohon lengkapi tanggal lahir untuk memverifikasi data dan melanjutkan perizinan.');
            } else {
                // Gender not recorded: show both fields
                $('#container-biodata-gender').removeClass('hidden');
                $('#biodata-gender').prop('required', true).val('');
                $('#container-biodata-birth-date').removeClass('col-span-2').addClass('col-span-1');
                $('#biodata-desc').text('Biodata Anda belum lengkap. Silakan lengkapi jenis kelamin dan tanggal lahir untuk melanjutkan.');
            }

            $('#biodata-birth-date').val(member.birth_date || '');
            setTimeout(() => {
                if (hasGender) {
                    $('#biodata-birth-date').focus();
                } else {
                    $('#biodata-gender').focus();
                }
            }, 300);
        }

        function submitCompleteBiodata() {
            const form = document.getElementById('complete-biodata-form');
            const formData = new FormData(form);

            $('#btn-submit-biodata').text('MENYIMPAN...').prop('disabled', true);

            $.ajax({
                url: '/self-permission/{{ $meeting->uid }}/complete-biodata',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                success: function (res) {
                    if (res.status === 'success') {
                        showFeedback('success', res.message);
                        setupStep2(res.member);
                    } else {
                        showFeedback('error', res.message || 'Gagal menyimpan.');
                        $('#btn-submit-biodata').text('SIMPAN & LANJUT').prop('disabled', false);
                    }
                },
                error: function (xhr) {
                    let msg = 'Terjadi kesalahan sistem.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    }
                    showFeedback('error', msg);
                    $('#btn-submit-biodata').text('SIMPAN & LANJUT').prop('disabled', false);
                }
            });
        }

        // --- STEP 2 LOGIC ---
        function setupStep2(member) {
            stopScanning();

            // Fill data
            $('#form-member-id').val(member.id);
            $('#member-name').text(member.full_name);
            $('#member-code').text(member.member_code || '-');
            $('#member-group').text(member.group || '-');

            $('#surat-nama').text(member.full_name);
            $('#surat-kelompok').text(member.group || '-');

            // Parse Shortcodes
            $('#pembuka-container').html(templatePembuka.replace(/\[nama-anggota\]/g, member.full_name).replace(/\[grup-anggota\]/g, member.group || '-'));
            $('#pernyataan-container').html(templatePernyataan.replace(/\[nama-anggota\]/g, member.full_name).replace(/\[grup-anggota\]/g, member.group || '-'));
            $('#penutup-container').html(templatePenutup.replace(/\[nama-anggota\]/g, member.full_name).replace(/\[grup-anggota\]/g, member.group || '-'));

            if (member.photo_url) {
                $('#member-photo').css('background-image', `url(${member.photo_url})`).text('');
            } else {
                $('#member-photo').css('background-image', 'none').html('<svg class="w-8 h-8 m-auto mt-4 text-white/20" fill="currentColor" viewBox="0 0 24 24"><path d="M12 12a5 5 0 100-10 5 5 0 000 10zm0-2a3 3 0 110-6 3 3 0 010 6zm9 11a1 1 0 01-2 0c0-2.33-4.33-4-7-4s-7 1.67-7 4a1 1 0 01-2 0c0-2.9 5.33-6 9-6s9 3.1 9 6z"/></svg>');
            }

            // Switch view
            $('#step-1').removeClass('active');
            $('#step-1b').removeClass('active');
            $('#step-1c').removeClass('active');
            $('#step-2').addClass('active');
            isProcessingLookup = false;

            // Reset form
            $('#perizinan-form')[0].reset();
            $('input[name="status"]').prop('checked', false);
            toggleForm(false);
            clearEvidence();
        }

        function toggleForm(show) {
            if (show) {
                $('#optional-fields').removeClass('hidden');
            } else {
                $('#optional-fields').addClass('hidden');
            }
        }

        function resetToStep1() {
            $('#step-2').removeClass('active');
            $('#step-1b').removeClass('active');
            $('#step-1c').removeClass('active');
            $('#step-1').addClass('active');
            $('#manual-group').val(null).trigger('change');
            $('#manual-member').empty().append('<option value="">Pilih Grup Terlebih Dahulu...</option>').prop('disabled', true);
            if ($('#manual-member').data('select2')) {
                $('#manual-member').select2('destroy');
            }
            $('#manual-member').select2({
                dropdownParent: $('#section-manual'),
                placeholder: 'Pilih Grup Terlebih Dahulu...',
                disabled: true,
                allowClear: false,
                width: '100%',
                language: {
                    noResults: function () { return 'Tidak ditemukan.'; },
                    searching: function () { return 'Mencari...'; }
                }
            });
            $('#btn-lookup').prop('disabled', true);
            $('#btn-submit').text('KIRIM PERIZINAN').prop('disabled', false).removeClass('opacity-75 cursor-not-allowed');
        }

        // --- TEMPORARY REGISTRATION LOGIC ---
        function goToStep1b() {
            stopScanning();
            $('#step-1').removeClass('active');
            $('#step-2').removeClass('active');
            $('#step-1b').addClass('active');

            // Focus on first input
            setTimeout(() => $('#register-temporary-form input[name="full_name"]').focus(), 100);
        }

        function submitTemporaryRegistration() {
            const form = document.getElementById('register-temporary-form');
            const formData = new FormData(form);

            $('#btn-submit-temp').text('MENDAFTAR...').attr('disabled', true).addClass('opacity-75');

            $.ajax({
                url: '/self-permission/{{ $meeting->uid }}/register-temporary',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                success: function (res) {
                    if (res.status === 'success') {
                        showFeedback('success', 'Pendaftaran instan berhasil.');
                        setupStep2(res.member);
                    } else {
                        showFeedback('error', res.message || 'Pendaftaran gagal.');
                    }
                },
                error: function (xhr) {
                    const msg = xhr.responseJSON ? (xhr.responseJSON.message || 'Data tidak valid.') : 'Terjadi kesalahan sistem.';
                    showFeedback('error', msg);
                },
                complete: function () {
                    $('#btn-submit-temp').text('DAFTAR & LANJUT').attr('disabled', false).removeClass('opacity-75');
                }
            });
        }

        // --- FORM SUBMIT LOGIC ---
        function submitPerizinan() {
            const form = document.getElementById('perizinan-form');
            const formData = new FormData(form);

            if (!formData.get('status')) {
                showFeedback('warning', 'Pilih status IZIN atau SAKIT.');
                return;
            }

            if (compressedEvidenceBlob) {
                formData.set('evidence', compressedEvidenceBlob, 'evidence.jpg');
            }

            $('#btn-submit').text('MEMPROSES...').attr('disabled', true).addClass('opacity-75');

            $.ajax({
                url: '/self-permission/{{ $meeting->uid }}/store',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                success: function (res) {
                    showFeedback(res.status, res.message);
                    if (res.status === 'success') {
                        // Success - lock button permanently during redirect window
                        $('#btn-submit').text('TERSIMPAN, MENGALIHKAN...').prop('disabled', true).addClass('opacity-75 cursor-not-allowed');
                        setTimeout(() => resetToStep1(), 3000);
                    } else {
                        $('#btn-submit').text('KIRIM PERIZINAN').prop('disabled', false).removeClass('opacity-75 cursor-not-allowed');
                    }
                },
                error: function (xhr) {
                    const msg = xhr.responseJSON ? (xhr.responseJSON.message || 'Data tidak valid.') : 'Terjadi kesalahan sistem.';
                    showFeedback('error', msg);
                    $('#btn-submit').text('KIRIM PERIZINAN').prop('disabled', false).removeClass('opacity-75 cursor-not-allowed');
                }
            });
        }

        window.addEventListener('pagehide', function () {
            stopScanning();
        });

        // --- FEEDBACK LOGIC ---
        function showFeedback(status, message) {
            const colors = { success: 'bg-emerald-500', error: 'bg-rose-500', warning: 'bg-amber-500' };
            const titles = { success: 'BERHASIL', error: 'GAGAL', warning: 'PERHATIAN' };

            $('#feedback').removeClass('hidden');
            $('#feedback-card').attr('class', colors[status] + ' text-white p-5 rounded-2xl shadow-2xl flex items-center gap-4 border border-white/20 transform transition-all duration-500');

            $('#feedback-title').text(titles[status]);
            $('#feedback-message').text(message);

            if (status === 'success') {
                $('#icon-success').removeClass('hidden');
                $('#icon-warning').addClass('hidden');
            } else {
                $('#icon-success').addClass('hidden');
                $('#icon-warning').removeClass('hidden');
            }

            setTimeout(() => { $('#feedback-card').removeClass('scale-90 opacity-0').addClass('scale-100 opacity-100'); }, 10);
            setTimeout(() => {
                $('#feedback-card').removeClass('scale-100 opacity-100').addClass('scale-90 opacity-0');
                setTimeout(() => { $('#feedback').addClass('hidden'); }, 500);
            }, 3000);
        }
    </script>

    <style>
        .prose pre {
            white-space: pre-wrap !important;
            word-break: break-word !important;
            overflow-wrap: break-word !important;
            max-width: 100%;
        }

        .prose img {
            border-radius: 0.5rem;
            max-width: 100%;
            height: auto;
        }
    </style>
</body>

</html>