# Sidebar Menu — inTime Admin Panel

> Dokumen ini mendefinisikan susunan navigasi sidebar pada panel admin Filament.
> Terakhir diperbarui: 2026-09-08

---

## Kondisi Saat Ini

| # | Grup | Menu | Icon | Sort | Source |
|---|------|------|------|------|--------|
| 1 | *(ungrouped)* | Beranda | `heroicon-o-home` (default) | — | `Dashboard.php` (Page) |
| 2 | Presensi & Laporan | Pertemuan | `heroicon-o-calendar-days` | 1 | `MeetingResource` |
| 3 | Data Master | Anggota | `heroicon-o-user-group` | 1 | `MemberResource` |
| 4 | Data Master | Grup | `heroicon-o-queue-list` | 2 | `GroupResource` |
| 5 | Konfigurasi Sistem | Kelompok Usia | `heroicon-o-tag` | 3 | `AgeGroupResource` |
| 6 | Konfigurasi Sistem | Tingkatan Grup | `heroicon-o-adjustments-vertical` | 4 | `LevelResource` |
| 7 | Konfigurasi Sistem | Kategori Jabatan | `heroicon-o-rectangle-stack` | 5 | `PositionCategoryResource` |
| 8 | Konfigurasi Sistem | Template Kartu | `heroicon-o-identification` | 6 | `CardTemplateResource` |
| 9 | Akses Pengguna | Pengguna | `heroicon-o-users` | 1 | `UserResource` |
| 10 | Akses Pengguna | Role & Izin | *(dari plugin Shield)* | 2 | `RoleResource` (Shield) |

### Masalah yang Ditemukan

1. **Grup "Konfigurasi Sistem" tidak terdaftar** di `navigationGroups()` pada `AdminPanelProvider.php` — hanya 3 grup yang didaftarkan (*Presensi & Laporan, Data Master, Akses Pengguna*), sehingga posisi grup ini tidak terjamin urutannya.
2. **Tidak ada area personal** — User yang login tidak punya akses cepat ke profil atau grup miliknya sendiri.
3. **Semua menu terasa "admin-oriented"** — Tidak ada pembedaan antara fitur yang dipakai sehari-hari vs konfigurasi.

---

## Rekomendasi Susunan Baru

### Prinsip UX

| Prinsip | Penjelasan |
|---------|------------|
| **Progresif** | Yang paling sering diakses di atas, konfigurasi di bawah |
| **Personal → Operasional → Konfigurasi → Akses** | Urutan mental model pengguna |
| **Icon di menu, bukan di grup** | Grup cukup sebagai label text uppercase (separator visual) |
| **Scannable** | Setiap menu punya icon unik agar mudah dikenali saat sidebar collapsed |

### Struktur Final

```
┌─────────────────────────────────────────┐
│  🏠  Beranda                            │  Ungrouped — selalu paling atas
├─────────────────────────────────────────┤
│  AREA SAYA                              │  Grup — konteks personal user login
│    👤  Profil Saya                      │
│    📂  Grup Saya                        │
├─────────────────────────────────────────┤
│  PRESENSI & LAPORAN                     │  Grup — fitur operasional utama
│    📅  Pertemuan                        │
├─────────────────────────────────────────┤
│  DATA MASTER                            │  Grup — kelola data organisasi
│    👥  Anggota                          │
│    📋  Grup                             │
├─────────────────────────────────────────┤
│  KONFIGURASI SISTEM                     │  Grup — pengaturan & template
│    🏷️  Kelompok Usia                   │
│    📊  Tingkatan Grup                   │
│    📚  Kategori Jabatan                 │
│    🪪  Template Kartu                   │
├─────────────────────────────────────────┤
│  AKSES PENGGUNA                         │  Grup — manajemen user & role
│    👤  Pengguna                         │
│    🛡️  Role & Izin                     │
└─────────────────────────────────────────┘
```

### Spesifikasi Detail

#### Ungrouped

| Menu | Label | Icon | Sort | Tipe | Keterangan |
|------|-------|------|------|------|------------|
| Beranda | `Beranda` | `heroicon-o-home` | — | Page | Dashboard utama, widget ringkasan |

#### Grup: Area Saya

| Menu | Label | Icon | Sort | Tipe | Keterangan |
|------|-------|------|------|------|------------|
| Profil Saya | `Profil Saya` | `heroicon-o-user-circle` | 1 | Page | Lihat & edit profil user yang sedang login |
| Grup Saya | `Grup Saya` | `heroicon-o-folder` | 2 | Page | Daftar grup di mana user login menjadi anggota |

#### Grup: Presensi & Laporan

| Menu | Label | Icon | Sort | Tipe | Keterangan |
|------|-------|------|------|------|------------|
| Pertemuan | `Pertemuan` | `heroicon-o-calendar-days` | 1 | Resource | CRUD data pertemuan & presensi |

#### Grup: Data Master

| Menu | Label | Icon | Sort | Tipe | Keterangan |
|------|-------|------|------|------|------------|
| Anggota | `Anggota` | `heroicon-o-user-group` | 1 | Resource | CRUD data anggota organisasi |
| Grup | `Grup` | `heroicon-o-queue-list` | 2 | Resource | CRUD data grup/kelompok |

#### Grup: Konfigurasi Sistem

| Menu | Label | Icon | Sort | Tipe | Keterangan |
|------|-------|------|------|------|------------|
| Kelompok Usia | `Kelompok Usia` | `heroicon-o-tag` | 1 | Resource | Definisi rentang usia |
| Tingkatan Grup | `Tingkatan Grup` | `heroicon-o-adjustments-vertical` | 2 | Resource | Level hierarki grup |
| Kategori Jabatan | `Kategori Jabatan` | `heroicon-o-rectangle-stack` | 3 | Resource | Kategori posisi/jabatan |
| Template Kartu | `Template Kartu` | `heroicon-o-identification` | 4 | Resource | Desain kartu anggota |

#### Grup: Akses Pengguna

| Menu | Label | Icon | Sort | Tipe | Keterangan |
|------|-------|------|------|------|------------|
| Pengguna | `Pengguna` | `heroicon-o-users` | 1 | Resource | Kelola akun pengguna sistem |
| Role & Izin | `Role & Izin` | *(Shield default)* | 2 | Resource | Kelola role & permission (Filament Shield) |

---

## Implementasi Teknis

### 1. Daftarkan semua grup di `AdminPanelProvider.php`

```php
->navigationGroups([
    'Area Saya',
    'Presensi & Laporan',
    'Data Master',
    'Konfigurasi Sistem',
    'Akses Pengguna',
])
```

### 2. Buat halaman baru

| File | Tipe | Keterangan |
|------|------|------------|
| `app/Filament/Pages/MyProfile.php` | Filament Page | Halaman profil user login |
| `app/Filament/Pages/MyGroups.php` | Filament Page | Halaman daftar grup user login |

### 3. Update `$navigationSort` pada Konfigurasi Sistem

Resource di grup *Konfigurasi Sistem* perlu di-renumber agar konsisten (dimulai dari 1, bukan 3):

| Resource | Sort Lama | Sort Baru |
|----------|-----------|-----------|
| `AgeGroupResource` | 3 | 1 |
| `LevelResource` | 4 | 2 |
| `PositionCategoryResource` | 5 | 3 |
| `CardTemplateResource` | 6 | 4 |

### 4. File yang perlu dimodifikasi

| File | Perubahan |
|------|-----------|
| `AdminPanelProvider.php` | Tambah grup "Area Saya" & "Konfigurasi Sistem" ke `navigationGroups()` |
| `AgeGroupResource.php` | Update `$navigationSort` → 1 |
| `LevelResource.php` | Update `$navigationSort` → 2 |
| `PositionCategoryResource.php` | Update `$navigationSort` → 3 |
| `CardTemplateResource.php` | Update `$navigationSort` → 4 |

### 5. File yang perlu dibuat

| File | Keterangan |
|------|------------|
| `app/Filament/Pages/MyProfile.php` | Page: Profil Saya |
| `app/Filament/Pages/MyGroups.php` | Page: Grup Saya |

---

## Catatan

- **ScanAttendance** (`ScanAttendance.php`) memiliki `$shouldRegisterNavigation = false` — tidak muncul di sidebar, diakses via tombol aksi di halaman lain.
- **Visibilitas menu** tetap dikontrol oleh **Filament Shield** — menu hanya muncul jika user punya permission yang sesuai.
- Grup "Area Saya" idealnya **tidak perlu permission khusus** karena bersifat personal (semua user yang login bisa akses).
