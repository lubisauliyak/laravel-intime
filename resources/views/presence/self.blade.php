<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Presensi: {{ $meeting->name }}</title>
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

        body { font-family: 'Manrope', sans-serif; }

        .glass { background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(10px); border: 1px solid rgba(0, 0, 0, 0.1); }
        .dark .glass { background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); }

        .scan-line { animation: scan 2s linear infinite; }
        @keyframes scan { 0% { top: 0; } 100% { top: 100%; } }

        #reader { border: none !important; }
        #reader video {
            object-fit: cover !important;
            width: 100% !important;
            height: 100% !important;
            border-radius: 1rem;
        }

        .mirror-mode { transform: scaleX(-1); }
        #reader__scan_region { background: transparent !important; }
        #reader__dashboard { display: none !important; }

        .step-container { display: none; }
        .step-container.active { display: block; animation: fade-in 0.5s ease-out forwards; }
        @keyframes fade-in {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        #camera-preview video {
            object-fit: cover;
            width: 100%;
            height: 100%;
            border-radius: 1rem;
            transform: scaleX(-1);
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
        .select2-container--default .select2-results > .select2-results__options {
            max-height: 240px !important;
        }
        .select2-container--default .select2-results__option[aria-selected=true] {
            background: rgba(16, 185, 129, 0.1) !important;
        }
        .dark .select2-container--default .select2-results__option[aria-selected=true] {
            background: rgba(16, 185, 129, 0.15) !important;
        }
    </style>
</head>

<body class="bg-gray-50 dark:bg-[#0d1511] text-gray-900 dark:text-white min-h-screen overflow-x-hidden flex flex-col transition-colors duration-300">
    <nav class="p-4 md:p-6 flex items-center justify-between border-b border-gray-200 dark:border-white/5 bg-white/80 dark:bg-[#0d1511]/80 backdrop-blur-md sticky top-0 z-50 transition-colors duration-300">
        <div class="flex items-center gap-3 md:gap-4 overflow-hidden">
            <div class="truncate">
                <h1 class="text-base md:text-lg font-extrabold tracking-tight truncate">{{ $meeting->name }}</h1>
                <p class="text-[9px] md:text-[10px] text-gray-500 uppercase tracking-widest truncate">
                    {{ $meeting->group?->name }} • {{ $meeting->meeting_date->format('d M Y') }} •
                    {{ $meeting->start_time?->format('H:i') ?? '-' }}-{{ $meeting->end_time?->format('H:i') ?? '-' }}
                </p>
            </div>
        </div>
        <div class="flex items-center gap-2 md:gap-3 shrink-0">
            @if($isExpired)
                <div class="flex items-center gap-2 px-3 py-1 md:px-4 md:py-1.5 bg-rose-500/10 border border-rose-500/20 rounded-full">
                    <div class="w-1.5 h-1.5 rounded-full bg-rose-500"></div>
                    <span class="text-[9px] md:text-[10px] font-black text-rose-400 uppercase tracking-widest hidden sm:inline">Sesi Berakhir</span>
                </div>
            @elseif(!$isCheckinOpen)
                <div class="flex items-center gap-2 px-3 py-1 md:px-4 md:py-1.5 bg-amber-500/10 border border-amber-500/20 rounded-full text-nowrap">
                    <div class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></div>
                    <span class="text-[9px] md:text-[10px] font-black text-amber-400 uppercase tracking-widest hidden sm:inline">Belum Dibuka</span>
                </div>
            @else
                <div class="flex items-center gap-2 px-3 py-1 md:px-4 md:py-1.5 bg-emerald-500/10 border border-emerald-500/20 rounded-full text-nowrap">
                    <div class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></div>
                    <span class="text-[9px] md:text-[10px] font-black text-emerald-400 uppercase tracking-widest hidden sm:inline">Menerima Presensi</span>
                </div>
            @endif
            <div class="ml-2 border-l border-gray-200 dark:border-gray-800 pl-3">
                @include('components.theme-switcher')
            </div>
        </div>
    </nav>

    <main class="max-w-2xl mx-auto w-full p-4 md:p-8 flex-1 flex flex-col justify-center">
        @if($isExpired)
            <div class="glass p-10 rounded-2xl relative overflow-hidden text-center shadow-xl md:shadow-2xl animate-in fade-in duration-1000 border border-gray-200 dark:border-white/5">
                <div class="relative w-24 h-24 mx-auto mb-8 bg-rose-100 dark:bg-rose-500/20 rounded-[2rem] flex items-center justify-center shadow-lg border border-rose-200 dark:border-rose-500/30">
                    <svg class="w-12 h-12 text-rose-500 dark:text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <h3 class="text-2xl font-black mb-3 tracking-tighter uppercase text-rose-600 dark:text-rose-400">Sesi Berakhir</h3>
                <p class="text-gray-600 dark:text-gray-400 text-sm mb-6 leading-relaxed font-medium">Batas waktu untuk melakukan presensi pada pertemuan ini telah berakhir.</p>
            </div>
        @elseif(!$isCheckinOpen)
            <div class="glass p-10 rounded-2xl relative overflow-hidden text-center shadow-xl md:shadow-2xl animate-in fade-in duration-1000 border border-gray-200 dark:border-white/5">
                <div class="relative w-24 h-24 mx-auto mb-8 bg-amber-100 dark:bg-amber-500/20 rounded-[2rem] flex items-center justify-center shadow-lg border border-amber-200 dark:border-amber-500/30">
                    <svg class="w-12 h-12 text-amber-500 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <h3 class="text-2xl font-black mb-3 tracking-tighter uppercase text-amber-600 dark:text-amber-400">Belum Dibuka</h3>
                <p class="text-gray-600 dark:text-gray-400 text-sm mb-6 leading-relaxed font-medium">Jam presensi untuk pertemuan ini belum dibuka. Silakan tunggu.</p>
            </div>
        @else
            <!-- STEP 1: Identifikasi Anggota -->
            <div id="step-1" class="step-container active space-y-8">
                <div class="text-center mb-8">
                    <h2 class="text-3xl font-black tracking-tighter mb-2 text-gray-900 dark:text-white">Presensi Mandiri</h2>
                    <p class="text-gray-600 dark:text-gray-400 text-sm font-medium">Scan QR KTA atau masukkan nomor KTA untuk melakukan presensi.</p>
                </div>

                <div class="flex p-1 bg-white border border-gray-200 dark:bg-white/5 rounded-2xl dark:border-white/5 shadow-sm">
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

                <div id="section-scan" class="relative group">
                    <div class="absolute -inset-1 bg-gradient-to-r from-emerald-500 to-blue-500 rounded-2xl blur opacity-20 group-hover:opacity-40 transition duration-1000"></div>
                    <div id="reader-container" class="relative bg-gray-100 dark:bg-black rounded-2xl aspect-square overflow-hidden border-8 border-gray-200 dark:border-white/5 shadow-2xl">
                        <div id="reader" style="width: 100%; height: 100%;"></div>
                        <div class="absolute inset-0 pointer-events-none z-10 flex flex-col justify-between p-6 md:p-10">
                            <div class="flex justify-between">
                                <div class="w-10 h-10 md:w-14 md:h-14 border-t-4 border-l-4 border-emerald-500 rounded-tl-2xl"></div>
                                <div class="w-10 h-10 md:w-14 md:h-14 border-t-4 border-r-4 border-emerald-500 rounded-tr-2xl"></div>
                            </div>
                            <div id="scanner-line" class="absolute left-6 right-6 md:left-10 md:right-10 h-0.5 bg-emerald-500/50 shadow-[0_0_20px_rgba(16,185,129,0.8)] scan-line hidden"></div>
                            <div class="flex justify-between">
                                <div class="w-10 h-10 md:w-14 md:h-14 border-b-4 border-l-4 border-emerald-500 rounded-bl-2xl"></div>
                                <div class="w-10 h-10 md:w-14 md:h-14 border-b-4 border-r-4 border-emerald-500 rounded-br-2xl"></div>
                            </div>
                        </div>
                        <button id="btn-stop-camera" onclick="stopScanning()"
                            class="absolute top-8 right-8 z-30 px-6 py-2.5 bg-rose-500/90 hover:bg-rose-600 text-white font-black rounded-2xl shadow-xl transition-all active:scale-95 uppercase tracking-tighter text-xs hidden backdrop-blur-md border border-white/10">Tutup Kamera</button>
                        <div id="start-overlay"
                            class="absolute inset-0 bg-white/90 dark:bg-[#0d1511]/90 backdrop-blur-xl flex flex-col items-center justify-center p-12 text-center z-20 transition-all duration-700">
                            <div class="relative mb-8">
                                <div class="absolute -inset-6 bg-emerald-500/20 blur-2xl rounded-full animate-pulse"></div>
                                <div class="relative w-24 h-24 bg-emerald-500 rounded-[2rem] flex items-center justify-center shadow-2xl shadow-emerald-500/40 rotate-12 group-hover:rotate-0 transition-transform duration-500">
                                    <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </div>
                            </div>
                            <h3 class="text-2xl font-black mb-3 tracking-tighter uppercase text-gray-900 dark:text-white">Scanner Siap</h3>
                            <p class="text-gray-600 dark:text-gray-400 text-sm mb-8 max-w-xs leading-relaxed font-medium">Klik tombol di bawah untuk mulai memindai Kode QR.</p>
                            <button onclick="startScanning()"
                                class="w-full px-8 py-4 bg-gray-900 dark:bg-white text-white dark:text-black font-black rounded-2xl hover:bg-emerald-500 dark:hover:bg-emerald-500 hover:text-white transition-all shadow-xl active:scale-95 uppercase tracking-tighter text-base">AKTIFKAN KAMERA</button>
                        </div>
                    </div>
                </div>

                <div id="section-manual" class="hidden glass p-8 rounded-2xl border-gray-200 dark:border-white/5 relative">
                    <div class="space-y-4">
                        <label class="text-xs font-black uppercase tracking-widest text-gray-500 ml-1">Asal Kelompok</label>
                        <select id="manual-group" class="w-full">
                            <option value="" selected>Pilih Grup...</option>
                            @foreach($groups as $grp)
                                <option value="{{ $grp->id }}">{{ $grp->full_name }}</option>
                            @endforeach
                        </select>

                        <label class="text-xs font-black uppercase tracking-widest text-gray-500 ml-1 block">Nama Anggota</label>
                        <select id="manual-member" class="w-full" disabled>
                            <option value="" selected>Pilih Grup Terlebih Dahulu...</option>
                        </select>

                        <button type="button" onclick="lookupManual(event)" id="btn-lookup"
                            class="w-full py-4 bg-emerald-500 hover:bg-emerald-600 text-white font-black rounded-2xl transition-all shadow-xl shadow-emerald-500/20 uppercase tracking-tighter disabled:opacity-50 disabled:cursor-not-allowed" disabled>LANJUTKAN</button>
                    </div>
                    <div class="mt-6 text-center border-t border-white/10 pt-6">
                        <button onclick="goToStep1b()"
                            class="text-xs font-bold text-emerald-400 hover:text-emerald-300 transition-colors uppercase tracking-wider">Belum punya KTA / Belum Terdaftar?</button>
                    </div>
                </div>
            </div>

            <!-- STEP 1b: Pendaftaran Sementara -->
            <div id="step-1b" class="step-container space-y-8">
                <div class="text-center mb-8">
                    <h2 class="text-3xl font-black tracking-tighter mb-2 text-gray-900 dark:text-white">Pendaftaran Instan</h2>
                    <p class="text-gray-600 dark:text-gray-400 text-sm font-medium">Isi form ini untuk melanjutkan jika Anda belum terdaftar / belum memiliki KTA.</p>
                </div>

                <div class="glass p-8 rounded-2xl border-gray-200 dark:border-white/5 relative">
                    <form id="register-temporary-form" class="space-y-6"
                        onsubmit="event.preventDefault(); submitTemporaryRegistration();">
                        <div class="space-y-2">
                            <label class="text-xs font-black uppercase tracking-widest text-gray-500 ml-1">Nama Lengkap <span class="text-rose-500">*</span></label>
                            <input type="text" name="full_name" required
                                class="w-full bg-white dark:bg-white/5 border border-gray-300 dark:border-white/10 rounded-xl p-4 text-sm focus:ring-2 focus:ring-emerald-500 outline-none transition-all text-gray-900 dark:text-white placeholder:text-gray-400 dark:placeholder:text-white/30"
                                placeholder="Masukkan nama lengkap">
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <label class="text-xs font-black uppercase tracking-widest text-gray-500 ml-1">Jenis Kelamin <span class="text-rose-500">*</span></label>
                                <select name="gender" required
                                    class="w-full bg-white dark:bg-[#0d1511] border border-gray-300 dark:border-white/10 rounded-xl p-4 text-sm focus:ring-2 focus:ring-emerald-500 outline-none transition-all text-gray-900 dark:text-white/90 appearance-none font-medium">
                                    <option value="" disabled selected>Pilih...</option>
                                    <option value="male">Laki-laki</option>
                                    <option value="female">Perempuan</option>
                                </select>
                            </div>
                            <div class="space-y-2">
                                <label class="text-xs font-black uppercase tracking-widest text-gray-500 ml-1">Tanggal Lahir <span class="text-rose-500">*</span></label>
                                <input type="date" name="birth_date" required
                                    class="w-full bg-white dark:bg-white/5 border border-gray-300 dark:border-white/10 rounded-xl p-4 text-sm focus:ring-2 focus:ring-emerald-500 outline-none transition-all text-gray-900 dark:text-white dark:[color-scheme:dark]">
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label class="text-xs font-black uppercase tracking-widest text-gray-500 ml-1">Asal Kelompok <span class="text-rose-500">*</span></label>
                            <select name="group_id" required
                                class="w-full bg-white dark:bg-[#0d1511] border border-gray-300 dark:border-white/10 rounded-xl p-4 text-sm focus:ring-2 focus:ring-emerald-500 outline-none transition-all text-gray-900 dark:text-white/90 appearance-none font-medium">
                                <option value="" disabled selected>Pilih Grup...</option>
                                @foreach($groups as $grp)
                                    <option value="{{ $grp->id }}">{{ $grp->full_name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="pt-4 grid grid-cols-3 gap-4 border-t border-gray-200 dark:border-white/10 mt-6 pt-6">
                            <button type="button" onclick="resetToStep1()"
                                class="col-span-1 py-4 bg-gray-100 dark:bg-white/5 hover:bg-gray-200 dark:hover:bg-white/10 text-gray-700 dark:text-white font-black rounded-2xl transition-all uppercase tracking-tighter text-sm border border-gray-300 dark:border-transparent">Kembali</button>
                            <button type="submit" id="btn-submit-temp"
                                class="col-span-2 py-4 bg-emerald-500 hover:bg-emerald-600 text-white font-black rounded-2xl shadow-xl shadow-emerald-500/20 transition-all uppercase tracking-tighter text-sm">Daftar & Lanjut</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- STEP 1.5: Lengkapi Biodata -->
            <div id="step-bio" class="step-container space-y-6">
                <div class="glass p-6 md:p-8 rounded-2xl border-gray-200 dark:border-white/5 relative">
                    <div class="text-center mb-6">
                        <div class="relative w-16 h-16 mx-auto mb-4 bg-amber-100 dark:bg-amber-500/20 rounded-[1.5rem] flex items-center justify-center border border-amber-200 dark:border-amber-500/30">
                            <svg class="w-8 h-8 text-amber-500 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-black tracking-tighter mb-1 text-gray-900 dark:text-white">Lengkapi Biodata</h3>
                        <p class="text-gray-600 dark:text-gray-400 text-xs font-medium">Isi data berikut untuk melanjutkan presensi.</p>
                    </div>

                    <form id="biodata-form" class="space-y-4" onsubmit="event.preventDefault(); submitBiodata();">
                        <input type="hidden" id="bio-member-id" name="member_id">
                        <input type="hidden" id="bio-has-nick-name" name="has_nick_name">
                        <input type="hidden" id="bio-has-birth-date" name="has_birth_date">

                        <div id="bio-field-nick-name">
                            <label class="text-xs font-black uppercase tracking-widest text-gray-500 ml-1">Nama Panggilan</label>
                            <input type="text" id="bio-nick-name" maxlength="100"
                                class="w-full bg-white dark:bg-[#0d1511] border border-gray-300 dark:border-white/10 rounded-xl p-4 text-sm focus:ring-2 focus:ring-emerald-500 outline-none transition-all text-gray-900 dark:text-white placeholder:text-gray-400 dark:placeholder:text-white/30 font-bold uppercase"
                                placeholder="Nama panggilan Anda"
                                oninput="this.value = this.value.toUpperCase()">
                        </div>

                        <div id="bio-field-birth-date">
                            <label class="text-xs font-black uppercase tracking-widest text-gray-500 ml-1">Tanggal Lahir</label>
                            <input type="date" id="bio-birth-date"
                                class="w-full bg-white dark:bg-[#0d1511] border border-gray-300 dark:border-white/10 rounded-xl p-4 text-sm focus:ring-2 focus:ring-emerald-500 outline-none transition-all text-gray-900 dark:text-white font-bold dark:[color-scheme:dark]">
                        </div>

                        <div class="pt-4 grid grid-cols-3 gap-4">
                            <button type="button" onclick="resetFromBio()"
                                class="col-span-1 py-4 bg-gray-100 dark:bg-white/5 hover:bg-gray-200 dark:hover:bg-white/10 text-gray-700 dark:text-white font-black rounded-2xl transition-all uppercase tracking-tighter text-sm border border-gray-300 dark:border-transparent">KEMBALI</button>
                            <button type="submit" id="btn-bio-submit"
                                class="col-span-2 py-4 bg-emerald-500 hover:bg-emerald-600 text-white font-black rounded-2xl shadow-xl shadow-emerald-500/20 transition-all uppercase tracking-tighter">SIMPAN & LANJUTKAN</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- STEP 2: Konfirmasi Data & Selfie -->
            <div id="step-2" class="step-container space-y-6">
                <div class="glass p-6 rounded-2xl border-gray-200 dark:border-white/5 flex items-center gap-5">
                    <div id="member-photo"
                        class="w-16 h-16 bg-gray-200 dark:bg-white/10 rounded-2xl bg-cover bg-center shrink-0 border border-gray-300 dark:border-white/10"></div>
                    <div>
                        <h3 id="member-name" class="text-lg font-black uppercase tracking-tighter leading-none mb-1 text-gray-900 dark:text-white">Nama Anggota</h3>
                        <p id="member-code" class="text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-1">KTA</p>
                        <p id="member-group" class="text-xs font-bold text-emerald-600 dark:text-emerald-400">Grup</p>
                    </div>
                </div>

                <form id="presence-form" class="space-y-6" onsubmit="event.preventDefault(); submitPresence();">
                    <input type="hidden" id="form-member-id" name="member_id">

                    <div class="glass p-6 md:p-8 rounded-2xl border-gray-200 dark:border-white/5 relative">
                        <h4 class="text-xs font-black uppercase tracking-widest text-gray-500 mb-4">Ambil Selfie / Upload Foto</h4>

                        <div id="camera-section" class="relative">
                            <div id="camera-preview" class="relative bg-gray-100 dark:bg-black rounded-2xl overflow-hidden aspect-[4/3] border border-gray-300 dark:border-white/10 hidden">
                                <video id="video" autoplay playsinline class="absolute inset-0 w-full h-full object-cover"></video>
                                <img id="selfie-preview" class="hidden absolute inset-0 w-full h-full object-cover">
                                <canvas id="canvas" class="hidden"></canvas>
                                <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                                    <div class="w-64 h-64 md:w-72 md:h-72 border-2 border-dashed border-white/40 dark:border-white/20 rounded-2xl opacity-50"></div>
                                </div>
                            </div>
                            <div id="camera-placeholder"
                                class="bg-gray-100 dark:bg-white/5 rounded-2xl aspect-[4/3] flex flex-col items-center justify-center border border-gray-300 dark:border-white/10 cursor-pointer hover:bg-gray-200 dark:hover:bg-white/10 transition-all"
                                onclick="document.getElementById('upload-input').click()">
                                <svg class="w-16 h-16 text-gray-400 dark:text-gray-500 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                                <p class="text-sm font-bold text-gray-500">Klik untuk upload foto</p>
                                <p class="text-[10px] text-gray-500 dark:text-gray-600 mt-1">atau gunakan kamera</p>
                            </div>
                        </div>

                        <div class="flex gap-3 mt-4">
                            <button type="button" id="btn-camera" onclick="toggleCamera()"
                                class="flex-1 py-3 bg-gray-200 dark:bg-white/10 hover:bg-gray-300 dark:hover:bg-white/20 text-gray-700 dark:text-white font-black rounded-xl transition-all uppercase tracking-tighter text-xs">Buka Kamera</button>
                            <button type="button" id="btn-capture" onclick="captureSelfie()"
                                class="flex-1 py-3 bg-emerald-500 hover:bg-emerald-600 text-white font-black rounded-xl transition-all uppercase tracking-tighter text-xs hidden">Ambil Foto</button>
                            <button type="button" id="btn-upload" onclick="document.getElementById('upload-input').click()"
                                class="flex-1 py-3 bg-gray-200 dark:bg-white/10 hover:bg-gray-300 dark:hover:bg-white/20 text-gray-700 dark:text-white font-black rounded-xl transition-all uppercase tracking-tighter text-xs">Upload File</button>
                        </div>
                        <input type="file" id="upload-input" accept="image/*" capture="user" class="hidden" onchange="handleUpload(event)">

                        <div class="space-y-2 mt-6 pt-6 border-t border-gray-200 dark:border-white/5">
                            <label class="text-xs font-black uppercase tracking-widest text-gray-500 ml-1">Keterangan <span class="text-gray-400 dark:text-gray-500 font-normal">(Opsional)</span></label>
                            <textarea name="notes" rows="2"
                                class="w-full bg-white dark:bg-[#0d1511] border border-gray-300 dark:border-white/10 rounded-2xl p-4 text-sm focus:ring-2 focus:ring-emerald-500 outline-none transition-all text-gray-900 dark:text-white placeholder:text-gray-400 dark:placeholder:text-gray-600"
                                placeholder="Catatan tambahan..."></textarea>
                        </div>
                    </div>

                    <div class="pt-4 grid grid-cols-3 gap-4">
                        <button type="button" onclick="resetToStep1()"
                            class="col-span-1 py-4 bg-gray-200 dark:bg-white/5 hover:bg-gray-300 dark:hover:bg-white/10 text-gray-700 dark:text-white font-black rounded-2xl transition-all uppercase tracking-tighter text-sm">KEMBALI</button>
                        <button type="submit" id="btn-submit"
                            class="col-span-2 py-4 bg-emerald-500 hover:bg-emerald-600 text-white font-black rounded-2xl shadow-xl shadow-emerald-500/20 transition-all uppercase tracking-tighter">KIRIM PRESENSI</button>
                    </div>
                </form>
            </div>
        @endif
    </main>

    <div id="feedback" class="fixed bottom-8 left-1/2 -translate-x-1/2 z-[100] pointer-events-none hidden max-w-sm w-full px-4">
        <div id="feedback-card"
            class="bg-emerald-500 text-white p-5 rounded-2xl shadow-2xl flex items-center gap-4 border border-white/20 transform transition-all duration-500 scale-90 opacity-0">
            <div id="feedback-icon" class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center shrink-0 shadow-inner">
                <svg id="icon-success" class="w-6 h-6 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M5 13l4 4L19 7" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                <svg id="icon-warning" class="w-6 h-6 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M12 9v2m0 4h.01M3 12a9 9 0 1118 0 9 9 0 01-18 0z" stroke-width="2.5" />
                </svg>
            </div>
            <div>
                <h4 id="feedback-title" class="font-black text-base uppercase tracking-tighter leading-none mb-1">Status</h4>
                <p id="feedback-message" class="text-xs font-bold opacity-80 leading-snug"></p>
            </div>
        </div>
    </div>

    <script>
        let html5QrCode;
        let isProcessingLookup = false;
        let stream = null;
        let capturedSelfie = null;
        let isManual = false;

        $(document).ready(function () {
            $('#manual-group').select2({
                dropdownParent: $('#section-manual'),
                placeholder: 'Cari Kelompok...',
                allowClear: false,
                width: '100%',
                language: {
                    noResults: function() { return 'Tidak ditemukan.'; },
                    searching: function() { return 'Mencari...'; }
                }
            }).on('select2:select', function(e) {
                loadMembersByGroup(e.params.data.id);
            });

            $('#manual-member').select2({
                dropdownParent: $('#section-manual'),
                placeholder: 'Pilih Grup Terlebih Dahulu...',
                disabled: true,
                allowClear: false,
                width: '100%',
                language: {
                    noResults: function() { return 'Tidak ditemukan.'; },
                    searching: function() { return 'Mencari...'; }
                }
            }).on('select2:select', function(e) {
                $('#btn-lookup').prop('disabled', false);
            });
        });

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

        function startScanning() {
            if (!window.isSecureContext && location.hostname !== "localhost") {
                alert('ERROR: Kamera hanya bisa diakses melalui HTTPS.');
                return;
            }
            $('#start-overlay').addClass('opacity-0 pointer-events-none scale-110');
            $('#scanner-line').removeClass('hidden');
            $('#btn-stop-camera').removeClass('hidden');

            html5QrCode = new Html5Qrcode("reader");
            html5QrCode.start({ facingMode: "environment" }, { fps: 30 },
                (decodedText) => {
                    if (isProcessingLookup) return;
                    lookupCode(decodedText);
                },
                () => {}
            ).then(() => {
                const videoElement = document.querySelector('#reader video');
                if (videoElement && videoElement.srcObject) {
                    const track = videoElement.srcObject.getVideoTracks()[0];
                    if (track.getSettings().facingMode === 'user') {
                        $(videoElement).addClass('mirror-mode');
                    }
                }
            }).catch(() => {
                alert('Gagal akses kamera.');
                stopScanning();
            });
        }

        function stopScanning() {
            if (html5QrCode) {
                try {
                    html5QrCode.stop().then(() => {
                        try { html5QrCode.clear(); } catch (e) {}
                        resetScannerUI();
                        html5QrCode = null;
                    }).catch(() => {
                        resetScannerUI();
                        html5QrCode = null;
                    });
                } catch (e) {
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
                    noResults: function() { return 'Tidak ditemukan.'; },
                    searching: function() { return 'Mencari...'; }
                }
            });
            btnLookup.prop('disabled', true);

            $.get('/self-presence/{{ $meeting->uid }}/members', { group_id: groupId }, function(res) {
                if (res.status === 'success') {
                    memberSelect.empty();
                    memberSelect.prop('disabled', false);
                    memberSelect.select2('destroy').select2({
                        dropdownParent: $('#section-manual'),
                        placeholder: 'Cari Anggota...',
                        allowClear: false,
                        width: '100%',
                        language: {
                            noResults: function() { return 'Tidak ditemukan.'; },
                            searching: function() { return 'Mencari...'; }
                        },
                        data: res.members.map(m => ({
                            id: m.id,
                            text: m.full_name,
                            code: m.member_code || '',
                            badge: m.badge || '',
                        })),
                        templateResult: function(data) {
                            if (!data.id) return data.text;
                            const badge = data.badge ? `<span class="inline-block text-[8px] font-black bg-blue-500/20 text-blue-400 px-1.5 py-0.5 rounded border border-blue-500/30 uppercase tracking-tighter ml-2">${data.badge}</span>` : '';
                            const code = data.code ? `<span class="text-gray-500 font-bold ml-1 text-[10px]">(${data.code})</span>` : '';
                            return $(`<span class="flex items-center">${data.text}${code}${badge}</span>`);
                        },
                        templateSelection: function(data) {
                            if (!data.id) return data.text;
                            const badge = data.badge ? ` [${data.badge}]` : '';
                            return data.text + badge;
                        }
                    }).on('select2:select', function(e) {
                        btnLookup.prop('disabled', false);
                    });
                }
            });
        }

        function lookupManual(event) {
            if (event) event.preventDefault();
            const memberId = $('#manual-member').val();
            if (!memberId || memberId === '') {
                showFeedback('warning', 'Pilih anggota terlebih dahulu.');
                return;
            }
            $('#btn-lookup').text('MEMPROSES...').attr('disabled', true);
            isManual = true;
            lookupCodeByData({ member_id: memberId }, true);
        }

        function lookupCode(code, isManualParam = false) {
            lookupCodeByData({ code: code }, isManualParam);
        }

        function lookupCodeByData(data, isManualParam = false) {
            isProcessingLookup = true;
            if (isManualParam) isManual = true;
            $.get('/self-presence/{{ $meeting->uid }}/lookup', data, function (res) {
                if (isManualParam) { $('#btn-lookup').text('LANJUTKAN').attr('disabled', false); }

                if (res.status === 'success') {
                    if (res.already_submitted) {
                        showFeedback('warning', res.message);
                        isProcessingLookup = false;
                        if (isManualParam) $('#manual-member').val(null).trigger('change');
                    } else {
                        setupStep2(res.member);
                    }
                } else if (res.status === 'incomplete_biodata') {
                    setupBiodataForm(res.member);
                } else if (res.status === 'warning') {
                    showFeedback('warning', res.message);
                    if (res.member) setupStep2(res.member);
                } else {
                    showFeedback('error', res.message);
                    isProcessingLookup = false;
                    if (isManualParam) $('#manual-member').val('').change();
                }
                isProcessingLookup = false;
                isManual = false;
            }).fail(function () {
                if (isManualParam) $('#btn-lookup').text('LANJUTKAN').attr('disabled', false);
                showFeedback('error', 'Terjadi kesalahan sistem.');
                isProcessingLookup = false;
                isManual = false;
            });
        }

        function setupBiodataForm(member) {
            stopScanning();
            $('#bio-member-id').val(member.id);
            $('#bio-has-nick-name').val(member.has_nick_name ? '1' : '0');
            $('#bio-has-birth-date').val(member.has_birth_date ? '1' : '0');

            if (member.has_nick_name) {
                $('#bio-field-nick-name').addClass('hidden');
                $('#bio-nick-name').prop('required', false);
            } else {
                $('#bio-field-nick-name').removeClass('hidden');
                $('#bio-nick-name').val('').prop('required', true);
            }

            if (member.has_birth_date) {
                $('#bio-field-birth-date').addClass('hidden');
                $('#bio-birth-date').prop('required', false);
            } else {
                $('#bio-field-birth-date').removeClass('hidden');
                $('#bio-birth-date').val('').prop('required', true);
            }

            $('#step-1').removeClass('active');
            $('#step-1b').removeClass('active');
            $('#step-bio').addClass('active');
            isProcessingLookup = false;
            isManual = false;
        }

        function submitBiodata() {
            const memberId = $('#bio-member-id').val();
            const hasNickName = $('#bio-has-nick-name').val() === '1';
            const hasBirthDate = $('#bio-has-birth-date').val() === '1';

            const data = {
                _token: $('meta[name="csrf-token"]').attr('content'),
                member_id: memberId,
            };

            if (!hasNickName) {
                const nickName = $('#bio-nick-name').val().trim();
                if (!nickName) { showFeedback('warning', 'Isi nama panggilan Anda.'); return; }
                data.nick_name = nickName;
            }
            if (!hasBirthDate) {
                const birthDate = $('#bio-birth-date').val();
                if (!birthDate) { showFeedback('warning', 'Pilih tanggal lahir Anda.'); return; }
                data.birth_date = birthDate;
            }

            $('#btn-bio-submit').text('MENYIMPAN...').attr('disabled', true).addClass('opacity-75');

            $.ajax({
                url: '/self-presence/{{ $meeting->uid }}/complete-biodata',
                type: 'POST',
                data: data,
                success: function (res) {
                    if (res.status === 'success') {
                        showFeedback('success', 'Biodata berhasil disimpan.');
                        $('#step-bio').removeClass('active');
                        $('#btn-lookup').prop('disabled', false).text('LANJUTKAN');
                        setTimeout(() => setupStep2(res.member), 500);
                    } else {
                        showFeedback('error', res.message || 'Gagal menyimpan biodata.');
                    }
                },
                error: function (xhr) {
                    const msg = xhr.responseJSON ? (xhr.responseJSON.message || 'Data tidak valid.') : 'Terjadi kesalahan sistem.';
                    showFeedback('error', msg);
                },
                complete: function () {
                    $('#btn-bio-submit').text('SIMPAN & LANJUTKAN').attr('disabled', false).removeClass('opacity-75');
                }
            });
        }

        function resetFromBio() {
            $('#step-bio').removeClass('active');
            $('#step-1').addClass('active');
            $('#bio-field-nick-name').addClass('hidden');
            $('#bio-field-birth-date').addClass('hidden');
            $('#bio-nick-name').val('').prop('required', false);
            $('#bio-birth-date').val('').prop('required', false);
            $('#btn-lookup').text('LANJUTKAN').attr('disabled', false);
        }

        // --- TEMPORARY REGISTRATION ---
        function goToStep1b() {
            stopScanning();
            $('#step-1').removeClass('active');
            $('#step-1b').addClass('active');
        }

        function submitTemporaryRegistration() {
            const form = document.getElementById('register-temporary-form');
            const formData = new FormData(form);

            $('#btn-submit-temp').text('MENDAFTARKAN...').attr('disabled', true);

            $.ajax({
                url: '/self-presence/{{ $meeting->uid }}/register-temporary',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                success: function (res) {
                    if (res.status === 'success') {
                        showFeedback('success', 'Pendaftaran berhasil.');
                        $('#step-1b').removeClass('active');
                        setTimeout(() => setupStep2(res.member), 500);
                    } else {
                        showFeedback('error', res.message || 'Gagal mendaftar.');
                    }
                },
                error: function (xhr) {
                    const msg = xhr.responseJSON ? (xhr.responseJSON.message || 'Data tidak valid.') : 'Terjadi kesalahan sistem.';
                    showFeedback('error', msg);
                },
                complete: function () {
                    $('#btn-submit-temp').text('DAFTAR & LANJUT').attr('disabled', false);
                }
            });
        }

        function setupStep2(member) {
            stopScanning();
            stopCamera();

            $('#form-member-id').val(member.id);
            $('#member-name').text(member.full_name);
            $('#member-code').text(member.member_code || '-');
            $('#member-group').text(member.group || '-');

            if (member.photo_url) {
                $('#member-photo').css('background-image', `url(${member.photo_url})`).text('');
            } else {
                $('#member-photo').css('background-image', 'none').html('<svg class="w-8 h-8 m-auto mt-4 text-white/20" fill="currentColor" viewBox="0 0 24 24"><path d="M12 12a5 5 0 100-10 5 5 0 000 10zm0-2a3 3 0 110-6 3 3 0 010 6zm9 11a1 1 0 01-2 0c0-2.33-4.33-4-7-4s-7 1.67-7 4a1 1 0 01-2 0c0-2.9 5.33-6 9-6s9 3.1 9 6z"/></svg>');
            }

            $('#step-1').removeClass('active');
            $('#step-1b').removeClass('active');
            $('#step-bio').removeClass('active');
            $('#step-2').addClass('active');
            isProcessingLookup = false;

            $('#presence-form')[0].reset();
            capturedSelfie = null;
            resetCameraUI();
        }

        function resetToStep1() {
            stopCamera();
            capturedSelfie = null;
            $('#step-2').removeClass('active');
            $('#step-1b').removeClass('active');
            $('#step-bio').removeClass('active');
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
                    noResults: function() { return 'Tidak ditemukan.'; },
                    searching: function() { return 'Mencari...'; }
                }
            });
            $('#btn-lookup').prop('disabled', true).text('LANJUTKAN');
            $('#btn-submit').text('KIRIM PRESENSI').prop('disabled', false).removeClass('opacity-75 cursor-not-allowed');
            resetCameraUI();
        }

        function toggleCamera() {
            if (stream) {
                stopCamera();
                resetCameraUI();
                return;
            }

            if (!window.isSecureContext && location.hostname !== "localhost") {
                showFeedback('error', 'Kamera hanya bisa diakses melalui HTTPS.');
                return;
            }

            $('#selfie-preview').addClass('hidden');
            $('#video').removeClass('hidden');
            $('#camera-placeholder').addClass('hidden');
            $('#camera-preview').removeClass('hidden');
            $('#btn-camera').text('Matikan Kamera');
            $('#btn-capture').removeClass('hidden');
            $('#btn-upload').addClass('hidden');

            navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } } })
                .then(function (s) {
                    stream = s;
                    document.getElementById('video').srcObject = s;

                    const videoEl = document.getElementById('video');
                    videoEl.onloadedmetadata = function () {
                        const canvas = document.getElementById('canvas');
                        canvas.width = videoEl.videoWidth;
                        canvas.height = videoEl.videoHeight;
                    };
                })
                .catch(function () {
                    showFeedback('error', 'Gagal mengakses kamera. Silakan upload foto sebagai alternatif.');
                    stopCamera();
                    resetCameraUI();
                });
        }

        function stopCamera() {
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
                stream = null;
            }
            const videoEl = document.getElementById('video');
            if (videoEl) videoEl.srcObject = null;
        }

        function resetCameraUI() {
            $('#video').removeClass('hidden');
            $('#selfie-preview').addClass('hidden');
            $('#camera-preview').addClass('hidden');
            $('#camera-placeholder').removeClass('hidden');
            $('#btn-camera').text('Buka Kamera');
            $('#btn-capture').addClass('hidden');
            $('#btn-upload').removeClass('hidden');
        }

        function captureSelfie() {
            if (!stream) {
                showFeedback('warning', 'Aktifkan kamera terlebih dahulu.');
                return;
            }

            const video = document.getElementById('video');
            const canvas = document.getElementById('canvas');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            const ctx = canvas.getContext('2d');
            ctx.translate(canvas.width, 0);
            ctx.scale(-1, 1);
            ctx.drawImage(video, 0, 0);

            const dataUrl = canvas.toDataURL('image/jpeg', 0.6);
            capturedSelfie = dataUrl.split(',')[1];
            $('#selfie-preview').attr('src', dataUrl).removeClass('hidden');
            $('#video').addClass('hidden');
            $('#camera-placeholder').addClass('hidden');
            stopCamera();
            $('#btn-camera').text('Ambil Ulang');
            $('#btn-capture').addClass('hidden');
            $('#btn-upload').removeClass('hidden');
        }

        function handleUpload(event) {
            const file = event.target.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onerror = function () {
                showFeedback('error', 'Gagal membaca file foto.');
            };
            reader.onload = function (e) {
                const img = new Image();
                img.onerror = function () {
                    showFeedback('error', 'Format gambar tidak dapat diproses.');
                };
                img.onload = function () {
                    const canvas = document.createElement('canvas');
                    let w = img.width, h = img.height;
                    const maxDim = 1000;
                    if (w > maxDim || h > maxDim) {
                        if (w > h) { h = Math.round((h * maxDim) / w); w = maxDim; }
                        else { h = Math.round((w * maxDim) / h); h = maxDim; }
                    }
                    canvas.width = w;
                    canvas.height = h;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, w, h);
                    const dataUrl = canvas.toDataURL('image/jpeg', 0.7);
                    capturedSelfie = dataUrl.split(',')[1];
                    $('#selfie-preview').attr('src', dataUrl).removeClass('hidden');
                    $('#video').addClass('hidden');
                    $('#camera-preview').removeClass('hidden');
                    $('#camera-placeholder').addClass('hidden');
                    stopCamera();
                    $('#btn-camera').text('Ambil Ulang');
                    $('#btn-capture').addClass('hidden');
                    $('#btn-upload').removeClass('hidden');
                };
                img.src = e.target.result;
            };
            reader.readAsDataURL(file);
        }

        function submitPresence() {
            const memberId = $('#form-member-id').val();
            if (!memberId) {
                showFeedback('warning', 'Data anggota tidak valid.');
                return;
            }

            if (!capturedSelfie) {
                showFeedback('warning', 'Ambil selfie atau upload foto terlebih dahulu.');
                return;
            }

            $('#btn-submit').text('MEMPROSES...').attr('disabled', true).addClass('opacity-75');

            const formData = new FormData(document.getElementById('presence-form'));
            formData.append('selfie_photo', capturedSelfie);

            $.ajax({
                url: '/self-presence/{{ $meeting->uid }}/store',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                success: function (res) {
                    showFeedback(res.status, res.message);
                    if (res.status === 'success') {
                        $('#btn-submit').text('TERSIMPAN, MENGALIHKAN...').prop('disabled', true).addClass('opacity-75 cursor-not-allowed');
                        setTimeout(() => resetToStep1(), 3000);
                    } else {
                        $('#btn-submit').text('KIRIM PRESENSI').prop('disabled', false).removeClass('opacity-75 cursor-not-allowed');
                    }
                },
                error: function (xhr) {
                    const msg = xhr.responseJSON ? (xhr.responseJSON.message || 'Data tidak valid.') : 'Terjadi kesalahan sistem.';
                    showFeedback('error', msg);
                    $('#btn-submit').text('KIRIM PRESENSI').prop('disabled', false).removeClass('opacity-75 cursor-not-allowed');
                }
            });
        }

        window.addEventListener('pagehide', function () {
            stopCamera();
            stopScanning();
        });

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
</body>
</html>
