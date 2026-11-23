# Rencana Kerja & Checklist: Optimasi Performa, Konsistensi UX, Scope Otorisasi, & Kelengkapan Ekspor Data

Dokumen ini merupakan panduan teknis dan daftar periksa (*checklist*) lanjutan untuk menyempurnakan **performa query (eliminasi N+1)**, **keamanan ruang lingkup hierarki (*scoping*)**, **standarisasi komponen Filament Native**, serta **kelengkapan fitur ekspor-impor** pada aplikasi **inTime**.

---

## 1. Ringkasan Temuan & Sasaran Perbaikan

| Kategori | Area / Modul | Ringkasan Masalah | Target Solusi | Prioritas |
| :--- | :--- | :--- | :--- | :---: |
| **Scope & Otorisasi** | `MeetingPolicy`, `MeetingAttendanceDetails`, `GroupsTable` | 1. Pengurus/Admin sub-grup (Kelompok) bisa melihat detail kehadiran pertemuan induk (Daerah/Desa), namun tidak bisa mengekspor/mencetak data kelompoknya sendiri karena tertahan `canManageMeeting`.<br>2. Aksi massal (`BulkAction`) di tabel Grup belum memfilter batas wewenang per baris. | 1. Izinkan ekspor/cetak jika user memiliki irisan sub-grup di bawah grup penyelenggara pertemuan.<br>2. Terapkan filter otorisasi per baris + notifikasi transparan pada aksi massal Grup. | **Tinggi** |
| **Performa (Anti N+1)** | `GroupMemberActivity`, `GroupMemberRekapitulasiSheet`, `GroupsTable` | 1. Perhitungan statistik anggota mengeksekusi 3–4 query per baris (ratusan query dalam 1 halaman/ekspor).<br>2. Kolom `members_count` di tabel Grup melakukan query `getAllDescendantIds()` + `count()` berulang tiap baris. | 1. Batch pre-loading pertemuan & presensi cukup 1 kali, kalkulasi di memori.<br>2. Pre-load / agregasi jumlah anggota per grup secara efisien. | **Tinggi** |
| **Konsistensi UI (Filament Native)** | `ImportMembers`, `ImportGroups` | 1. Tombol Header (`Mulai Ulang`, `Kembali`) masih menggunakan teks biasa.<br>2. Tombol submit pada Wizard masih menggunakan `Blade::render('<x-filament::button ...>')` manual. | 1. Seragamkan seluruh tombol utilitas header menjadi `->iconButton()` + `->tooltip()`.<br>2. Ganti `Blade::render` dengan `Action` native Filament. | **Sedang** |
| **Kelengkapan Fitur Ekspor** | `ManageGroups`, `GroupsTable`, `GroupsRawExport` | Fitur Impor Grup dan permission `Export:Group` sudah tersedia, namun belum ada fitur **Ekspor Data Raw Grup** yang *round-trip ready*. | Buat `GroupsRawExport` + tombol header `export` (icon + tooltip + modal cakupan) dan `Ekspor Terpilih` di tabel Grup. | **Sedang** |

---

## 2. Pembagian Sprint Pengerjaan

### Sprint 1: Penguatan Scope Otorisasi & Keamanan Aksi Massal (Prioritas Tinggi)
**Tujuan:** Menutup celah inkonsistensi hak akses wilayah pada halaman pertemuan lintas tingkat serta mengamankan aksi massal pada tabel Grup.

- [x] **1.1 Penyelarasan Otorisasi Ekspor Pertemuan Lintas Tingkat pada [MeetingPolicy.php](file:///d:/SandBox/Antigravity/inTime/app/Policies/MeetingPolicy.php)**
  - [x] Tambahkan helper privat `canAccessMeetingScope(AuthUser $authUser, Meeting $meeting): bool` pada `MeetingPolicy`:
    * Mengembalikan `true` jika user adalah Super Admin, atau mengelola grup penyelenggara (`canManageMeeting`), **atau** grup wilayah user berada di bawah (merupakan *descendant* dari) grup penyelenggara pertemuan (`in_array($authUser->group_id, $meeting->group->getAllDescendantIds())`).
  - [x] Perbarui method `exportExcel()` dan `exportPdf()` di `MeetingPolicy` agar menggunakan `canAccessMeetingScope()` (mengingat query ekspor di [MeetingAttendanceDetails.php](file:///d:/SandBox/Antigravity/inTime/app/Filament/Resources/Meetings/Pages/MeetingAttendanceDetails.php#L65-L76) sudah otomatis membatasi data hanya untuk anggota di wilayah user via `getPermittedGroupIds()`).

- [x] **1.2 Proteksi Wilayah pada Aksi Massal Tabel Grup di [GroupsTable.php](file:///d:/SandBox/Antigravity/inTime/app/Filament/Resources/Groups/Tables/GroupsTable.php)**
  - [x] Perbarui `DeleteBulkAction` pada `GroupsTable.php`:
    * Filter koleksi menggunakan `auth()->user()->can('delete', $record)`.
    * Tampilkan `Notification::make()->warning()` jika terdapat baris grup yang dilewati karena di luar wilayah wewenang pengguna (selaras dengan pola di [MembersTable.php](file:///d:/SandBox/Antigravity/inTime/app/Filament/Resources/Members/Tables/MembersTable.php#L450-L465) dan [MeetingsTable.php](file:///d:/SandBox/Antigravity/inTime/app/Filament/Resources/Meetings/Tables/MeetingsTable.php#L117-L132)).
  - [x] Perbarui `RestoreBulkAction` pada `GroupsTable.php` dengan filter `auth()->user()->can('restore', $record)` + notifikasi parsial.
  - [x] Perbarui `ForceDeleteBulkAction` pada `GroupsTable.php` dengan filter `auth()->user()->can('forceDelete', $record)` + notifikasi parsial.
  - [x] Tambahkan safety guardrail pada [GroupPolicy.php](file:///d:/SandBox/Antigravity/inTime/app/Policies/GroupPolicy.php) agar pengguna non-Super Admin tidak dapat menghapus grup penugasannya sendiri.

---

### Sprint 2: Optimasi Performa & Eliminasi N+1 Query (Prioritas Tinggi)
**Tujuan:** Memangkas ratusan query berulang menjadi *batch query* tunggal agar pemuatan tabel dan pengunduhan Excel berjalan instan tanpa membebani database.

- [x] **2.1 Optimasi Batch Query pada Halaman [GroupMemberActivity.php](file:///d:/SandBox/Antigravity/inTime/app/Filament/Resources/Groups/Pages/GroupMemberActivity.php)**
  - [x] Simpan hasil `AgeGroup::count()` ke dalam properti cache halaman (cukup 1 query per request, bukan dipanggil di tiap baris anggota).
  - [x] Buat mekanisme *batch pre-loading* untuk daftar pertemuan relevan (`Meeting`) dan data kehadiran (`Attendance`) berdasarkan filter tanggal & tingkat pertemuan, sehingga `getMemberStats(Member $record)` menghitung statistik di memori (Collection) tanpa melakukan query SQL baru pada setiap baris tabel.

- [x] **2.2 Optimasi Batch Query pada Ekspor Excel [GroupMemberRekapitulasiSheet.php](file:///d:/SandBox/Antigravity/inTime/app/Exports/Sheets/GroupMemberRekapitulasiSheet.php) & [GroupMemberSummarySheet.php](file:///d:/SandBox/Antigravity/inTime/app/Exports/Sheets/GroupMemberSummarySheet.php)**
  - [x] Di `GroupMemberRekapitulasiSheet::map()`, hapus query `Meeting::whereIn(...)` dan `$member->attendances()->with('meeting')...` yang berjalan per baris anggota.
  - [x] Manfaatkan koleksi `$this->meetings` yang sudah diambil di constructor dan eager-load `attendances` dengan constraint filter tanggal/level langsung pada query utama di `collection()`.
  - [x] Di `GroupMemberSummarySheet`, lakukan pre-load data member aktif & attendance untuk seluruh grup turunan sekaligus sebelum iterasi baris grup.

- [x] **2.3 Optimasi Kolom Jumlah Anggota pada [GroupsTable.php](file:///d:/SandBox/Antigravity/inTime/app/Filament/Resources/Groups/Tables/GroupsTable.php) & Pohon Hierarki Grup [Group.php](file:///d:/SandBox/Antigravity/inTime/app/Models/Group.php)**
  - [x] Ganti pemanggilan `Member::whereIn('group_id', $record->getAllDescendantIds())->count()` per baris dengan peta hitungan (*lookup map*) jumlah anggota per `group_id` yang di-cache singkat dengan auto-invalidation pada `Member::saved` / `deleted`, lalu dijumlahkan mengikuti pohon hierarki grup di memori.
  - [x] Optimasi `getAllDescendantIds()` dan `getAllAncestorIds()` pada `Group.php` dengan pemuatan graf hierarki 1-query in-memory (`Group::pluck('parent_id', 'id')`), mengeliminasi query berulang ke tabel database `cache`.

---

### Sprint 3: Standarisasi UI 100% Filament Native & Konsistensi Header Action (Prioritas Sedang)
**Tujuan:** Menghapus penggunaan sintaks Blade manual di dalam schema PHP dan menyeragamkan seluruh tombol aksi header utilitas menjadi `->hiddenLabel()->outlined()->tooltip(...)`.

- [x] **3.1 Pembersihan Blade Manual & Penyeragaman Header di [ImportMembers.php](file:///d:/SandBox/Antigravity/inTime/app/Filament/Resources/Members/Pages/ImportMembers.php)**
  - [x] Ubah `Action::make('reset_file')` (`Mulai Ulang`) dan `Action::make('back')` (`Kembali`) pada `getHeaderActions()` menjadi `->hiddenLabel()->outlined()` dengan `->tooltip('Mulai Ulang Pemeriksaan File')` dan `->tooltip('Kembali ke Daftar Anggota')`.
  - [x] Ganti `->submitAction(new HtmlString(Blade::render(...)))` pada `Wizard` dengan komponen `Action::make('saveImport')` native Filament (lengkap dengan `wire:confirm` & auto-spinner bawaan).
  - [x] Bersihkan import `Illuminate\Support\Facades\Blade` dari class.

- [x] **3.2 Pembersihan Blade Manual & Penyeragaman Header di [ImportGroups.php](file:///d:/SandBox/Antigravity/inTime/app/Filament/Resources/Groups/Pages/ImportGroups.php)**
  - [x] Ubah `Action::make('reset_file')` dan `Action::make('back')` pada `getHeaderActions()` menjadi `->hiddenLabel()->outlined()` + `->tooltip()`.
  - [x] Ganti `->submitAction(new HtmlString(Blade::render(...)))` pada `Wizard` dengan `Action` native Filament.
  - [x] Bersihkan import `Illuminate\Support\Facades\Blade` dari class.

- [x] **3.3 Penyeragaman Visual Header di [ViewGroup.php](file:///d:/SandBox/Antigravity/inTime/app/Filament/Resources/Groups/Pages/ViewGroup.php)**
  - [x] Ubah tombol utilitas `refreshMemberAge` dari `->iconButton()` (borderless) menjadi `->hiddenLabel()->outlined()->tooltip(...)` agar berbingkai rapi mendampingi `EditAction`.

---

### Sprint 4: Fitur Ekspor Data Raw Grup "Round-Trip Ready" (Prioritas Sedang / Pelengkap)
**Tujuan:** Melengkapi ekosistem manajemen data Grup agar sepadan dengan fitur Ekspor Data Raw Anggota (mendukung siklus *Ekspor -> Edit Massal di Excel -> Impor Ulang*).

- [x] **4.1 Pembuatan Class Export [GroupsRawExport.php](file:///d:/SandBox/Antigravity/inTime/app/Exports/GroupsRawExport.php)**
  - [x] Susun kolom utama yang 100% kompatibel dengan [GroupTemplateExport.php](file:///d:/SandBox/Antigravity/inTime/app/Exports/GroupTemplateExport.php) & [GroupImportParser.php](file:///d:/SandBox/Antigravity/inTime/app/Services/Groups/GroupImportParser.php) (Kolom A–D dengan header Biru Indigo `#4F46E5`: `Tingkat Grup (Wajib)`, `Nama Grup (Wajib)`, `Induk Grup`, `Kode Grup (Opsional)`), diikuti kolom informasi sistem (header Abu-abu `#4B5563`: `Prefix ID Otomatis`, `Template KTA Efektif`, `Jumlah Anggota`, `Status Aktif`).
  - [x] Terapkan format teks `@` pada kode grup agar angka berawalan nol (seperti `01`, `07`) terjaga utuh.
  - [x] Terapkan *Freeze Pane* `A2` dan *Auto-Filter* pada baris pertama.

- [x] **4.2 Integrasi Tombol Ekspor pada [ManageGroups.php](file:///d:/SandBox/Antigravity/inTime/app/Filament/Resources/Groups/Pages/ManageGroups.php) & [GroupsTable.php](file:///d:/SandBox/Antigravity/inTime/app/Filament/Resources/Groups/Tables/GroupsTable.php)**
  - [x] Tambahkan `Action::make('export')` di `ManageGroups::getHeaderActions()` menggunakan `->hiddenLabel()->outlined()`, `->icon('heroicon-o-document-arrow-down')`, `->color('success')`, `->tooltip('Ekspor Data Grup (Excel)')`, dengan modal pilihan cakupan (*Sesuai Filter & Tab Saat Ini* vs *Seluruh Grup di Wilayah Wewenang Saya*).
  - [x] Pastikan urutan tombol header adalah `export | import | CreateAction` (Primary Action di sisi kanan).
  - [x] Tambahkan `BulkAction::make('export_selected')` (`Ekspor Terpilih`) pada `BulkActionGroup` di `GroupsTable.php` lengkap dengan filter wewenang per baris dan notifikasi transparan.

---

## 3. Kriteria Penerimaan (Definition of Done)
1. **Zero N+1 pada Halaman Rekap:** Membuka halaman `Aktivitas Anggota` dan mengunduh Excel dengan >100 anggota berjalan dengan jumlah query konstan (<15 query total).
2. **Keamanan Scope Terjamin:** Pengguna non-Super Admin tidak dapat menghapus/memulihkan grup di luar wilayahnya via aksi massal, namun tetap dapat mengekspor laporan kehadiran anggota kelompoknya sendiri pada pertemuan tingkat induk.
3. **100% Native Filament:** Tidak ada lagi penggunaan `Blade::render()` untuk merender tombol di dalam class Resource/Page Filament.
4. **Lint & Regression Test:** Seluruh file lulus `php -l` dan alur *round-trip* ekspor-impor berjalan tanpa error.

