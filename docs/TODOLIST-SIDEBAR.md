# Dokumen Rencana & Checklist: Restrukturisasi Navigasi Sidebar inTime

Dokumen ini disusun dari perspektif **Product Manager & UX Professional** untuk memandu perubahan struktur navigasi sidebar panel admin inTime — dari pola *database-centric* menjadi *workflow-centric* — tanpa memutus fungsionalitas yang sudah berjalan.

> **Tanggal Dibuat:** 2026-09-10
> **Referensi Utama:** `NOTES-SIDEBAR.md`, `TODOLIST-POLICY.md`, `MENU-SIDEBAR.md`

---

## Prinsip Panduan

1. **Mental model pengguna, bukan schema database** — Sidebar merepresentasikan pekerjaan yang dilakukan, bukan tabel yang ada.
2. **Performa tidak boleh turun** — Tidak ada kueri tambahan akibat perubahan navigasi; manfaatkan memoization yang sudah ada.
3. **Policy tetap terjaga** — Tidak ada logika otorisasi yang ditulis *inline* di file Resource Navigation. Gunakan `Policy`, `canAccess()`, atau `shield` sesuai pola yang sudah ditetapkan di `TODOLIST-POLICY.md`.
4. **Zero inline style baru** — CSS navigasi dikelola melalui file terpisah, bukan via `renderHook` inline di `AdminPanelProvider.php`.
5. **Tidak destruktif** — Semua route, nama Resource, dan relasi Policy/Shield tidak berubah; hanya `navigationGroup`, `navigationSort`, dan `navigationLabel` yang dimodifikasi.

---

## Kondisi Sidebar Saat Ini

| # | Resource / Page | `navigationGroup` Saat Ini | `navigationSort` |
|---|---|---|---|
| 1 | `Dashboard.php` | *(ungrouped)* | — |
| 2 | `MeetingResource` | `Presensi & Laporan` | 1 |
| 3 | `MemberResource` | `Data Master` | 1 |
| 4 | `GroupResource` | `Data Master` | 2 |
| 5 | `AgeGroupResource` | `Konfigurasi Sistem` | 3 |
| 6 | `LevelResource` | `Konfigurasi Sistem` | 4 |
| 7 | `PositionCategoryResource` | `Konfigurasi Sistem` | 5 |
| 8 | `CardTemplateResource` | `Konfigurasi Sistem` | 6 |
| 9 | `UserResource` | `Akses Pengguna` | 1 |
| 10 | `RoleResource` *(Shield)* | `Akses Pengguna` | 2 |

**Masalah Utama:**
- Grup `Konfigurasi Sistem` tidak terdaftar di `navigationGroups()` pada `AdminPanelProvider.php` → urutan tidak terjamin.
- `AgeGroup`, `Level`, `PositionCategory` muncul sebagai item sidebar mandiri → membebani mental model operator.
- `ScanAttendance` sudah `$shouldRegisterNavigation = false` (benar), tetapi tidak ada dokumentasinya.
- Inline style CSS tebal (~80 baris) berada dalam `renderHook` di `AdminPanelProvider.php` → melanggar prinsip separasi CSS.

---

## Target Sidebar Final

```
┌──────────────────────────────────────────────┐
│  🏠  Dashboard                               │  (ungrouped)
├──────────────────────────────────────────────┤
│  OPERASIONAL                                 │
│    📅  Pertemuan                             │  MeetingResource
├──────────────────────────────────────────────┤
│  DATA ANGGOTA                                │
│    👥  Anggota                               │  MemberResource
├──────────────────────────────────────────────┤
│  ORGANISASI                                  │
│    🏢  Grup & Struktur                       │  GroupResource (label diganti)
├──────────────────────────────────────────────┤
│  ADMINISTRASI                                │
│    🪪  Template Kartu                        │  CardTemplateResource
│    👤  Pengguna                              │  UserResource
│    🛡️  Role & Izin                           │  RoleResource (Shield)
└──────────────────────────────────────────────┘
```

**Disembunyikan dari sidebar (tetap berfungsi sebagai data referensi):**

| Resource | Alasan Disembunyikan | Cara Akses |
|---|---|---|
| `AgeGroupResource` | Jarang diubah; konfigurasi teknis | Tetap bisa diakses via URL langsung `/admin/age-groups` |
| `LevelResource` | Jarang diubah; konfigurasi teknis | Tetap bisa diakses via URL langsung `/admin/levels` |
| `PositionCategoryResource` | Jarang diubah; konfigurasi teknis | Tetap bisa diakses via URL langsung `/admin/position-categories` |

> **Catatan UX:** Resource di atas tidak dihapus — hanya `$shouldRegisterNavigation = false`. Superadmin masih bisa mengaksesnya langsung via URL. Policy & Shield tetap aktif melindungi endpoint tersebut.

---

## Checklist Pengerjaan

### Fase 1: Ekstraksi Inline CSS ke File Terpisah

> Menyelesaikan pelanggaran prinsip *"zero inline code"* sebelum melakukan perubahan navigasi, agar `AdminPanelProvider.php` tetap bersih setelah modifikasi.

- [ ] **1.1 Buat file CSS terpisah untuk tema sidebar inTime**
  - [ ] Buat file `resources/css/filament/admin/theme.css` sesuai konvensi Filament v5.
  - [ ] Pindahkan seluruh isi blok `<style>` dari `renderHook('panels::styles.after', ...)` di [`AdminPanelProvider.php`](file:///d:/SandBox/Antigravity/inTime/app/Providers/Filament/AdminPanelProvider.php) ke file CSS tersebut — termasuk light mode, dark mode, sidebar, topbar, dan table styles.

- [ ] **1.2 Ganti `renderHook` CSS dengan referensi stylesheet**
  - [ ] Di `AdminPanelProvider.php`, hapus blok `->renderHook('panels::styles.after', fn() => Blade::render('<style>...</style>'))`.
  - [ ] Ganti dengan `->viteTheme('resources/css/filament/admin/theme.css')` atau mekanisme stylesheet yang sesuai Filament v5.
  - [ ] Verifikasi tampilan sidebar, topbar, dan tabel tetap identik di browser (light & dark mode) setelah ekstraksi.

---

### Fase 2: Restrukturisasi `navigationGroups()` di AdminPanelProvider

> Mendaftarkan grup-grup baru secara eksplisit agar urutan sidebar terjamin dan konsisten di semua role.

- [ ] **2.1 Perbarui array `navigationGroups()` di [`AdminPanelProvider.php`](file:///d:/SandBox/Antigravity/inTime/app/Providers/Filament/AdminPanelProvider.php)**
  - [ ] Hapus grup lama: `'Presensi & Laporan'`, `'Data Master'`, `'Akses Pengguna'`.
  - [ ] Tambahkan grup baru dengan urutan yang benar:
    - `'Operasional'`
    - `'Data Anggota'`
    - `'Organisasi'`
    - `'Administrasi'`
  - [ ] Pastikan nama grup **persis sama** (case-sensitive) dengan nilai `$navigationGroup` yang akan diset di masing-masing Resource pada Fase 3.

- [ ] **2.2 Perbarui konfigurasi plugin Shield**
  - [ ] Di `AdminPanelProvider.php`, ubah `->navigationGroup('Akses Pengguna')` pada `FilamentShieldPlugin` menjadi `->navigationGroup('Administrasi')`.
  - [ ] Pertahankan `->navigationSort(2)` agar `Role & Izin` tetap muncul di bawah `Pengguna`.

---

### Fase 3: Perbarui Nilai `$navigationGroup` & `$navigationSort` di Setiap Resource

> Perubahan dilakukan **hanya** pada properti navigasi. Tidak ada perubahan logika, Policy, atau query.

- [ ] **3.1 [`MeetingResource.php`](file:///d:/SandBox/Antigravity/inTime/app/Filament/Resources/Meetings/MeetingResource.php)**
  - [ ] Ubah `$navigationGroup` dari `'Presensi & Laporan'` → `'Operasional'`.
  - [ ] Pertahankan `$navigationSort = 1` dan `$navigationIcon`.
  - [ ] Pertahankan `getNavigationBadge()` — tidak ada perubahan kueri.

- [ ] **3.2 [`MemberResource.php`](file:///d:/SandBox/Antigravity/inTime/app/Filament/Resources/Members/MemberResource.php)**
  - [ ] Ubah `$navigationGroup` dari `'Data Master'` → `'Data Anggota'`.
  - [ ] Pertahankan `$navigationSort = 1` dan badge anggota belum permanen.

- [ ] **3.3 [`GroupResource.php`](file:///d:/SandBox/Antigravity/inTime/app/Filament/Resources/Groups/GroupResource.php)**
  - [ ] Ubah `$navigationGroup` dari `'Data Master'` → `'Organisasi'`.
  - [ ] Ubah `$navigationLabel` dari `'Grup'` → `'Grup & Struktur'` agar lebih deskriptif.
  - [ ] Ubah `$navigationSort = 1`.

- [ ] **3.4 [`CardTemplateResource.php`](file:///d:/SandBox/Antigravity/inTime/app/Filament/Resources/CardTemplates/CardTemplateResource.php)**
  - [ ] Ubah `$navigationGroup` dari `'Konfigurasi Sistem'` → `'Administrasi'`.
  - [ ] Ubah `$navigationSort = 1`.

- [ ] **3.5 [`UserResource.php`](file:///d:/SandBox/Antigravity/inTime/app/Filament/Resources/Users/UserResource.php)**
  - [ ] Ubah `$navigationGroup` dari `'Akses Pengguna'` → `'Administrasi'`.
  - [ ] Ubah `$navigationSort = 2`.

---

### Fase 4: Sembunyikan Resource Konfigurasi Teknis dari Sidebar

> Resource ini **tidak dihapus** — hanya disembunyikan dari navigasi. Semua fungsionalitas (CRUD, Policy, Shield permission) tetap berjalan normal.

- [ ] **4.1 [`AgeGroupResource.php`](file:///d:/SandBox/Antigravity/inTime/app/Filament/Resources/AgeGroups/AgeGroupResource.php)**
  - [ ] Tambahkan properti: `protected static bool $shouldRegisterNavigation = false;`

- [ ] **4.2 [`LevelResource.php`](file:///d:/SandBox/Antigravity/inTime/app/Filament/Resources/Levels/LevelResource.php)**
  - [ ] Tambahkan properti: `protected static bool $shouldRegisterNavigation = false;`

- [ ] **4.3 [`PositionCategoryResource.php`](file:///d:/SandBox/Antigravity/inTime/app/Filament/Resources/PositionCategories/PositionCategoryResource.php)**
  - [ ] Tambahkan properti: `protected static bool $shouldRegisterNavigation = false;`

> **Penting:** `$shouldRegisterNavigation = false` hanya melepas item dari navigasi sidebar. Policy & Shield tetap aktif memproteksi URL endpoint tersebut secara penuh.

---

### Fase 5: Verifikasi Performa — Tidak Ada Regresi N+1

> Fase ini bersifat *review*, bukan perubahan kode. Pastikan perubahan navigasi tidak memperkenalkan kueri baru.

- [ ] **5.1 Verifikasi `MeetingResource::getNavigationBadge()`**
  - [ ] Pastikan kueri `whereDate('meeting_date', '>=', now())` tetap menggunakan scoping hierarki dari `getEloquentQuery()` yang sudah ada.
  - [ ] Konfirmasi tidak ada kueri tambahan yang dipanggil hanya karena badge ditampilkan.

- [ ] **5.2 Verifikasi `MemberResource::getNavigationBadge()`**
  - [ ] Pastikan kueri `where('is_permanent', false)` tetap di-scope ke hierarki grup pengguna aktif melalui `getEloquentQuery()`.
  - [ ] Konfirmasi badge tidak memicu `getAllDescendantIds()` lebih dari satu kali per request — sudah di-memoize sesuai `TODOLIST-POLICY.md` Fase 6.

- [ ] **5.3 Konfirmasi tidak ada perubahan pada `getEloquentQuery()` di semua Resource**
  - [ ] `MeetingResource` → eager load `group.level` tetap ada.
  - [ ] `MemberResource` → eager load `group.level` dan `ageGroup` tetap ada.
  - [ ] `GroupResource` → eager load `level` dan `parent.level` tetap ada.

---

### Fase 6: Verifikasi Visual & Fungsional (QA)

- [ ] **6.1 Cek tampilan sidebar sebagai Superadmin**
  - [ ] Konfirmasi hanya **4 grup** yang muncul: `Operasional`, `Data Anggota`, `Organisasi`, `Administrasi`.
  - [ ] Konfirmasi `AgeGroup`, `Level`, `PositionCategory` **tidak muncul** di sidebar.
  - [ ] Konfirmasi akses langsung via URL `/admin/age-groups`, `/admin/levels`, `/admin/position-categories` tetap berfungsi tanpa error 403.

- [ ] **6.2 Cek tampilan sidebar sebagai Operator**
  - [ ] Konfirmasi hanya menu yang diizinkan Shield yang ditampilkan.
  - [ ] Konfirmasi grup `Administrasi` tersembunyi jika Operator tidak memiliki permission `UserResource` dan `CardTemplateResource`.

- [ ] **6.3 Verifikasi tidak ada regresi CSS**
  - [ ] Sidebar light mode: warna, hover, active state tampil normal.
  - [ ] Sidebar dark mode: warna `#0d1511`, border `#1e293b`, active emerald tampil normal.
  - [ ] Topbar blur/glassmorphism tetap berfungsi.

- [ ] **6.4 Verifikasi Shield permission tidak terputus**
  - [ ] Buka `/admin/roles` dan pastikan matrix permission masih menampilkan seluruh resource.
  - [ ] Jalankan `php artisan permission:cache-reset` jika ada anomali cache permission.

---

## Ringkasan File yang Dimodifikasi

| File | Perubahan |
|---|---|
| [`AdminPanelProvider.php`](file:///d:/SandBox/Antigravity/inTime/app/Providers/Filament/AdminPanelProvider.php) | Ganti `renderHook` CSS → referensi stylesheet; perbarui `navigationGroups()`; ubah grup Shield |
| `resources/css/filament/admin/theme.css` *(file baru)* | Tampung seluruh CSS inTime theme yang dipindah dari inline |
| [`MeetingResource.php`](file:///d:/SandBox/Antigravity/inTime/app/Filament/Resources/Meetings/MeetingResource.php) | `$navigationGroup` → `'Operasional'` |
| [`MemberResource.php`](file:///d:/SandBox/Antigravity/inTime/app/Filament/Resources/Members/MemberResource.php) | `$navigationGroup` → `'Data Anggota'` |
| [`GroupResource.php`](file:///d:/SandBox/Antigravity/inTime/app/Filament/Resources/Groups/GroupResource.php) | `$navigationGroup` → `'Organisasi'`; `$navigationLabel` → `'Grup & Struktur'` |
| [`CardTemplateResource.php`](file:///d:/SandBox/Antigravity/inTime/app/Filament/Resources/CardTemplates/CardTemplateResource.php) | `$navigationGroup` → `'Administrasi'`; `$navigationSort` → `1` |
| [`UserResource.php`](file:///d:/SandBox/Antigravity/inTime/app/Filament/Resources/Users/UserResource.php) | `$navigationGroup` → `'Administrasi'`; `$navigationSort` → `2` |
| [`AgeGroupResource.php`](file:///d:/SandBox/Antigravity/inTime/app/Filament/Resources/AgeGroups/AgeGroupResource.php) | `$shouldRegisterNavigation = false` |
| [`LevelResource.php`](file:///d:/SandBox/Antigravity/inTime/app/Filament/Resources/Levels/LevelResource.php) | `$shouldRegisterNavigation = false` |
| [`PositionCategoryResource.php`](file:///d:/SandBox/Antigravity/inTime/app/Filament/Resources/PositionCategories/PositionCategoryResource.php) | `$shouldRegisterNavigation = false` |

---

## Catatan Risiko & Mitigasi

| Risiko | Mitigasi |
|---|---|
| CSS sidebar rusak setelah ekstraksi ke file | Verifikasi visual di QA Fase 6 sebelum merge |
| Shield permission cache tidak terbarui | Jalankan `php artisan permission:cache-reset` jika ada anomali |
| Operator kehilangan akses ke Resource yang disembunyikan | `$shouldRegisterNavigation = false` tidak menonaktifkan Policy; akses via URL langsung tetap diproteksi Shield |
| String `navigationGroups()` tidak cocok dengan `$navigationGroup` Resource | Gunakan string literal yang identik (case-sensitive), konsisten dengan pola yang sudah ada |
