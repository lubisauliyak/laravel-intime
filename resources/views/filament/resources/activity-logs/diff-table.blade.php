@php
    $properties = $record->properties ?? [];
    $old = $properties['old'] ?? [];
    $attributes = $properties['attributes'] ?? [];
    $event = $record->event;
    $diff = \App\Support\ActivityLogDictionary::buildHumanDiff($record->subject_type, $old, $attributes);
@endphp

<div class="space-y-4">
    @if($event === 'updated')
        @if(count($diff) > 0)
            <div class="overflow-x-auto border border-gray-200 dark:border-white/10 rounded-xl shadow-sm">
                <table class="w-full text-left text-sm divide-y divide-gray-200 dark:divide-white/10">
                    <thead class="bg-gray-50 dark:bg-white/5 font-semibold text-gray-700 dark:text-gray-200">
                        <tr>
                            <th class="px-4 py-3 w-1/3">Data yang Berubah</th>
                            <th class="px-4 py-3 w-1/3 text-rose-600 dark:text-rose-400">Nilai Sebelumnya</th>
                            <th class="px-4 py-3 w-1/3 text-emerald-600 dark:text-emerald-400">Menjadi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5 bg-white dark:bg-gray-900">
                        @foreach($diff as $item)
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition-colors">
                                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">
                                    {{ $item['label'] }}
                                    <div class="text-xs text-gray-400 font-mono font-normal">{{ $item['field'] }}</div>
                                </td>
                                <td class="px-4 py-3 text-rose-700 dark:text-rose-300 bg-rose-50/30 dark:bg-rose-950/20 font-medium">
                                    <span class="inline-block px-2 py-0.5 rounded bg-rose-100 dark:bg-rose-900/50 text-xs">
                                        {{ $item['old'] }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-emerald-700 dark:text-emerald-300 bg-emerald-50/30 dark:bg-emerald-950/20 font-medium">
                                    <span class="inline-block px-2 py-0.5 rounded bg-emerald-100 dark:bg-emerald-900/50 text-xs">
                                        {{ $item['new'] }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="p-4 text-sm text-gray-500 bg-gray-50 dark:bg-white/5 rounded-xl text-center">
                Tidak ada perubahan atribut spesifik yang tercatat.
            </div>
        @endif

    @elseif($event === 'created')
        <div class="overflow-x-auto border border-gray-200 dark:border-white/10 rounded-xl shadow-sm">
            <table class="w-full text-left text-sm divide-y divide-gray-200 dark:divide-white/10">
                <thead class="bg-gray-50 dark:bg-white/5 font-semibold text-gray-700 dark:text-gray-200">
                    <tr>
                        <th class="px-4 py-3 w-1/3">Data / Atribut</th>
                        <th class="px-4 py-3 w-2/3 text-emerald-600 dark:text-emerald-400">Nilai Awal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5 bg-white dark:bg-gray-900">
                    @forelse($attributes as $key => $val)
                        <tr class="hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition-colors">
                            <td class="px-4 py-2.5 font-medium text-gray-900 dark:text-white">
                                {{ \App\Support\ActivityLogDictionary::getFieldLabel($record->subject_type, $key) }}
                                <div class="text-xs text-gray-400 font-mono font-normal">{{ $key }}</div>
                            </td>
                            <td class="px-4 py-2.5 text-gray-700 dark:text-gray-300 font-medium">
                                {{ \App\Support\ActivityLogDictionary::formatValue($record->subject_type, $key, $val) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="px-4 py-3 text-center text-gray-400">Data atribut awal tidak tersedia.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    @elseif($event === 'deleted')
        <div class="p-4 border border-rose-200 dark:border-rose-900/50 bg-rose-50/50 dark:bg-rose-950/20 rounded-xl space-y-3">
            <div class="flex items-center gap-2 text-rose-700 dark:text-rose-400 font-semibold text-sm">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
                Data Telah Dihapus
            </div>
            <p class="text-xs text-rose-600/80 dark:text-rose-400/80">
                Berikut adalah snapshot data terakhir sebelum objek ini dihapus dari sistem:
            </p>
            <div class="overflow-x-auto border border-rose-200 dark:border-rose-900/50 rounded-lg bg-white dark:bg-gray-900">
                <table class="w-full text-left text-sm divide-y divide-gray-100 dark:divide-white/5">
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @foreach($old as $key => $val)
                            <tr>
                                <td class="px-4 py-2 font-medium text-gray-900 dark:text-white w-1/3">
                                    {{ \App\Support\ActivityLogDictionary::getFieldLabel($record->subject_type, $key) }}
                                </td>
                                <td class="px-4 py-2 text-gray-600 dark:text-gray-300">
                                    {{ \App\Support\ActivityLogDictionary::formatValue($record->subject_type, $key, $val) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
