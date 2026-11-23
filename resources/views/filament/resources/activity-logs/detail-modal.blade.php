@php
    $properties = $record->properties ?? [];
    $ip = $properties['ip'] ?? '-';
    $userAgent = $properties['user_agent'] ?? '-';
    $event = $record->event;
    $eventBadgeColor = match($event) {
        'created' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
        'updated' => 'bg-sky-50 text-sky-700 dark:bg-sky-950/40 dark:text-sky-300 border-sky-200 dark:border-sky-800',
        'deleted' => 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300 border-rose-200 dark:border-rose-800',
        'restored' => 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300 border-amber-200 dark:border-amber-800',
        default => 'bg-gray-50 text-gray-700 dark:bg-gray-800 dark:text-gray-300 border-gray-200 dark:border-gray-700',
    };
    $eventLabel = match($event) {
        'created' => 'Dibuat',
        'updated' => 'Diperbarui',
        'deleted' => 'Dihapus',
        'restored' => 'Dipulihkan',
        default => ucfirst($event),
    };
    $moduleName = match(class_basename($record->subject_type)) {
        'Member' => 'Anggota',
        'Meeting' => 'Pertemuan',
        'Attendance' => 'Presensi',
        'Group' => 'Kelompok',
        'User' => 'Pengguna',
        default => class_basename($record->subject_type),
    };
@endphp

<div class="space-y-6 text-gray-800 dark:text-gray-200">
    <!-- Header Ringkasan Aktivitas -->
    <div class="p-4 bg-gray-50 dark:bg-white/[0.03] border border-gray-200 dark:border-white/10 rounded-2xl">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300 flex items-center justify-center font-bold text-sm shadow-sm">
                    {{ strtoupper(substr($record->causer_name ?? 'S', 0, 2)) }}
                </div>
                <div>
                    <div class="font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                        {{ $record->causer_name ?? 'Sistem' }}
                        <span class="text-xs px-2 py-0.5 rounded-full border {{ $eventBadgeColor }} font-medium">
                            {{ $eventLabel }}
                        </span>
                        <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 dark:bg-white/10 text-gray-600 dark:text-gray-300 font-medium">
                            Modul: {{ $moduleName }}
                        </span>
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        {{ \Carbon\Carbon::parse($record->created_at)->locale('id')->isoFormat('dddd, D MMMM Y • HH:mm:ss') }} WIB
                    </div>
                </div>
            </div>

            @if($record->group)
                <div class="text-xs text-right bg-white dark:bg-gray-800 px-3 py-1.5 rounded-xl border border-gray-200 dark:border-white/10">
                    <span class="text-gray-400 block">Wilayah / Kelompok:</span>
                    <span class="font-semibold text-gray-800 dark:text-gray-200">{{ $record->group->name }}</span>
                </div>
            @endif
        </div>

        <div class="mt-3 pt-3 border-t border-gray-200/60 dark:border-white/5">
            <div class="text-sm font-medium text-gray-900 dark:text-white">
                {{ $record->description }}
            </div>
        </div>
    </div>

    <!-- Lapisan 1: Tabel Perubahan Ramah Pengguna (Human-First) -->
    <div>
        <h4 class="text-sm font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2 flex items-center gap-2">
            <svg class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            Rincian Perubahan Data
        </h4>
        @include('filament.resources.activity-logs.diff-table', ['record' => $record])
    </div>

    <!-- Lapisan 2: Section Lipat Detail Teknis Developer (Collapsible) -->
    <div x-data="{ open: false }" class="border border-gray-200 dark:border-white/10 rounded-2xl overflow-hidden bg-gray-50/50 dark:bg-white/[0.01]">
        <button 
            type="button" 
            @click="open = !open" 
            class="w-full flex items-center justify-between p-4 text-left font-medium text-xs text-gray-600 dark:text-gray-300 hover:bg-gray-100/60 dark:hover:bg-white/[0.03] transition-colors"
        >
            <span class="flex items-center gap-2">
                <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                </svg>
                Informasi Teknis (Developer Inspector)
            </span>
            <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 text-gray-400 transform transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <div x-show="open" x-cloak class="p-4 pt-0 space-y-4 border-t border-gray-200/60 dark:border-white/5 bg-white dark:bg-gray-900">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs pt-3">
                <div class="p-2.5 rounded-lg bg-gray-50 dark:bg-white/[0.03] border border-gray-200/50 dark:border-white/5">
                    <span class="text-gray-400 block font-mono">Alamat IP:</span>
                    <span class="font-mono font-medium text-gray-800 dark:text-gray-200">{{ $ip }}</span>
                </div>
                <div class="p-2.5 rounded-lg bg-gray-50 dark:bg-white/[0.03] border border-gray-200/50 dark:border-white/5">
                    <span class="text-gray-400 block font-mono">Model / Subject ID:</span>
                    <span class="font-mono font-medium text-gray-800 dark:text-gray-200">{{ $record->subject_type }} (#{{ $record->subject_id }})</span>
                </div>
                <div class="p-2.5 rounded-lg bg-gray-50 dark:bg-white/[0.03] border border-gray-200/50 dark:border-white/5 col-span-1 md:col-span-2">
                    <span class="text-gray-400 block font-mono">User Agent Browser:</span>
                    <span class="font-mono text-gray-700 dark:text-gray-300 break-all">{{ $userAgent }}</span>
                </div>
            </div>

            <div>
                <span class="text-xs text-gray-400 block font-mono mb-1">Raw JSON Payload (Properties):</span>
                <pre class="p-3 bg-gray-900 text-emerald-400 text-xs rounded-xl overflow-x-auto font-mono max-h-60 border border-gray-800">{{ json_encode($properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
            </div>
        </div>
    </div>
</div>
