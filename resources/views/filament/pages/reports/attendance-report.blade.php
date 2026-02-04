<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">Rekapitulasi Kehadiran Anggota</x-slot>
            <x-slot name="description">Halaman ini menampilkan statistik kehadiran anggota berdasarkan grup mereka.</x-slot>
            
            {{ $this->table }}
        </x-filament::section>
    </div>
</x-filament-panels::page>
