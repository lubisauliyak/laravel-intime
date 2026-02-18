<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Scanner Presensi: {{ $meeting->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap');
        body { font-family: 'Outfit', sans-serif; }
        .glass { background: rgba(255, 255, 255, 0.05); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.1); }
        .scan-line { animation: scan 2s linear infinite; }
        @keyframes scan { 0% { top: 0; } 100% { top: 100%; } }
        /* Select2 Dark Mode Fixes */
        .select2-container--default .select2-selection--single { background: #1f2937; border-color: #374151; height: 42px; color: white; }
        .select2-container--default .select2-selection--single .select2-selection__rendered { color: white; line-height: 40px; }
        .select2-dropdown { background: #1f2937; border-color: #374151; color: white; }
        .select2-search__field { background: #111827 !important; color: white; }
        .custom-scrollbar::-webkit-scrollbar { width: 5px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }
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

<body class="bg-[#0a0a0c] text-white min-h-screen overflow-x-hidden">
    <!-- Navbar -->
    <nav class="p-4 md:p-6 flex items-center justify-between border-b border-white/5 bg-black/40 sticky top-0 z-50">
        <div class="flex items-center gap-3 md:gap-4 overflow-hidden">
            <a href="/admin/meetings/{{ $meeting->id }}" class="p-2 hover:bg-white/10 rounded-xl transition-all shrink-0">
                <svg class="w-5 h-5 md:w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M15 19l-7-7 7-7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </a>
            <div class="truncate">
                <h1 class="text-base md:text-lg font-extrabold tracking-tight truncate">{{ $meeting->name }}</h1>
                <p class="text-[9px] md:text-[10px] text-gray-500 uppercase tracking-widest truncate">{{ $meeting->group->name }} • {{ $meeting->meeting_date->format('d M Y') }} • {{ $meeting->start_time->format('H:i') }}-{{ $meeting->end_time->format('H:i') }}@if($hasCheckinTime) • Presensi {{ $meeting->checkin_open_time->format('H:i') }}@endif</p>
            </div>
        </div>
        <div class="flex items-center gap-2 md:gap-3 shrink-0">
            @if($isFinished)
                <div class="flex items-center gap-2 px-3 py-1 md:px-4 md:py-1.5 bg-rose-500/10 border border-rose-500/20 rounded-full">
                    <div class="w-1.5 h-1.5 rounded-full bg-rose-500"></div>
                    <span class="text-[9px] md:text-[10px] font-black text-rose-400 uppercase tracking-widest">Sesi Berakhir</span>
                </div>
            @elseif($isBeforeCheckin)
                <div class="flex items-center gap-2 px-3 py-1 md:px-4 md:py-1.5 bg-amber-500/10 border border-amber-500/20 rounded-full text-nowrap">
                    <div class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></div>
                    <span class="text-[9px] md:text-[10px] font-black text-amber-400 uppercase tracking-widest">Presensi Dimulai {{ $effectiveOpenTime->format('H:i') }}</span>
                </div>
            @else
                <div class="flex items-center gap-2 px-3 py-1 md:px-4 md:py-1.5 bg-emerald-500/10 border border-emerald-500/20 rounded-full text-nowrap">
                    <div class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></div>
                    <span class="text-[9px] md:text-[10px] font-black text-emerald-400 uppercase tracking-widest">Berlangsung</span>
                </div>
            @endif
        </div>
    </nav>

    <main class="max-w-7xl mx-auto p-4 md:p-8">
        <div class="grid grid-cols-1 {{ $canScan ? 'lg:grid-cols-12' : ($isBeforeCheckin ? 'lg:grid-cols-12' : '') }} gap-8 md:gap-12 items-start">
            
            @if($isBeforeCheckin)
            {{-- Waiting Card: Presensi belum dimulai --}}
            <div class="lg:col-span-5 animate-in fade-in duration-1000">
                <div class="glass p-10 md:p-14 rounded-2xl border-white/5 relative overflow-hidden text-center shadow-2xl">
                    <div class="relative mb-8">
                        <div class="absolute -inset-6 bg-amber-500/20 blur-2xl rounded-full animate-pulse"></div>
                        <div class="relative w-24 h-24 mx-auto bg-amber-500/20 rounded-[2rem] flex items-center justify-center shadow-2xl border border-amber-500/30">
                            <svg class="w-12 h-12 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </div>
                    </div>
                    <h3 class="text-2xl font-black mb-3 tracking-tighter uppercase text-amber-400">Menunggu Presensi</h3>
                    <p class="text-gray-400 text-sm mb-6 max-w-xs mx-auto leading-relaxed font-medium">Presensi akan dibuka pada jam <span class="text-amber-400 font-black">{{ $effectiveOpenTime->format('H:i') }}</span></p>
                    <div class="inline-flex items-center gap-2 px-5 py-2.5 bg-amber-500/10 border border-amber-500/20 rounded-full">
                        <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" stroke-width="2"/></svg>
                        <span class="text-[10px] font-black text-amber-400 uppercase tracking-widest">Pertemuan {{ $meeting->start_time->format('H:i') }} - {{ $meeting->end_time->format('H:i') }}</span>
                    </div>
                </div>
            </div>
            @elseif($canScan)
            <!-- Area Scanner (Kiri) -->
            <div class="lg:col-span-5 space-y-8 animate-in fade-in duration-1000">
                <!-- Camera Container -->
                <div class="relative group">
                    <div class="absolute -inset-1 bg-gradient-to-r from-emerald-500 to-blue-500 rounded-2xl blur opacity-20 group-hover:opacity-40 transition duration-1000"></div>
                    <div id="reader-container" class="relative bg-black rounded-2xl aspect-square overflow-hidden border-8 border-white/5 shadow-2xl">
                        <div id="reader" style="width: 100%; height: 100%;"></div>
                        
                        <!-- HUD Overlay -->
                        <div class="absolute inset-0 pointer-events-none z-10 flex flex-col justify-between p-6 md:p-10">
                            <div class="flex justify-between">
                                <div class="w-10 h-10 md:w-14 h-14 border-t-4 border-l-4 border-emerald-500 rounded-tl-2xl"></div>
                                <div class="w-10 h-10 md:w-14 h-14 border-t-4 border-r-4 border-emerald-500 rounded-tr-2xl"></div>
                            </div>
                            <!-- Scanner Line -->
                            <div id="scanner-line" class="absolute left-6 right-6 md:left-10 md:right-10 h-0.5 bg-emerald-500/50 shadow-[0_0_20px_rgba(16,185,129,0.8)] scan-line hidden"></div>
                            
                            <div class="flex justify-between">
                                <div class="w-10 h-10 md:w-14 h-14 border-b-4 border-l-4 border-emerald-500 rounded-bl-2xl"></div>
                                <div class="w-10 h-10 md:w-14 h-14 border-b-4 border-r-4 border-emerald-500 rounded-br-2xl"></div>
                            </div>
                        </div>

                        <!-- Stop Camera Button -->
                        <button id="btn-stop-camera" onclick="stopScanning()" class="absolute top-8 right-8 z-30 px-6 py-2.5 bg-rose-500/90 hover:bg-rose-600 text-white font-black rounded-2xl shadow-xl transition-all active:scale-95 uppercase tracking-tighter text-xs hidden backdrop-blur-md border border-white/10">
                            Tutup Kamera
                        </button>

                        <!-- Start Button Overlay -->
                        <div id="start-overlay" class="absolute inset-0 bg-gray-950/90 backdrop-blur-xl flex flex-col items-center justify-center p-12 text-center z-20 transition-all duration-700">
                            <div class="relative mb-8">
                                <div class="absolute -inset-6 bg-emerald-500/20 blur-2xl rounded-full animate-pulse"></div>
                                <div class="relative w-24 h-24 bg-emerald-500 rounded-[2rem] flex items-center justify-center shadow-2xl shadow-emerald-500/40 rotate-12 group-hover:rotate-0 transition-transform duration-500">
                                    <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </div>
                            </div>
                            <h3 class="text-2xl font-black mb-3 tracking-tighter uppercase">Scanner Siap</h3>
                            <p class="text-gray-400 text-sm mb-8 max-w-xs leading-relaxed font-medium">Klik tombol di bawah untuk mulai memindai Kode QR.</p>
                            <button onclick="startScanning()" class="w-full px-8 py-4 bg-white text-black font-black rounded-2xl hover:bg-emerald-500 hover:text-white transition-all shadow-xl active:scale-95 uppercase tracking-tighter text-base">AKTIFKAN KAMERA</button>
                        </div>
                    </div>
                </div>

                <!-- Manual Input Card -->
                <div class="glass p-8 rounded-2xl border-white/5 relative overflow-hidden group shadow-2xl">
                    <div class="absolute top-0 right-0 p-8 opacity-5 -mr-10 -mt-10 group-hover:rotate-12 transition-transform duration-1000">
                        <svg class="w-32 h-32" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" stroke-width="2"/></svg>
                    </div>
                    <div class="flex items-center gap-4 mb-6 relative z-10">
                        <div class="p-3 bg-blue-500/20 rounded-2xl text-blue-400 shadow-inner">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" stroke-width="2.5"/></svg>
                        </div>
                        <h2 class="text-xl font-black uppercase tracking-tighter">Cari Manual</h2>
                    </div>
                    <div class="space-y-5 relative z-10">
                        <select id="manual-search" class="w-full"></select>
                        @php
                            $canSetExcused = auth()->user()->can('SetExcusedAttendance');
                        @endphp
                        <div class="grid {{ $canSetExcused ? 'grid-cols-3' : '' }} gap-3">
                            <button onclick="recordManual('hadir')" class="py-4 md:py-3 bg-emerald-500/20 hover:bg-emerald-500 text-emerald-400 hover:text-white font-black rounded-xl transition-all border border-emerald-500/30 text-[11px] md:text-[10px] uppercase tracking-tighter">HADIR</button>
                            @if($canSetExcused)
                            <button onclick="recordManual('izin')" class="py-4 md:py-3 bg-amber-500/20 hover:bg-amber-500 text-amber-400 hover:text-white font-black rounded-xl transition-all border border-amber-500/30 text-[11px] md:text-[10px] uppercase tracking-tighter">IZIN</button>
                            <button onclick="recordManual('sakit')" class="py-4 md:py-3 bg-red-500/20 hover:bg-red-500 text-red-400 hover:text-white font-black rounded-xl transition-all border border-red-500/30 text-[11px] md:text-[10px] uppercase tracking-tighter">SAKIT</button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <!-- Tabel Presensi -->
            <div class="{{ $isFinished ? 'max-w-4xl mx-auto w-full' : 'lg:col-span-7' }} space-y-8 animate-in slide-in-from-bottom-6 duration-1000">
                <div class="flex items-center justify-between px-2">
                    <div class="flex items-center gap-3 md:gap-4">
                        <h2 class="text-2xl md:text-3xl font-black tracking-tighter">DAFTAR HADIR</h2>
                        <span id="attendee-count" class="px-3 py-1 md:px-4 md:py-1.5 bg-white/10 rounded-full text-[10px] md:text-xs font-black text-gray-400 uppercase tracking-widest">{{ count($attendances) }} Anggota</span>
                    </div>
                    <button onclick="location.reload()" class="p-3 hover:bg-white/10 rounded-2xl text-gray-500 hover:text-white transition-all group">
                        <svg class="w-6 h-6 group-hover:rotate-180 transition-transform duration-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" stroke-width="2.5" stroke-linecap="round"/></svg>
                    </button>
                </div>

                <div class="glass rounded-2xl overflow-hidden border-white/5 shadow-[0_32px_64px_-12px_rgba(0,0,0,0.8)]">
                    <div class="max-h-[70vh] overflow-y-auto custom-scrollbar">
                        <table class="w-full text-left border-collapse">
                            <thead class="sticky top-0 z-10 glass backdrop-blur-3xl">
                                <tr class="bg-black/40 text-[9px] md:text-[10px] font-black uppercase tracking-[0.2em] text-gray-500">
                                    <th class="px-4 py-4 md:px-8 md:py-5">Identitas Anggota</th>
                                    <th class="px-4 py-4 md:px-8 md:py-5">Waktu</th>
                                    <th class="px-4 py-4 md:px-8 md:py-5 text-right">Status</th>
                                </tr>
                            </thead>
                            <tbody id="attendance-list" class="divide-y divide-white/5">
                                @forelse($attendances as $attendance)
                                    <tr class="hover:bg-white/[0.02] transition-colors">
                                        <td class="px-4 py-4 md:px-8 md:py-5">
                                            <div class="font-black text-xs md:text-sm uppercase tracking-tight text-emerald-400 truncate max-w-[120px] md:max-w-none">{{ $attendance->member->full_name }}</div>
                                            <div class="text-[9px] md:text-[10px] text-gray-500 font-bold uppercase mt-1 tracking-widest">{{ $attendance->member->member_code }}</div>
                                        </td>
                                        <td class="px-4 py-4 md:px-8 md:py-5 font-black text-[10px] md:text-xs text-gray-400 whitespace-nowrap">{{ $attendance->checkin_time?->format('H:i') ?? '-' }}</td>
                                        <td class="px-4 py-4 md:px-8 md:py-5 text-right">
                                            @php
                                                $statusColor = match($attendance->status) {
                                                    'hadir' => 'text-emerald-400 border-emerald-500/20 bg-emerald-500/5',
                                                    'izin' => 'text-amber-400 border-amber-500/20 bg-amber-500/5',
                                                    'sakit' => 'text-red-400 border-red-500/20 bg-red-500/5',
                                                    default => 'text-gray-500 border-white/10 bg-white/5',
                                                };
                                                $isLate = str_contains(strtoupper($attendance->notes ?? ''), 'TERLAMBAT');
                                                // Bukti shows if there is a file OR notes that are NOT just "TERLAMBAT"
                                                $hasRealNotes = $attendance->notes && strtoupper($attendance->notes) !== 'TERLAMBAT';
                                                $hasEvidence = $attendance->evidence_path || $hasRealNotes;
                                            @endphp
                                            <div class="flex flex-col items-end gap-1.5">
                                                <div class="flex items-center gap-1.5">
                                                    @if($isLate)
                                                        <span class="inline-block text-[8px] md:text-[9px] font-black border border-rose-500/20 bg-rose-500/10 text-rose-400 px-2 py-0.5 rounded-md uppercase tracking-tighter">TERLAMBAT</span>
                                                    @endif
                                                    <span class="inline-block text-[8px] md:text-[9px] font-black border {{ $statusColor }} px-2 py-0.5 md:px-3 md:py-1 rounded-full uppercase tracking-tighter">{{ $attendance->status ?? 'HADIR' }}</span>
                                                </div>
                                                <div class="flex items-center justify-end gap-2 text-[8px] font-bold text-gray-600">
                                                    @if($hasEvidence)
                                                        <div class="px-1.5 py-0.5 bg-white/5 border border-white/10 rounded-md text-gray-400 uppercase tracking-tighter">LAMPIRAN</div>
                                                    @endif
                                                    <div class="hidden md:block uppercase tracking-widest">{{ str_replace('_', ' ', $attendance->method) }}</div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr id="empty-row">
                                        <td colspan="3" class="px-8 py-24 text-center">
                                            <div class="opacity-10 flex flex-col items-center">
                                                <svg class="w-20 h-20 mb-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" stroke-width="1"/></svg>
                                                <p class="text-xs font-black uppercase tracking-[0.3em]">Belum ada aktivitas presensi</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="flex justify-center p-4">
                    <p class="text-[10px] text-gray-500 font-bold uppercase tracking-[0.2em]">Sistem Presensi Real-time • inTime v1.0</p>
                </div>
            </div>
        </div>
    </main>

    <!-- Global Floating Feedback -->
    <div id="feedback" class="fixed bottom-12 left-1/2 -translate-x-1/2 z-[100] pointer-events-none hidden max-w-sm w-full px-6">
        <div id="feedback-card" class="bg-emerald-500 text-white p-6 rounded-2xl shadow-[0_32px_64px_-12px_rgba(0,0,0,0.6)] flex items-center gap-5 border border-white/20 transform transition-all duration-500 scale-90 opacity-0">
            <div id="feedback-icon" class="w-14 h-14 bg-white/20 rounded-3xl flex items-center justify-center shrink-0 border border-white/10 shadow-inner">
                <svg id="icon-success" class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <svg id="icon-warning" class="w-8 h-8 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 9v2m0 4h.01M3 12a9 9 0 1118 0 9 9 0 01-18 0z" stroke-width="2.5"/></svg>
            </div>
            <div>
                <h4 id="feedback-title" class="font-black text-lg uppercase tracking-tighter leading-none mb-1">Status</h4>
                <p id="feedback-message" class="text-sm font-bold opacity-80 leading-snug"></p>
            </div>
        </div>
    </div>

    <!-- Audio Effects -->
    <audio id="audio-success" src="https://assets.mixkit.co/active_storage/sfx/2354/2354-preview.mp3" preload="auto"></audio>
    <audio id="audio-error" src="https://assets.mixkit.co/active_storage/sfx/2571/2571-preview.mp3" preload="auto"></audio>

    <!-- Evidence Modal -->
    <div id="evidence-modal" class="fixed inset-0 z-[100] hidden items-center justify-center p-4 bg-black/80 backdrop-blur-md">
        <div id="evidence-content" class="glass max-w-md w-full p-8 rounded-2xl border-white/10 transform transition-all duration-300 scale-95 opacity-0">
            <div class="flex items-center gap-4 mb-8">
                <div class="p-4 bg-blue-500/20 rounded-2xl text-blue-400">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>
                <div>
                    <h3 class="text-2xl font-black uppercase tracking-tighter">Lampiran <span id="evidence-status-label" class="text-amber-400"></span></h3>
                    <p class="text-xs text-gray-500 font-bold uppercase tracking-widest mt-1">Unggah bukti foto atau keterangan</p>
                </div>
            </div>

            <form id="evidence-form" method="POST" enctype="multipart/form-data" class="space-y-6">
                @csrf
                <input type="hidden" id="modal-member-id" name="member_id">
                <input type="hidden" id="modal-status" name="status">
                
                <div class="space-y-2">
                    <label class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-500 ml-1">Foto Bukti (Opsional)</label>
                    <div class="relative group">
                        <input type="file" name="evidence" accept="image/*" class="w-full text-xs text-gray-500 file:mr-4 file:py-3 file:px-6 file:rounded-2xl file:border-0 file:text-[10px] file:font-black file:uppercase file:bg-white/5 file:text-white hover:file:bg-white/10 transition-all cursor-pointer bg-white/5 rounded-2xl p-2 border border-white/5">
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-500 ml-1">Keterangan Tambahan</label>
                    <textarea name="notes" rows="3" class="w-full bg-white/5 border border-white/5 rounded-2xl p-4 text-sm focus:ring-2 focus:ring-emerald-500 outline-none transition-all placeholder:text-white/10" placeholder="Contoh: Surat Izin, Sedang di luar kota, dll..."></textarea>
                </div>

                <div class="grid grid-cols-2 gap-4 pt-2">
                    <button type="button" onclick="closeEvidenceModal()" class="py-4 bg-white/5 hover:bg-white/10 text-white font-black rounded-2xl transition-all uppercase tracking-tighter">BATAL</button>
                    <button type="button" id="btn-submit-evidence" onclick="submitEvidence()" class="py-4 bg-emerald-500 hover:bg-emerald-600 text-white font-black rounded-2xl shadow-xl shadow-emerald-500/20 transition-all uppercase tracking-tighter">SIMPAN</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let html5QrCode;
        let isProcessing = false;
        let count = {{ count($attendances) }};

        $(document).ready(function() {
            const $manualSearch = $('#manual-search');
            $manualSearch.select2({
                placeholder: "-- Cari Nama atau Kode --",
                minimumInputLength: 2,
                ajax: {
                    url: '/live-scanner/{{ $meeting->id }}/search',
                    dataType: 'json',
                    delay: 250,
                    data: function(params) { return { q: params.term }; },
                    processResults: function(data) { return { results: data }; },
                    cache: true
                }
            });

        });

        function recordManual(status) {
            const id = $('#manual-search').val();
            const $btn = $(event.currentTarget);
            
            if(!id) {
                showFeedback('warning', 'Pilih anggota terlebih dahulu.');
                return;
            }

            // Simpan teks asli
            const originalText = $btn.text();
            $btn.text('...').attr('disabled', true).addClass('opacity-50');

            if(status === 'hadir') {
                submitAttendance(id, 'hadir', $btn, originalText);
            } else {
                $btn.text(originalText).attr('disabled', false).removeClass('opacity-50');
                openEvidenceModal(id, status);
            }
        }

        function openEvidenceModal(memberId, status) {
            $('#modal-member-id').val(memberId);
            $('#modal-status').val(status);
            $('#evidence-status-label').text(status.toUpperCase());
            $('#evidence-modal').removeClass('hidden').addClass('flex');
            setTimeout(() => $('#evidence-content').removeClass('scale-95 opacity-0').addClass('scale-100 opacity-100'), 10);
        }

        function closeEvidenceModal() {
            $('#evidence-content').removeClass('scale-100 opacity-100').addClass('scale-95 opacity-0');
            setTimeout(() => $('#evidence-modal').addClass('hidden').removeClass('flex'), 300);
            $('#evidence-form')[0].reset();
        }

        function submitEvidence() {
            const form = document.getElementById('evidence-form');
            const formData = new FormData(form);
            
            $('#btn-submit-evidence').text('MEMPROSES...').attr('disabled', true);

            $.ajax({
                url: '/live-scanner/{{ $meeting->id }}/manual',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(res) {
                    showFeedback(res.status, res.message);
                    if(res.status === 'success') {
                        location.reload();
                    } else {
                        $('#btn-submit-evidence').text('SIMPAN').attr('disabled', false);
                    }
                },
                error: function(xhr) {
                    $('#btn-submit-evidence').text('SIMPAN').attr('disabled', false);
                    const msg = xhr.responseJSON ? xhr.responseJSON.message : 'Terjadi kesalahan sistem.';
                    showFeedback('error', msg);
                }
            });
        }

        function submitAttendance(memberId, status, $btn = null, originalText = '') {
            $.post('/live-scanner/{{ $meeting->id }}/manual', {
                _token: $('meta[name="csrf-token"]').attr('content'),
                member_id: memberId,
                status: status
            }, (res) => {
                showFeedback(res.status, res.message);
                if(res.status === 'success') {
                    location.reload();
                } else if($btn) {
                    $btn.text(originalText).attr('disabled', false).removeClass('opacity-50');
                }
            }).fail(() => {
                if($btn) $btn.text(originalText).attr('disabled', false).removeClass('opacity-50');
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
            const config = { fps: 60, qrbox: { width: 250, height: 250 } };

            html5QrCode.start({ facingMode: "environment" }, config,
                (decodedText) => {
                    if (isProcessing) return;
                    processAttendance(decodedText);
                },
                (errorMessage) => {}
            ).catch(err => {
                console.error(err);
                alert('GAGAL AKSES KAMERA:\n1. Pastikan browser diizinkan akses kamera.\n2. Gunakan HTTPS (Bukan HTTP).\n3. Pastikan tidak ada aplikasi lain yang memakai kamera.');
                resetScannerUI();
            });
        }

        function stopScanning() {
            if (html5QrCode) {
                html5QrCode.stop().then(() => {
                    html5QrCode.clear();
                    resetScannerUI();
                }).catch(err => {
                    console.error("Gagal menghentikan kamera", err);
                    resetScannerUI();
                });
            } else {
                resetScannerUI();
            }
        }

        function resetScannerUI() {
            $('#start-overlay').removeClass('opacity-0 pointer-events-none scale-110');
            $('#scanner-line').addClass('hidden');
            $('#btn-stop-camera').addClass('hidden');
        }

        function processAttendance(code) {
            isProcessing = true;
            $.post('/live-scanner/{{ $meeting->id }}/process', {
                _token: $('meta[name="csrf-token"]').attr('content'),
                code: code
            }, function(res) {
                showFeedback(res.status, res.message);
                if(res.status === 'success') {
                    playAudio('success');
                    addToTable(res.name, code, res.time, 'QR CODE', 'hadir', false, res.is_late);
                } else {
                    playAudio('error');
                }
                setTimeout(() => { isProcessing = false; }, 3500);
            }).fail(() => {
                isProcessing = false;
            });
        }

        function addToTable(name, code, time, method, status = 'hadir', hasEvidence = false, isLate = false) {
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
                        <div class="font-black text-xs md:text-sm uppercase tracking-tight text-emerald-400 truncate max-w-[120px] md:max-w-none">${name}</div>
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
            const titles = { success: 'MANTAP!', error: 'WADUH!', warning: 'EITTS!' };
            
            $('#feedback').removeClass('hidden');
            $('#feedback-card').attr('class', colors[status] + ' text-white p-6 rounded-[2.5rem] shadow-[0_32px_64px_-12px_rgba(0,0,0,0.6)] flex items-center gap-5 border border-white/20 transform transition-all duration-500');
            
            $('#feedback-title').text(titles[status]);
            $('#feedback-message').text(message);
            
            if(status === 'success') {
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

        function playAudio(type) {
            const audio = document.getElementById('audio-' + type);
            if (audio) { audio.currentTime = 0; audio.play().catch(e => {}); }
        }
    </script>
</body>
</html>
