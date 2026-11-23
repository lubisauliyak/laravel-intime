@php
    $errors = $getValidationErrors ?? [];
    $isValid = $getIsValid ?? false;
    $validCount = $getValidCount ?? 0;
    $isChecking = $getIsChecking ?? false;
@endphp

@if($isChecking)
    <div class="mt-4 p-4 rounded-lg bg-gray-50 text-gray-600 border border-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-700">
        <div class="flex items-center gap-2 font-medium">
            <x-filament::loading-indicator class="h-5 w-5" />
            <span>Sedang memeriksa file Excel...</span>
        </div>
    </div>
@elseif(count($errors) > 0)
    <div class="mt-4 p-4 rounded-lg bg-danger-50 text-danger-600 border border-danger-200 dark:bg-danger-500/10 dark:text-danger-400 dark:border-danger-500/20">
        <div class="flex items-center gap-2 font-medium mb-2">
            <x-heroicon-m-exclamation-circle class="w-5 h-5" />
            <span>Pemeriksaan Gagal (Ditemukan {{ count($errors) }} error)</span>
        </div>
        <ul class="list-disc pl-5 text-sm space-y-1 mt-2 max-h-40 overflow-y-auto">
            @foreach($errors as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <div class="mt-3 text-xs opacity-80">
            Perbaiki data di file Excel Anda berdasarkan catatan di atas, kemudian unggah kembali.
        </div>
    </div>
@elseif($isValid && $validCount > 0)
    <div class="mt-4 p-4 rounded-lg bg-success-50 text-success-600 border border-success-200 dark:bg-success-500/10 dark:text-success-400 dark:border-success-500/20">
        <div class="flex items-center gap-2 font-medium">
            <x-heroicon-m-check-circle class="w-5 h-5" />
            <span>Data Aman! Siap untuk diimpor.</span>
        </div>
        <div class="text-sm mt-1">
            {{ $validCount }} baris data siap diproses. Silakan klik tombol <b>Kirim</b> di bawah.
        </div>
    </div>
@endif
