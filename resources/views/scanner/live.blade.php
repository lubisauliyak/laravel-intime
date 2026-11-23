<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Scanner Presensi: {{ $meeting->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
        }
    </script>
    @include('components.theme-script')
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
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

        /* Select2 Dark Mode Fixes */
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
            z-index: 999999 !important;
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
            border: 1px solid #e5e7eb !important;
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

        .custom-scrollbar::-webkit-scrollbar {
            width: 5px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(0, 0, 0, 0.1);
            border-radius: 10px;
        }

        .dark .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.1);
        }

        /* Camera Fill Frame Fix */
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
    </style>
</head>
@php
    $isFinished = $meeting->meeting_date->setTimeFrom($meeting->end_time)->isPast();
    $isCheckinOpen = $meeting->isCheckinOpen();
    $hasCheckinTime = !is_null($meeting->checkin_open_time);
    $effectiveOpenTime = $meeting->checkin_open_time ?? $meeting->start_time;
    $isBeforeCheckin = $meeting->meeting_date->isToday() && $effectiveOpenTime && now()->isBefore($meeting->meeting_date->copy()->setTimeFrom($effectiveOpenTime));
    $isOperator = auth()->user()?->hasRole('operator');
    $canScan = !$isFinished && !$isBeforeCheckin;
@endphp

<body
    class="bg-gray-50 dark:bg-[#0d1511] text-gray-900 dark:text-white min-h-screen overflow-x-hidden transition-colors duration-200">
    <!-- Navbar -->
    <nav
        class="p-4 md:p-6 flex items-center justify-between border-b border-gray-200 dark:border-white/5 bg-white/80 dark:bg-[#0d1511]/80 backdrop-blur-md sticky top-0 z-50 transition-colors duration-200">
        <div class="flex items-center gap-3 md:gap-4 overflow-hidden">
            <a href="/admin/meetings/{{ $meeting->uid }}"
                class="p-2 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-white/10 rounded-xl transition-all shrink-0">
                <svg class="w-5 h-5 md:w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M15 19l-7-7 7-7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </a>
            <div class="truncate">
                <h1 class="text-base md:text-lg font-extrabold tracking-tight truncate">{{ $meeting->name }}</h1>
                <p class="text-[9px] md:text-[10px] text-gray-500 uppercase tracking-widest truncate">
                    {{ $meeting->group->name }} • {{ $meeting->meeting_date->format('d M Y') }} •
                    {{ $meeting->start_time->format('H:i') }}-{{ $meeting->end_time->format('H:i') }}@if($hasCheckinTime)
                    • Presensi {{ $meeting->checkin_open_time->format('H:i') }}@endif
                </p>
            </div>
        </div>
        <div class="flex items-center gap-2 md:gap-3 shrink-0">
            @if($isFinished)
                <div
                    class="flex items-center gap-2 px-3 py-1 md:px-4 md:py-1.5 bg-rose-500/10 border border-rose-500/20 rounded-full">
                    <div class="w-1.5 h-1.5 rounded-full bg-rose-500"></div>
                    <span class="text-[9px] md:text-[10px] font-black text-rose-400 uppercase tracking-widest">Sesi
                        Berakhir</span>
                </div>
            @elseif($isBeforeCheckin)
                <div
                    class="flex items-center gap-2 px-3 py-1 md:px-4 md:py-1.5 bg-amber-500/10 border border-amber-500/20 rounded-full text-nowrap">
                    <div class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></div>
                    <span class="text-[9px] md:text-[10px] font-black text-amber-400 uppercase tracking-widest">Presensi
                        Dimulai {{ $effectiveOpenTime->format('H:i') }}</span>
                </div>
            @else
                <div
                    class="flex items-center gap-2 px-3 py-1 md:px-4 md:py-1.5 bg-emerald-500/10 border border-emerald-500/20 rounded-full text-nowrap">
                    <div class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></div>
                    <span
                        class="text-[9px] md:text-[10px] font-black text-emerald-400 uppercase tracking-widest">Berlangsung</span>
                </div>
            @endif
            <div class="ml-2 border-l border-gray-200 dark:border-gray-800 pl-3">
                @include('components.theme-switcher')
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto p-4 md:p-8">
        <div
            class="grid grid-cols-1 {{ $canScan ? 'lg:grid-cols-12' : ($isBeforeCheckin ? 'lg:grid-cols-12' : '') }} gap-8 md:gap-12 items-start">

            @if($isBeforeCheckin)
                {{-- Waiting Card: Presensi belum dimulai --}}
                <div class="lg:col-span-5 animate-in fade-in duration-1000">
                    <div
                        class="glass p-10 md:p-14 rounded-2xl border-white/5 relative overflow-hidden text-center shadow-2xl">
                        <div class="relative mb-8">
                            <div class="absolute -inset-6 bg-amber-500/20 blur-2xl rounded-full animate-pulse"></div>
                            <div
                                class="relative w-24 h-24 mx-auto bg-amber-500/20 rounded-[2rem] flex items-center justify-center shadow-2xl border border-amber-500/30">
                                <svg class="w-12 h-12 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" stroke-width="2.5"
                                        stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </div>
                        </div>
                        <h3 class="text-2xl font-black mb-3 tracking-tighter uppercase text-amber-400">Menunggu Presensi
                        </h3>
                        <p class="text-gray-400 text-sm mb-6 max-w-xs mx-auto leading-relaxed font-medium">Presensi akan
                            dibuka pada jam <span
                                class="text-amber-400 font-black">{{ $effectiveOpenTime->format('H:i') }}</span></p>
                        <div
                            class="inline-flex items-center gap-2 px-5 py-2.5 bg-amber-500/10 border border-amber-500/20 rounded-full">
                            <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" stroke-width="2" />
                            </svg>
                            <span class="text-[10px] font-black text-amber-400 uppercase tracking-widest">Pertemuan
                                {{ $meeting->start_time->format('H:i') }} - {{ $meeting->end_time->format('H:i') }}</span>
                        </div>
                    </div>
                </div>
            @elseif($canScan)
                <!-- Area Scanner (Kiri) -->
                <div class="lg:col-span-5 space-y-8 animate-in fade-in duration-1000">
                    <!-- Camera Container -->
                    <div class="relative group">
                        <div
                            class="absolute -inset-1 bg-gradient-to-r from-emerald-500 to-blue-500 rounded-2xl blur opacity-20 group-hover:opacity-40 transition duration-1000">
                        </div>
                        <div id="reader-container"
                            class="relative bg-gray-100 dark:bg-black rounded-2xl aspect-square overflow-hidden border-8 border-white dark:border-white/5 shadow-2xl">
                            <div id="reader" style="width: 100%; height: 100%;"></div>

                            <!-- HUD Overlay -->
                            <div
                                class="absolute inset-0 pointer-events-none z-10 flex flex-col justify-between p-6 md:p-10">
                                <div class="flex justify-between">
                                    <div
                                        class="w-10 h-10 md:w-14 h-14 border-t-4 border-l-4 border-emerald-500 rounded-tl-2xl">
                                    </div>
                                    <div
                                        class="w-10 h-10 md:w-14 h-14 border-t-4 border-r-4 border-emerald-500 rounded-tr-2xl">
                                    </div>
                                </div>
                                <!-- Scanner Line -->
                                <div id="scanner-line"
                                    class="absolute left-6 right-6 md:left-10 md:right-10 h-0.5 bg-emerald-500/50 shadow-[0_0_20px_rgba(16,185,129,0.8)] scan-line hidden">
                                </div>

                                <div class="flex justify-between">
                                    <div
                                        class="w-10 h-10 md:w-14 h-14 border-b-4 border-l-4 border-emerald-500 rounded-bl-2xl">
                                    </div>
                                    <div
                                        class="w-10 h-10 md:w-14 h-14 border-b-4 border-r-4 border-emerald-500 rounded-br-2xl">
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
                                    <div class="absolute -inset-6 bg-emerald-500/20 blur-2xl rounded-full animate-pulse">
                                    </div>
                                    <div
                                        class="relative w-24 h-24 bg-emerald-500 rounded-[2rem] flex items-center justify-center shadow-2xl shadow-emerald-500/40 rotate-12 group-hover:rotate-0 transition-transform duration-500">
                                        <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path
                                                d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"
                                                stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </div>
                                </div>
                                <h3
                                    class="text-2xl font-black mb-3 tracking-tighter uppercase text-gray-900 dark:text-white">
                                    Scanner Siap</h3>
                                <p
                                    class="text-gray-500 dark:text-gray-400 text-sm mb-8 max-w-xs leading-relaxed font-medium">
                                    Klik tombol di bawah untuk mulai memindai Kode QR.</p>
                                <button onclick="startScanning()"
                                    class="w-full px-8 py-4 bg-gray-900 dark:bg-white text-white dark:text-black font-black rounded-2xl hover:bg-emerald-500 dark:hover:bg-emerald-500 hover:text-white dark:hover:text-white transition-all shadow-xl active:scale-95 uppercase tracking-tighter text-base">AKTIFKAN
                                    KAMERA</button>
                            </div>
                        </div>
                    </div>

                    <!-- Manual Input Card -->
                    <div class="glass p-8 rounded-2xl border-white/5 relative overflow-hidden group shadow-2xl">
                        <div
                            class="absolute top-0 right-0 p-8 opacity-5 -mr-10 -mt-10 group-hover:rotate-12 transition-transform duration-1000 text-gray-900 dark:text-white">
                            <svg class="w-32 h-32" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" stroke-width="2" />
                            </svg>
                        </div>
                        <div class="flex items-center gap-4 relative z-10">
                            <div
                                class="p-3 bg-blue-100 dark:bg-blue-500/20 rounded-2xl text-blue-600 dark:text-blue-400 shadow-inner">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" stroke-width="2.5" />
                                </svg>
                            </div>
                            <h2 class="text-xl font-black uppercase tracking-tighter text-gray-900 dark:text-white">Cari
                                Manual</h2>
                        </div>
                        <div class="space-y-5 relative z-10 mt-6">
                            <select id="filter-group"
                                class="w-full bg-white dark:bg-[#0d1511] border border-gray-300 dark:border-white/10 rounded-xl p-3 text-sm focus:ring-2 focus:ring-emerald-500 outline-none transition-all text-gray-900 dark:text-white/90 font-semibold shadow-sm">
                                <option value="">-- Semua Kelompok --</option>
                                @foreach($groups as $grp)
                                    <option value="{{ $grp->id }}">{{ $grp->name }}</option>
                                @endforeach
                            </select>

                            <div class="shadow-sm rounded-xl">
                                <select id="manual-search" class="w-full"></select>
                            </div>

                            @php
                                $canSetExcused = auth()->user()->can('SetExcusedAttendance');
                            @endphp
                            <div class="grid {{ $canSetExcused ? 'grid-cols-3' : '' }} gap-3 pt-2">
                                <button onclick="recordManual('hadir')"
                                    class="py-4 md:py-3 bg-emerald-50 dark:bg-emerald-500/20 hover:bg-emerald-500 text-emerald-600 dark:text-emerald-400 hover:text-white font-black rounded-xl transition-all border border-emerald-200 dark:border-emerald-500/30 text-[11px] md:text-[10px] uppercase tracking-tighter shadow-sm">HADIR</button>
                                @if($canSetExcused)
                                    <button onclick="recordManual('izin')"
                                        class="py-4 md:py-3 bg-amber-50 dark:bg-amber-500/20 hover:bg-amber-500 text-amber-600 dark:text-amber-400 hover:text-white font-black rounded-xl transition-all border border-amber-200 dark:border-amber-500/30 text-[11px] md:text-[10px] uppercase tracking-tighter shadow-sm">IZIN</button>
                                    <button onclick="recordManual('sakit')"
                                        class="py-4 md:py-3 bg-red-50 dark:bg-red-500/20 hover:bg-red-500 text-red-600 dark:text-red-400 hover:text-white font-black rounded-xl transition-all border border-red-200 dark:border-red-500/30 text-[11px] md:text-[10px] uppercase tracking-tighter shadow-sm">SAKIT</button>
                                @endif
                            </div>
                        </div>
                        <div class="mt-8 text-center border-t border-gray-200 dark:border-white/10 pt-6 relative z-10">
                            <button onclick="openTempRegModal()"
                                class="text-xs font-bold text-emerald-600 dark:text-emerald-400 hover:text-emerald-700 dark:hover:text-emerald-300 transition-colors uppercase tracking-wider">Belum
                                punya KTA / Belum Terdaftar?</button>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Tabel Presensi -->
            <div
                class="{{ $isFinished ? 'max-w-4xl mx-auto w-full' : 'lg:col-span-7' }} space-y-8 animate-in slide-in-from-bottom-6 duration-1000">
                <div class="flex items-center justify-between px-2">
                    <div class="flex items-center gap-3 md:gap-4">
                        <h2 class="text-2xl md:text-3xl font-black tracking-tighter">DAFTAR HADIR</h2>
                        <span id="attendee-count"
                            class="px-3 py-1 md:px-4 md:py-1.5 bg-white/10 rounded-full text-[10px] md:text-xs font-black text-gray-400 uppercase tracking-widest">{{ count($attendances) }}
                            Anggota</span>
                    </div>
                    <button onclick="location.reload()"
                        class="p-3 hover:bg-white/10 rounded-2xl text-gray-500 hover:text-white transition-all group">
                        <svg class="w-6 h-6 group-hover:rotate-180 transition-transform duration-500" fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path
                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                                stroke-width="2.5" stroke-linecap="round" />
                        </svg>
                    </button>
                </div>

                <div
                    class="glass rounded-2xl overflow-hidden border-white/5 shadow-[0_32px_64px_-12px_rgba(0,0,0,0.8)]">
                    <div class="max-h-[70vh] overflow-y-auto custom-scrollbar">
                        <table class="w-full text-left border-collapse">
                            <thead class="sticky top-0 z-10 backdrop-blur-md">
                                <tr
                                    class="bg-gray-200 dark:bg-[#0d1511] text-[9px] md:text-[10px] font-black uppercase tracking-[0.2em] text-gray-800 dark:text-gray-500 border-b border-gray-300 dark:border-white/5">
                                    <th class="px-4 py-4 md:px-8 md:py-5">Identitas Anggota</th>
                                    <th class="px-4 py-4 md:px-8 md:py-5">Waktu</th>
                                    <th class="px-4 py-4 md:px-8 md:py-5 text-right">Status</th>
                                </tr>
                            </thead>
                            <tbody id="attendance-list" class="divide-y divide-gray-200 dark:divide-white/5">
                                @forelse($attendances as $attendance)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-white/[0.02] transition-colors">
                                        <td class="px-4 py-4 md:px-8 md:py-5">
                                            <div class="flex items-center gap-2">
                                                <div
                                                    class="font-black text-xs md:text-sm uppercase tracking-tight text-emerald-600 dark:text-emerald-400 truncate max-w-[120px] md:max-w-none">
                                                    {{ $attendance->member?->full_name ?? '-' }}
                                                </div>
                                                @if($attendance->is_supervisor_badge)
                                                    <span
                                                        class="inline-block text-[8px] font-black bg-blue-100 dark:bg-blue-500/20 text-blue-600 dark:text-blue-400 px-1.5 py-0.5 rounded border border-blue-200 dark:border-blue-500/30 uppercase tracking-tighter">PENGURUS</span>
                                                @endif
                                            </div>
                                            <div
                                                class="text-[9px] md:text-[10px] text-gray-600 dark:text-gray-500 font-bold uppercase mt-1 tracking-widest">
                                                {{ $attendance->member?->member_code ?? '-' }}
                                            </div>
                                        </td>
                                        <td
                                            class="px-4 py-4 md:px-8 md:py-5 font-black text-[10px] md:text-xs text-gray-700 dark:text-gray-400 whitespace-nowrap">
                                            {{ $attendance->checkin_time?->format('H:i') ?? '-' }}
                                        </td>
                                        <td class="px-4 py-4 md:px-8 md:py-5 text-right">
                                            @php
                                                $statusColor = match ($attendance->status?->value) {
                                                    'hadir' => 'text-emerald-600 dark:text-emerald-400 border-emerald-200 dark:border-emerald-500/20 bg-emerald-50 dark:bg-emerald-500/5',
                                                    'izin' => 'text-amber-600 dark:text-amber-400 border-amber-200 dark:border-amber-500/20 bg-amber-50 dark:bg-amber-500/5',
                                                    'sakit' => 'text-red-600 dark:text-red-400 border-red-200 dark:border-red-500/20 bg-red-50 dark:bg-red-500/5',
                                                    default => 'text-gray-600 dark:text-gray-500 border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-white/5',
                                                };
                                                $isLate = str_contains(strtoupper($attendance->notes ?? ''), 'TERLAMBAT');
                                                // Bukti shows if there is a file OR notes that are NOT just "TERLAMBAT"
                                                $hasRealNotes = $attendance->notes && strtoupper($attendance->notes) !== 'TERLAMBAT';
                                                $hasEvidence = $attendance->evidence_path || $hasRealNotes;
                                            @endphp
                                            <div class="flex flex-col items-end gap-1.5">
                                                <div class="flex items-center gap-1.5">
                                                    @if($isLate)
                                                        <span
                                                            class="inline-block text-[8px] md:text-[9px] font-black border border-rose-200 dark:border-rose-500/20 bg-rose-50 dark:bg-rose-500/10 text-rose-600 dark:text-rose-400 px-2 py-0.5 rounded-md uppercase tracking-tighter">TERLAMBAT</span>
                                                    @endif
                                                    <span
                                                        class="inline-block text-[8px] md:text-[9px] font-black border {{ $statusColor }} px-2 py-0.5 md:px-3 md:py-1 rounded-full uppercase tracking-tighter">{{ $attendance->status?->getLabel() ?? 'HADIR' }}</span>
                                                </div>
                                                <div
                                                    class="flex items-center justify-end gap-2 text-[8px] font-bold text-gray-500 dark:text-gray-600">
                                                    @if($hasEvidence)
                                                        <div
                                                            class="px-1.5 py-0.5 bg-gray-100 dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded-md text-gray-600 dark:text-gray-400 uppercase tracking-tighter">
                                                            LAMPIRAN</div>
                                                    @endif
                                                    <div
                                                        class="hidden md:block uppercase tracking-widest text-gray-500 dark:text-gray-500">
                                                        {{ $attendance->method?->getLabel() ?? str_replace('_', ' ', $attendance->method?->value ?? '') }}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr id="empty-row">
                                        <td colspan="3" class="px-8 py-24 text-center">
                                            <div class="opacity-30 dark:opacity-10 flex flex-col items-center">
                                                <svg class="w-20 h-20 mb-6 text-gray-500 dark:text-white" fill="none"
                                                    stroke="currentColor" viewBox="0 0 24 24">
                                                    <path
                                                        d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"
                                                        stroke-width="1" />
                                                </svg>
                                                <p
                                                    class="text-xs font-black uppercase tracking-[0.3em] text-gray-600 dark:text-white">
                                                    Belum ada aktivitas presensi</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="flex justify-center p-4">
                    <p class="text-[10px] text-gray-500 font-bold uppercase tracking-[0.2em]">Sistem Presensi Real-time
                        • inTime v1.0</p>
                </div>
            </div>
        </div>
    </main>

    <!-- Global Floating Feedback -->
    <div id="feedback"
        class="fixed bottom-8 left-1/2 -translate-x-1/2 z-[100] pointer-events-none hidden max-w-sm w-full px-4">
        <div id="feedback-card"
            class="bg-emerald-500 text-white p-5 rounded-2xl shadow-2xl flex items-center gap-4 border border-white/20 transform transition-all duration-500 scale-90 opacity-0">
            <div id="feedback-icon"
                class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center shrink-0 border border-white/10 shadow-inner">
                <svg id="icon-success" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M5 13l4 4L19 7" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                <svg id="icon-warning" class="w-6 h-6 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M12 9v2m0 4h.01M3 12a9 9 0 1118 0 9 9 0 01-18 0z" stroke-width="2" />
                </svg>
            </div>
            <div>
                <h4 id="feedback-title" class="font-black text-base uppercase tracking-tighter leading-none mb-0.5">
                    Status</h4>
                <p id="feedback-message" class="text-sm font-medium opacity-90 leading-snug"></p>
            </div>
        </div>
    </div>

    <!-- Audio Effects -->
    <audio id="audio-success" src="https://assets.mixkit.co/active_storage/sfx/2354/2354-preview.mp3"
        preload="auto"></audio>
    <audio id="audio-error" src="https://assets.mixkit.co/active_storage/sfx/2571/2571-preview.mp3"
        preload="auto"></audio>

    <!-- Evidence Modal -->
    <div id="evidence-modal"
        class="fixed inset-0 z-[100] hidden items-center justify-center p-4 bg-black/80 backdrop-blur-md">
        <div id="evidence-content"
            class="glass max-w-md w-full p-8 rounded-2xl border-white/10 transform transition-all duration-300 scale-95 opacity-0">
            <div class="flex items-center gap-4 mb-8">
                <div class="p-4 bg-blue-100 dark:bg-blue-500/20 rounded-2xl text-blue-600 dark:text-blue-400">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path
                            d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                            stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-2xl font-black uppercase tracking-tighter text-gray-900 dark:text-white">Lampiran
                        <span id="evidence-status-label" class="text-amber-500 dark:text-amber-400"></span>
                    </h3>
                    <p class="text-xs text-gray-500 font-bold uppercase tracking-widest mt-1">Unggah bukti foto atau
                        keterangan</p>
                </div>
            </div>

            <form id="evidence-form" method="POST" enctype="multipart/form-data" class="space-y-6">
                @csrf
                <input type="hidden" id="modal-member-id" name="member_id">
                <input type="hidden" id="modal-status" name="status">

                <div class="space-y-2">
                    <label
                        class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-600 dark:text-gray-500 ml-1">Foto
                        Bukti (Opsional)</label>
                    <div class="relative group">
                        <input type="file" name="evidence" accept="image/*"
                            class="w-full text-xs text-gray-600 dark:text-gray-500 file:mr-4 file:py-3 file:px-6 file:rounded-2xl file:border-0 file:text-[10px] file:font-black file:uppercase file:bg-gray-200 dark:file:bg-white/5 file:text-gray-700 dark:file:text-white hover:file:bg-gray-300 dark:hover:file:bg-white/10 transition-all cursor-pointer bg-white dark:bg-white/5 rounded-2xl p-2 border border-gray-300 dark:border-white/5">
                    </div>
                </div>

                <div class="space-y-2">
                    <label
                        class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-600 dark:text-gray-500 ml-1">Keterangan
                        Tambahan</label>
                    <textarea name="notes" rows="3"
                        class="w-full bg-white dark:bg-white/5 border border-gray-300 dark:border-white/5 rounded-2xl p-4 text-sm focus:ring-2 focus:ring-emerald-500 outline-none transition-all placeholder:text-gray-400 dark:placeholder:text-white/10 text-gray-900 dark:text-white"
                        placeholder="Contoh: Surat Izin, Sedang di luar kota, dll..."></textarea>
                </div>

                <div class="grid grid-cols-2 gap-4 pt-2">
                    <button type="button" onclick="closeEvidenceModal()"
                        class="py-4 bg-gray-100 dark:bg-white/5 hover:bg-gray-200 dark:hover:bg-white/10 text-gray-700 dark:text-white font-black rounded-2xl transition-all uppercase tracking-tighter border border-gray-300 dark:border-transparent">BATAL</button>
                    <button type="button" id="btn-submit-evidence" onclick="submitEvidence()"
                        class="py-4 bg-emerald-500 hover:bg-emerald-600 text-white font-black rounded-2xl shadow-xl shadow-emerald-500/20 transition-all uppercase tracking-tighter">SIMPAN</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Temporary Registration Modal -->
    <div id="temp-reg-modal"
        class="fixed inset-0 z-[100] hidden items-center justify-center p-4 bg-black/80 backdrop-blur-md">
        <div id="temp-reg-content"
            class="glass max-w-md w-full p-8 rounded-2xl border-white/10 transform transition-all duration-300 scale-95 opacity-0">
            <div class="flex items-center gap-4 mb-8">
                <div
                    class="p-4 bg-emerald-100 dark:bg-emerald-500/20 rounded-2xl text-emerald-600 dark:text-emerald-400">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-2xl font-black uppercase tracking-tighter text-gray-900 dark:text-white">Pendaftaran
                        Instan</h3>
                    <p class="text-xs text-gray-500 font-bold uppercase tracking-widest mt-1">Daftarkan anggota baru</p>
                </div>
            </div>

            <form id="temp-reg-form" onsubmit="event.preventDefault(); submitTempReg();" class="space-y-4"
                enctype="multipart/form-data">
                @csrf
                <div class="space-y-2">
                    <label
                        class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-600 dark:text-gray-500 ml-1">Asal
                        Kelompok <span class="text-rose-500">*</span></label>
                    <select id="temp-group-select" name="group_id" required
                        class="w-full bg-white dark:bg-[#0d1511] border border-gray-300 dark:border-white/10 rounded-xl p-3 text-sm focus:ring-2 focus:ring-emerald-500 outline-none transition-all text-gray-900 dark:text-white/90 font-medium">
                        <option value="" disabled selected>Pilih Grup...</option>
                        @foreach($groups as $grp)
                            <option value="{{ $grp->id }}">{{ $grp->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="space-y-2">
                    <label
                        class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-600 dark:text-gray-500 ml-1">Nama
                        Lengkap <span class="text-rose-500">*</span></label>
                    <input type="text" name="full_name" required
                        class="w-full bg-white dark:bg-white/5 border border-gray-300 dark:border-white/10 rounded-xl p-3 text-sm focus:ring-2 focus:ring-emerald-500 outline-none transition-all text-gray-900 dark:text-white placeholder:text-gray-400 dark:placeholder:text-white/30"
                        placeholder="Nama Lengkap">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <label
                            class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-600 dark:text-gray-500 ml-1">Panggilan
                            <span class="text-rose-500">*</span></label>
                        <input type="text" name="nick_name" required
                            class="w-full bg-white dark:bg-white/5 border border-gray-300 dark:border-white/10 rounded-xl p-3 text-sm focus:ring-2 focus:ring-emerald-500 outline-none transition-all text-gray-900 dark:text-white placeholder:text-gray-400 dark:placeholder:text-white/30"
                            placeholder="Panggilan">
                    </div>
                    <div class="space-y-2">
                        <label
                            class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-600 dark:text-gray-500 ml-1">Jenis
                            Kelamin <span class="text-rose-500">*</span></label>
                        <select name="gender" required
                            class="w-full bg-white dark:bg-[#0d1511] border border-gray-300 dark:border-white/10 rounded-xl p-3 text-sm focus:ring-2 focus:ring-emerald-500 outline-none transition-all text-gray-900 dark:text-white/90 font-medium">
                            <option value="" disabled selected>Pilih...</option>
                            <option value="male">Laki-laki</option>
                            <option value="female">Perempuan</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <label
                            class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-600 dark:text-gray-500 ml-1">Tanggal
                            Lahir <span class="text-rose-500">*</span></label>
                        <input type="date" name="birth_date" required
                            class="w-full bg-white dark:bg-white/5 border border-gray-300 dark:border-white/10 rounded-xl p-3 text-sm focus:ring-2 focus:ring-emerald-500 outline-none transition-all text-gray-900 dark:text-white dark:[color-scheme:dark]">
                    </div>
                    <div class="space-y-2">
                        <label
                            class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-600 dark:text-gray-500 ml-1">Foto
                            Profil (Opsional)</label>
                        <div class="relative group">
                            <input type="file" name="photo" accept="image/*"
                                class="w-full text-xs text-gray-600 dark:text-gray-500 file:mr-2 file:py-1.5 file:px-4 file:rounded-lg file:border-0 file:text-[10px] file:font-black file:uppercase file:bg-gray-200 dark:file:bg-white/5 file:text-gray-700 dark:file:text-white hover:file:bg-gray-300 dark:hover:file:bg-white/10 transition-all cursor-pointer bg-white dark:bg-white/5 rounded-xl p-1.5 border border-gray-300 dark:border-white/10">
                        </div>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4 pt-2">
                    <button type="button" onclick="closeTempRegModal()"
                        class="py-4 bg-gray-100 dark:bg-white/5 hover:bg-gray-200 dark:hover:bg-white/10 text-gray-700 dark:text-white font-black rounded-2xl transition-all uppercase tracking-tighter border border-gray-300 dark:border-transparent">BATAL</button>
                    <button type="submit" id="btn-submit-temp-reg"
                        class="py-4 bg-emerald-500 hover:bg-emerald-600 text-white font-black rounded-2xl shadow-xl shadow-emerald-500/20 transition-all uppercase tracking-tighter">DAFTAR
                        & HADIR</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let html5QrCode;
        let isProcessing = false;
        let count = {{ count($attendances) }};

        $(document).ready(function () {
            const $manualSearch = $('#manual-search');
            $manualSearch.select2({
                placeholder: "-- Cari Nama / No KTA --",
                minimumInputLength: 0,
                language: {
                    errorLoading: function () { return 'Gagal memuat data.'; },
                    inputTooShort: function (args) { return 'Ketik nama atau pilih kelompok...'; },
                    loadingMore: function () { return 'Memuat lebih banyak...'; },
                    noResults: function () {
                        const groupId = $('#filter-group').val();
                        const searchTerm = $('.select2-search__field').val() || '';
                        if (!groupId && searchTerm.length < 2) {
                            return 'Ketik minimal 2 karakter...';
                        }
                        return 'Tidak ditemukan.';
                    },
                    searching: function () { return 'Mencari...'; }
                },
                ajax: {
                    url: '/live-scanner/{{ $meeting->uid }}/search',
                    dataType: 'json',
                    delay: 250,
                    cache: false,
                    data: function (params) {
                        return {
                            q: params.term || '',
                            group_id: $('#filter-group').val()
                        };
                    },
                    processResults: function (data) {
                        if (data.error) {
                            console.error("Search Error:", data.error);
                            alert("Backend Error: " + data.error);
                        }
                        return { results: data.error ? [] : data };
                    },
                    error: function (jqXHR, textStatus, errorThrown) {
                        console.error("AJAX Error:", textStatus, errorThrown, jqXHR.responseText);
                        if (jqXHR.status !== 0 && jqXHR.status !== 200) {
                            alert("AJAX Error: " + jqXHR.status + " " + errorThrown);
                        }
                    }
                },
                templateResult: function (item) {
                    if (item.loading) return item.text;
                    const badge = item.badge ? `<span class="inline-block text-[8px] font-black bg-blue-500/20 text-blue-400 px-1.5 py-0.5 rounded border border-blue-500/30 uppercase tracking-tighter ml-2">${item.badge}</span>` : '';
                    const code = item.code ? `<span class="text-gray-500 font-bold ml-1 text-[10px]">(${item.code})</span>` : '';
                    const group = item.group ? `<div class="text-[10px] text-gray-500 font-semibold mt-0.5">${item.group}</div>` : '';
                    return $(`<div class="py-1">${item.text}${code}${badge}${group}</div>`);
                },
                templateSelection: function (item) {
                    if (!item.id) return item.text;
                    const badge = item.badge ? ` [${item.badge}]` : '';
                    return item.text + badge;
                }
            });

            $('#temp-group-select').select2({
                placeholder: 'Cari Kelompok...',
                allowClear: false,
                width: '100%',
                language: {
                    noResults: function () { return 'Tidak ditemukan.'; },
                    searching: function () { return 'Mencari...'; }
                }
            });

            $('#filter-group').select2({
                placeholder: '-- Semua Kelompok --',
                allowClear: true,
                width: '100%',
            }).on('change', function () {
                // Clear the member search dropdown when group changes
                $('#manual-search').val(null).trigger('change');
            });

        });

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

        let compressedEvidenceModalBlob = null;
        let compressedTempRegPhotoBlob = null;

        $(document).on('change', '#evidence-modal input[name="evidence"]', async function () {
            if (this.files && this.files[0]) {
                try {
                    compressedEvidenceModalBlob = await compressImageFile(this.files[0], 1200, 0.7);
                } catch (e) {
                    compressedEvidenceModalBlob = this.files[0];
                }
            } else {
                compressedEvidenceModalBlob = null;
            }
        });

        $(document).on('change', '#temp-reg-modal input[name="photo"]', async function () {
            if (this.files && this.files[0]) {
                try {
                    compressedTempRegPhotoBlob = await compressImageFile(this.files[0], 1200, 0.7);
                } catch (e) {
                    compressedTempRegPhotoBlob = this.files[0];
                }
            } else {
                compressedTempRegPhotoBlob = null;
            }
        });

        function recordManual(status) {
            const id = $('#manual-search').val();
            const $btn = $(event.currentTarget);

            if (!id) {
                showFeedback('warning', 'Pilih anggota terlebih dahulu.');
                return;
            }

            // Simpan teks asli
            const originalText = $btn.text();
            $btn.text('...').attr('disabled', true).addClass('opacity-50');

            if (status === 'hadir') {
                submitAttendance(id, 'hadir', $btn, originalText);
            } else {
                $btn.text(originalText).attr('disabled', false).removeClass('opacity-50');
                openEvidenceModal(id, status);
            }
        }

        function openEvidenceModal(memberId, status) {
            compressedEvidenceModalBlob = null;
            $('#modal-member-id').val(memberId);
            $('#modal-status').val(status);
            $('#evidence-status-label').text(status.toUpperCase());
            $('#evidence-modal').removeClass('hidden').addClass('flex');
            setTimeout(() => $('#evidence-content').removeClass('scale-95 opacity-0').addClass('scale-100 opacity-100'), 10);
        }

        function closeEvidenceModal() {
            compressedEvidenceModalBlob = null;
            $('#evidence-content').removeClass('scale-100 opacity-100').addClass('scale-95 opacity-0');
            setTimeout(() => $('#evidence-modal').addClass('hidden').removeClass('flex'), 300);
            $('#evidence-form')[0].reset();
        }

        function submitEvidence() {
            const form = document.getElementById('evidence-form');
            const formData = new FormData(form);

            if (compressedEvidenceModalBlob) {
                formData.set('evidence', compressedEvidenceModalBlob, 'evidence.jpg');
            }

            $('#btn-submit-evidence').text('MEMPROSES...').attr('disabled', true);

            $.ajax({
                url: '/live-scanner/{{ $meeting->uid }}/manual',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function (res) {
                    showFeedback(res.status, res.message);
                    if (res.status === 'success') {
                        playAudio('success');
                        closeEvidenceModal();

                        const status = formData.get('status');
                        const hasEvidence = formData.has('evidence') && formData.get('evidence').size > 0;
                        addToTable(res.name, res.code, res.time, 'MANUAL', status, hasEvidence, res.is_late, res.is_supervisor_badge);

                        $('#btn-submit-evidence').text('SIMPAN').attr('disabled', false);
                    } else {
                        $('#btn-submit-evidence').text('SIMPAN').attr('disabled', false);
                    }
                },
                error: function (xhr) {
                    $('#btn-submit-evidence').text('SIMPAN').attr('disabled', false);
                    const msg = xhr.responseJSON ? xhr.responseJSON.message : 'Terjadi kesalahan sistem.';
                    showFeedback('error', msg);
                }
            });
        }

        function submitAttendance(memberId, status, $btn = null, originalText = '') {
            $.post('/live-scanner/{{ $meeting->uid }}/manual', {
                _token: $('meta[name="csrf-token"]').attr('content'),
                member_id: memberId,
                status: status
            }, (res) => {
                showFeedback(res.status, res.message);
                if (res.status === 'success') {
                    playAudio('success');
                    addToTable(res.name, res.code, res.time, 'MANUAL', status, false, res.is_late, res.is_supervisor_badge);
                    if ($btn) $btn.text(originalText).attr('disabled', false).removeClass('opacity-50');
                } else {
                    playAudio('error');
                    if ($btn) $btn.text(originalText).attr('disabled', false).removeClass('opacity-50');
                }
            }).fail(() => {
                if ($btn) $btn.text(originalText).attr('disabled', false).removeClass('opacity-50');
                showFeedback('error', 'Terjadi kesalahan jaringan.');
            });
        }

        function startScanning() {
            if (!window.isSecureContext && location.hostname !== "localhost") {
                alert('ERROR: Kamera hanya bisa diakses melalui protokol HTTPS (Secure Context) atau Localhost. Silakan gunakan SSL atau setting chrome://flags.');
                return;
            }

            $('#start-overlay').addClass('opacity-0 pointer-events-none scale-110');
            $('#scanner-line').removeClass('hidden');
            $('#btn-stop-camera').removeClass('hidden');

            html5QrCode = new Html5Qrcode("reader");
            const config = { fps: 60 };

            html5QrCode.start({ facingMode: "environment" }, config,
                (decodedText) => {
                    if (isProcessing) return;
                    processAttendance(decodedText);
                },
                (errorMessage) => { }
            ).then(() => {
                // Auto-detect camera type to apply mirror effect only on front camera
                const videoElement = document.querySelector('#reader video');
                if (videoElement && videoElement.srcObject) {
                    const track = videoElement.srcObject.getVideoTracks()[0];
                    const settings = track.getSettings();
                    // Mirror only if front camera (user)
                    if (settings.facingMode === 'user') {
                        $(videoElement).addClass('mirror-mode');
                    } else {
                        $(videoElement).removeClass('mirror-mode');
                    }
                }
            }).catch(err => {
                console.error(err);
                alert('GAGAL AKSES KAMERA:\n1. Pastikan browser diizinkan akses kamera.\n2. Gunakan HTTPS (Bukan HTTP).\n3. Pastikan tidak ada aplikasi lain yang memakai kamera.');
                resetScannerUI();
            });
        }

        function stopScanning() {
            if (html5QrCode) {
                if (html5QrCode.isScanning) {
                    html5QrCode.stop().then(() => {
                        try { html5QrCode.clear(); } catch (e) {}
                        resetScannerUI();
                        html5QrCode = null;
                    }).catch(err => {
                        console.error("Gagal menghentikan kamera", err);
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

        window.addEventListener('pagehide', function () {
            stopScanning();
        });

        function resetScannerUI() {
            $('#start-overlay').removeClass('opacity-0 pointer-events-none scale-110');
            $('#scanner-line').addClass('hidden');
            $('#btn-stop-camera').addClass('hidden');
        }

        function processAttendance(code) {
            isProcessing = true;
            $.post('/live-scanner/{{ $meeting->uid }}/process', {
                _token: $('meta[name="csrf-token"]').attr('content'),
                code: code
            }, function (res) {
                showFeedback(res.status, res.message);
                if (res.status === 'success') {
                    playAudio('success');
                    addToTable(res.name, code, res.time, 'QR CODE', 'hadir', false, res.is_late, res.is_supervisor_badge);
                } else {
                    playAudio('error');
                }
                setTimeout(() => { isProcessing = false; }, 3500);
            }).fail(() => {
                isProcessing = false;
            });
        }

        function addToTable(name, code, time, method, status = 'hadir', hasEvidence = false, isLate = false, isSupervisorBadge = false) {
            $('#empty-row').hide();
            count++;
            $('#attendee-count').text(count + ' Anggota');

            const statusColors = {
                hadir: 'text-emerald-400 border-emerald-500/20 bg-emerald-500/5',
                izin: 'text-amber-400 border-amber-500/20 bg-amber-500/5',
                sakit: 'text-red-400 border-red-500/20 bg-red-500/5'
            };

            const statusColor = statusColors[status] || 'text-gray-500 border-white/10 bg-white/5';

            const row = `
                <tr class="animate-in slide-in-from-right-10 duration-700 bg-emerald-500/10">
                    <td class="px-4 py-4 md:px-8 md:py-5">
                        <div class="flex items-center gap-2">
                            <div class="font-black text-xs md:text-sm uppercase tracking-tight text-emerald-400 truncate max-w-[120px] md:max-w-none">${name}</div>
                            ${isSupervisorBadge ? '<span class="inline-block text-[8px] font-black bg-blue-500/20 text-blue-400 px-1.5 py-0.5 rounded border border-blue-500/30 uppercase tracking-tighter">PENGURUS</span>' : ''}
                        </div>
                        <div class="text-[9px] md:text-[10px] text-gray-500 font-bold uppercase mt-1 tracking-widest">${code}</div>
                    </td>
                    <td class="px-4 py-4 md:px-8 md:py-5 font-black text-[10px] md:text-xs text-gray-200 whitespace-nowrap">${time}</td>
                    <td class="px-4 py-4 md:px-8 md:py-5 text-right">
                        <div class="flex flex-col items-end gap-1.5">
                            <div class="flex items-center gap-1.5">
                                ${isLate ? '<span class="inline-block text-[8px] md:text-[9px] font-black border border-rose-500/20 bg-rose-500/10 text-rose-400 px-2 py-0.5 rounded-md uppercase tracking-tighter">TERLAMBAT</span>' : ''}
                                <span class="inline-block text-[8px] md:text-[9px] font-black border ${statusColor} px-2 py-0.5 md:px-3 md:py-1 rounded-full uppercase tracking-tighter">${status.toUpperCase()}</span>
                            </div>
                            <div class="flex items-center justify-end gap-2 text-[8px] font-bold text-gray-600">
                                ${hasEvidence ? '<div class="px-1.5 py-0.5 bg-white/5 border border-white/10 rounded-md text-gray-400 uppercase tracking-tighter">LAMPIRAN</div>' : ''}
                                <div class="hidden md:block uppercase tracking-widest">${method.replace('_', ' ').toUpperCase()}</div>
                            </div>
                        </div>
                    </td>
                </tr>
            `;
            $('#attendance-list').prepend(row);
        }

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

            setTimeout(() => {
                $('#feedback-card').removeClass('scale-90 opacity-0').addClass('scale-100 opacity-100');
            }, 50);

            setTimeout(() => {
                $('#feedback-card').removeClass('scale-100 opacity-100').addClass('scale-90 opacity-0');
                setTimeout(() => { $('#feedback').addClass('hidden'); }, 500);
            }, 3000);
        }

        function openTempRegModal() {
            $('#temp-reg-modal').removeClass('hidden').addClass('flex');
            setTimeout(() => $('#temp-reg-content').removeClass('scale-95 opacity-0').addClass('scale-100 opacity-100'), 10);
        }

        function closeTempRegModal() {
            compressedTempRegPhotoBlob = null;
            $('#temp-reg-content').removeClass('scale-100 opacity-100').addClass('scale-95 opacity-0');
            setTimeout(() => $('#temp-reg-modal').addClass('hidden').removeClass('flex'), 300);
            $('#temp-reg-form')[0].reset();
        }

        function submitTempReg() {
            const form = document.getElementById('temp-reg-form');
            const formData = new FormData(form);

            if (compressedTempRegPhotoBlob) {
                formData.set('photo', compressedTempRegPhotoBlob, 'photo.jpg');
            }

            $('#btn-submit-temp-reg').text('MEMPROSES...').attr('disabled', true);

            $.ajax({
                url: '/live-scanner/{{ $meeting->uid }}/register-temporary',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function (res) {
                    if (res.status === 'success') {
                        closeTempRegModal();
                        // Langsung absen HADIR
                        submitAttendance(res.member.id, 'hadir');
                    } else {
                        showFeedback('error', res.message || 'Gagal mendaftar.');
                        $('#btn-submit-temp-reg').text('DAFTAR & HADIR').attr('disabled', false);
                    }
                },
                error: function (xhr) {
                    $('#btn-submit-temp-reg').text('DAFTAR & HADIR').attr('disabled', false);
                    const msg = xhr.responseJSON ? xhr.responseJSON.message : 'Terjadi kesalahan sistem.';
                    showFeedback('error', msg);
                }
            });
        }

        function playAudio(type) {
            const audio = document.getElementById('audio-' + type);
            if (audio) { audio.currentTime = 0; audio.play().catch(e => { }); }
        }
    </script>
</body>

</html>