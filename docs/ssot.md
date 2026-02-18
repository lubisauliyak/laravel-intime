# 📘 SSOT — Single Source of Truth: inTime

> **Terakhir diperbarui:** 18 Februari 2026 (Revisi 3 — Dashboard Optimization & Role Migration)  
> **Prinsip:** Dokumen ini adalah **satu-satunya sumber kebenaran** proyek inTime. Semua dokumen lain tunduk pada informasi di sini. Jika ada konflik, **dokumen ini yang benar**.

---

## Daftar Isi

1. [Identitas Proyek](#1-identitas-proyek)
2. [Peta Dokumen & Otoritas](#2-peta-dokumen--otoritas)
3. [Skema Database (Aktual)](#3-skema-database-aktual)
4. [Aturan Role & Permission](#4-aturan-role--permission)
5. [Status Pengembangan](#5-status-pengembangan)
6. [Arsitektur Kode](#6-arsitektur-kode)
7. [Kebijakan & Konvensi](#7-kebijakan--konvensi)
8. [Keputusan Arsitektur](#8-keputusan-arsitektur)
9. [Changelog SSOT](#9-changelog-ssot)

---

## 1. Identitas Proyek

| Atribut | Nilai |
|:--------|:------|
| **Nama** | inTime — Smart Membership & Attendance System |
| **Deskripsi** | Aplikasi web untuk manajemen pendataan anggota dan sistem absensi pertemuan dengan struktur organisasi bertingkat |
| **Framework** | Laravel 12 |
| **Admin Panel** | Filament PHP v5 |
| **Database** | MySQL |
| **PHP** | ^8.2 (Development: 8.4, Hosting: 8.2.29) |
| **Bahasa Antarmuka** | Indonesia (Bahasa Indonesia) |

### Dependencies Utama (dari `composer.json`)

| Package | Fungsi |
|:--------|:-------|
| `filament/filament` | Admin panel (v5) |
| `bezhansalleh/filament-shield` | Role & Permission management |
| `spatie/laravel-permission` | RBAC backend |
| `simplesoftwareio/simple-qrcode` | QR Code generation (SVG) |
| `maatwebsite/excel` | Excel export |
| `barryvdh/laravel-dompdf` | PDF generation |

> 💡 **SSOT untuk dependencies adalah `composer.json`**, bukan dokumen ini.

---

## 2. Peta Dokumen & Otoritas

### 2.1 Matriks Otoritas

Setiap topik hanya boleh memiliki **SATU dokumen otoritatif (SSOT)**. Dokumen lain boleh mereferensikan tapi **tidak boleh menduplikasi konten**.

| Topik | SSOT (Sumber Kebenaran) | Referensi Pendukung |
|:------|:------------------------|:-------------------|
| **Kebenaran proyek secara umum** | `docs/ssot.md` (dokumen ini) | — |
| **Identitas & overview publik** | `README.md` | — |
| **Grand design & arsitektur** | `docs/implementation-plan.md` | `docs/ssot.md` §3, §4 |
| **Skema database** | `docs/ssot.md` §3 + migration files | `docs/implementation-plan.md` §3 (harus sinkron) |
| **Role & permission rules** | `docs/ssot.md` §4 | `docs/implementation-plan.md` §4 |
| **Status progress keseluruhan** | `docs/ssot.md` §5 | `README.md`, `docs/timeline.md` |
| **Detail tugas per phase** | `docs/todolist/phase-X-todolist.md` | — |
| **Kronologi pengembangan** | `docs/timeline.md` | — |
| **Test plan per phase** | `docs/test/test-phase-X.md` | — |
| **Audit backend (historis)** | `docs/backend-brain.md` | — |
| **Audit QA (aktif)** | `docs/qa-brain.md` | — |
| **Tech stack & versions** | `composer.json` + `.env` | `README.md` |
| **UI sidebar structure** | Kode aktual (Resources + Policies) | `docs/sidebar-panel.md` (referensi saja) |

### 2.2 Inventaris Dokumen

```
inTime/
├── README.md                     📄 Public-facing overview
├── composer.json                 📄 SSOT: Dependencies & versions
├── .env / .env.example           📄 SSOT: Environment config
│
├── docs/
│   ├── ssot.md                   🏛️ DOKUMEN INI — Master reference
│   ├── implementation-plan.md    📐 Grand design & arsitektur
│   ├── timeline.md               ⏳ Kronologi (harus sinkron dgn §5)
│   ├── sidebar-panel.md          ❌ DIHAPUS (keputusan #4 — konten sudah di SSOT §4)
│   ├── backend-brain.md          📦 Arsip audit backend
│   ├── qa-brain.md               🔍 Audit QA aktif
│   │
│   ├── todolist/
│   │   ├── phase-1-todolist.md   📦 Arsip (selesai 100%)
│   │   ├── phase-2-todolist.md   📦 Arsip (selesai 100%)
│   │   ├── phase-3-todolist.md   📦 Arsip (selesai 100%)
│   │   ├── phase-4-todolist.md   📦 Arsip (selesai 100%)
│   │   ├── phase-5-todolist.md   📦 Arsip (selesai 100%)
│   │   └── phase-6-todolist.md   🎯 SSOT: Tugas aktif saat ini
│   │
│   └── test/
│       ├── test-phase-1.md       📦 Arsip (passed 100%)
│       ├── test-phase-2.md       📦 Arsip (passed 100%)
│       ├── test-phase-3.md       📦 Arsip (passed 100%)
│       ├── test-phase-4.md       📦 Arsip (passed 100%)
│       └── test-phase-5.md       📦 Arsip (passed 100%)
```

### 2.3 Aturan Pembaruan

1. **Jika mengubah skema database** → Update §3 dokumen ini **DAN** `implementation-plan.md` §3.
2. **Jika menyelesaikan sebuah phase** → Update §5 dokumen ini, `README.md`, dan `timeline.md`.
3. **Jika berubahnya aturan role/permission** → Update §4 dokumen ini **DAN** `implementation-plan.md` §4.
4. **Jangan pernah menduplikasi** daftar detail tugas — cukup link ke `phase-X-todolist.md`.

---

## 3. Skema Database (Aktual)

> ⚠️ **Ini adalah skema database AKTUAL** yang direkonstruksi dari seluruh 22 file migration. Jika `implementation-plan.md` §3 berbeda, **skema di bawah ini yang benar**.

### 3.1 Tabel `levels`

*Master hierarki organisasi.*

| Field | Type | Constraint | Description |
|:------|:-----|:-----------|:------------|
| `id` | bigint | PK, auto-increment | — |
| `name` | string | — | Nama level (contoh: "PUSAT", "WILAYAH") |
| `code` | string | unique | Kode level (contoh: "T3", "T2") |
| `level_number` | integer | unique | Angka hierarki (3 = tertinggi) |
| `created_at` | timestamp | — | — |
| `updated_at` | timestamp | — | — |

### 3.2 Tabel `groups`

*Struktur organisasi bertingkat (adjacency list pattern).*

| Field | Type | Constraint | Description |
|:------|:-----|:-----------|:------------|
| `id` | bigint | PK, auto-increment | — |
| `parent_id` | bigint | FK → `groups.id`, nullable, cascade | Grup induk |
| `level_id` | bigint | FK → `levels.id`, nullable, set null | Tingkat hierarki |
| `name` | string | — | Nama grup |
| `status` | boolean | default: true | Aktif / Non-aktif |
| `deleted_at` | timestamp | nullable | Soft delete |
| `created_at` | timestamp | — | — |
| `updated_at` | timestamp | — | — |

> **Catatan:** Kolom `level` (integer) sudah di-drop dan digantikan `level_id` (FK) via migration `change_level_to_level_id`.

### 3.3 Tabel `users`

*Akun sistem (Admin/Operator). Terpisah dari data anggota.*

| Field | Type | Constraint | Description |
|:------|:-----|:-----------|:------------|
| `id` | bigint | PK, auto-increment | — |
| `name` | string | — | Nama lengkap |
| `email` | string | unique | Untuk login |
| `email_verified_at` | timestamp | nullable | — |
| `password` | string | — | Bcrypt hash |
| `group_id` | bigint | FK → `groups.id`, nullable, set null | Penempatan grup |
| `role` | string | nullable | Role utama (Sync dengan Spatie Roles) |
| `status` | boolean | default: true | Aktif / Suspend |
| `deleted_at` | timestamp | nullable | Soft delete |
| `remember_token` | string | nullable | — |
| `created_at` | timestamp | — | — |
| `updated_at` | timestamp | — | — |

### 3.4 Tabel `age_groups`

*Master data kategori usia.*

| Field | Type | Constraint | Description |
|:------|:-----|:-----------|:------------|
| `id` | bigint | PK, auto-increment | — |
| `name` | string | — | Nama kategori (contoh: "ANAK") |
| `code` | string | unique | Kode kategori |
| `min_age` | integer | — | Usia minimum |
| `max_age` | integer | nullable | Usia maksimum (null = ∞) |
| `deleted_at` | timestamp | nullable | Soft delete |
| `created_at` | timestamp | — | — |
| `updated_at` | timestamp | — | — |

### 3.5 Tabel `members`

*Data anggota (subjek absensi). Tidak memiliki akses login.*

| Field | Type | Constraint | Description |
|:------|:-----|:-----------|:------------|
| `id` | bigint | PK, auto-increment | — |
| `member_code` | string | unique, nullable | Kode identitas (contoh: "IT-2024-001") |
| `full_name` | string | — | Nama lengkap (UPPERCASE) |
| `nick_name` | string | nullable | Nama panggilan |
| `group_id` | bigint | FK → `groups.id`, cascade | Penempatan grup |
| `birth_date` | date | nullable | Tanggal lahir |
| `age` | integer | nullable | Usia (auto-calculated by Observer) |
| `age_group_id` | bigint | FK → `age_groups.id`, nullable, set null | Kategori usia (auto-matched) |
| `gender` | enum | `male`, `female` | Jenis kelamin |
| `status` | boolean | default: true | Aktif / Non-aktif |
| `membership_type` | enum | `anggota`, `pengurus` — default: `anggota` | Tipe keanggotaan |
| `qr_code_path` | string | nullable | Path file QR Code (SVG) |
| `deleted_at` | timestamp | nullable | Soft delete |
| `created_at` | timestamp | — | — |
| `updated_at` | timestamp | — | — |

> **Catatan evolusi:**
> - `member_code`: Awalnya `NOT NULL`, diubah ke `nullable` (migration `make_member_code_nullable`).
> - `age_group` (string): Sudah di-drop, digantikan `age` (int) + `age_group_id` (FK) via migration `update_member_age_fields`.
> - `status`: Awalnya `enum('active','inactive','moved')`, diubah ke `boolean` via migration `change_member_status_to_boolean`.
> - `birth_date`: Awalnya `NOT NULL`, diubah ke `nullable` (migration `make_birth_date_nullable`).

**Indexes** (via migration `add_indexes_to_tables`):
- `members_gender_index`
- `members_status_index`

### 3.6 Tabel `meetings`

*Data pertemuan/jadwal.*

| Field | Type | Constraint | Description |
|:------|:-----|:-----------|:------------|
| `id` | bigint | PK, auto-increment | — |
| `name` | string | — | Judul pertemuan |
| `description` | text | nullable | Deskripsi/keterangan |
| `meeting_date` | date | — | Tanggal pelaksanaan |
| `start_time` | time | nullable | Jam mulai |
| `end_time` | time | nullable | Jam selesai |
| `group_id` | bigint | FK → `groups.id`, cascade | Grup penyelenggara |
| `target_gender` | enum | `all`, `male`, `female` — default: `all` | Target gender |
| `target_age_groups` | json | nullable | Array ID kategori usia target |
| `created_by` | bigint | FK → `users.id`, cascade | Pembuat pertemuan |
| `deleted_at` | timestamp | nullable | Soft delete |
| `created_at` | timestamp | — | — |
| `updated_at` | timestamp | — | — |

**Indexes:**
- `meetings_meeting_date_index`

### 3.7 Tabel `attendances`

*Data kehadiran per pertemuan per anggota.*

| Field | Type | Constraint | Description |
|:------|:-----|:-----------|:------------|
| `id` | bigint | PK, auto-increment | — |
| `meeting_id` | bigint | FK → `meetings.id`, cascade | Pertemuan |
| `member_id` | bigint | FK → `members.id`, cascade | Anggota |
| `checkin_time` | datetime | — | Waktu scan/input |
| `method` | enum | `manual`, `qr_code` — default: `qr_code` | Metode input |
| ~~`attendance_type`~~ | ~~enum~~ | ~~`wajib`, `opsional`, `istimewa`~~ | ❌ **DIHAPUS** (keputusan #6) |
| `status` | string | default: `hadir` | Status: `hadir`, `izin`, `sakit` |
| `notes` | text | nullable | Catatan/keterangan |
| `evidence_path` | string | nullable | Path bukti foto |
| `deleted_at` | timestamp | nullable | Soft delete |
| `created_at` | timestamp | — | — |
| `updated_at` | timestamp | — | — |

**Constraints:**
- `UNIQUE(meeting_id, member_id)` — Satu anggota hanya bisa punya satu record per pertemuan.

**Indexes:**
- `attendances_status_index`

### 3.8 Tabel Pendukung (Framework & Packages)

| Tabel | Asal | Fungsi |
|:------|:-----|:-------|
| `sessions` | Laravel | Session management |
| `cache`, `cache_locks` | Laravel | Database cache driver |
| `jobs`, `job_batches`, `failed_jobs` | Laravel | Queue system |
| `permissions`, `roles`, `model_has_permissions`, `model_has_roles`, `role_has_permissions` | Spatie Permission | RBAC tables |

---

## 4. Aturan Role & Permission

### 4.1 Definisi Role

| Role | `group_id` | Scope Akses |
|:-----|:-----------|:------------|
| **super_admin** | `NULL` | Seluruh sistem tanpa batasan |
| **admin** | **Wajib** (FK ke grup) | Grup sendiri + semua turunannya. Bisa **lihat** grup induk (read-only). |
| **operator** | **Wajib** (FK ke grup) | Hanya scanner & presensi. **Tidak bisa melihat** menu Kelompok & Anggota. |

> 📌 **Keputusan #3:** `group_id` **wajib diisi** untuk role admin dan operator. Form user harus memvalidasi ini.

### 4.2 Matriks Hak Akses

| Resource / Fitur | super_admin | admin | operator |
|:-----------------|:-----------:|:-----:|:--------:|
| **Kelompok (Groups)** — lihat | ✅ Semua | ✅ Grup sendiri + turunan + induk (read-only) | ❌ **Menu dihilangkan** |
| **Kelompok (Groups)** — kelola | ✅ | ✅ Turunan saja (via `canBeManagedBy`) | ❌ |
| **Anggota (Members)** — lihat | ✅ Semua | ✅ Grup sendiri + turunan | ❌ **Menu dihilangkan** |
| **Anggota (Members)** — kelola | ✅ | ✅ Turunan saja | ❌ |
| **Pertemuan (Meetings)** — lihat | ✅ Semua | ✅ Grup sendiri + turunan | ✅ Grup sendiri + turunan |
| **Pertemuan (Meetings)** — buat | ✅ | ✅ Hanya untuk grupnya sendiri | ❌ |
| **Pertemuan (Meetings)** — edit/hapus | ✅ | ✅ Turunan saja | ❌ |
| **Scanner Station** | ✅ | ✅ Grup + turunan | ✅ Grup + turunan |
| **PDF Report** | ✅ | ✅ Grup + turunan | ✅ Grup + turunan |
| **Dashboard Widgets** | ✅ | ✅ | ✅ |
| **User Management** | ✅ | ❌ | ❌ |
| **Shield (Roles & Permissions)** | ✅ | ❌ | ❌ |

> 📌 **Keputusan #1:** Operator tidak bisa melihat menu Kelompok & Anggota sama sekali.  
> 📌 **Keputusan #2:** Scanner & PDF Report dibatasi ke grup sendiri + turunan untuk semua role non-super_admin.

### 4.3 Aturan Hierarki (Scoping Logic)

```
Super Admin → Tidak ada filter, lihat semua
Admin (Grup X) → getEloquentQuery() filter:
  - whereIn('group_id', $user->group->getAllDescendantIds())
  - getAllDescendantIds() = [Grup X sendiri, Anak X, Cucu X, ...]
Operator → Menu Groups & Members DIHILANGKAN dari sidebar (canAccess = false)
         → Meeting di-scope sama seperti Admin
         → Scanner & PDF di-validate group hierarchy
User tanpa group_id → Tidak mungkin terjadi (validasi di form UserResource)
```

### 4.4 Login & Authentication

- **Panel:** Filament Admin Panel (`/admin`)
- **Login Check:** `User::canAccessPanel()` → `hasRole(super_admin|admin|operator)`
- **Middleware:** `Authenticate::class` (Filament default)
- **Custom Routes:** `auth` middleware (Scanner, PDF)

---

## 5. Status Pengembangan

### 5.1 Overview

| Phase | Nama | Status | Periode |
|:------|:-----|:------:|:--------|
| Phase 1 | Core Architecture | ✅ **Selesai** | 22–25 Jan 2026 |
| Phase 2 | QR & Authorization | ✅ **Selesai** | 26–31 Jan 2026 |
| Phase 3 | Attendance Engine | ✅ **Selesai** | 1–3 Feb 2026 |
| Phase 4 | Reporting & Mobile UX | ✅ **Selesai** | 4–10 Feb 2026 |
| Phase 5 | QR Management & Advanced Export | ✅ **Selesai** | 11–16 Feb 2026 |
| **Phase 6** | **Monitoring & Optimization** | ⏳ **Current** | 18 Feb 2026 ~ |
| Phase 7 | Advanced Analytics & Attendance Insights | Direncanakan | — |
| Phase 8 | Member Cards & Optimization | Direncanakan | — |
| Phase 9 | Self-Permit System | Direncanakan | — |
| **Phase 10** | **On-the-Spot Registration** | **Direncanakan** | — |

### 5.2 Detail Fitur — Telah Selesai (P1–P5)

| Fitur | Phase | Status |
|:------|:-----:|:------:|
| Hierarki organisasi, Dynamic columns, Auto-age calculation | P1 | ✅ |
| QR Code auto-generation, Shield RBAC, Scoping logic | P2 | ✅ |
| Live Scanner, Real-time validation, Dashboard widgets | P3 | ✅ |
| Drill-down stats, Manual status (Izin/Sakit), Smart status | P4 | ✅ |
| Download QR (Single/Bulk), Scanner search filters, Multi-sheet Excel | P5 | ✅ |

### 5.3 Detail Fitur — Phase Aktif & Mendatang

> **SSOT tugas aktif:** `docs/todolist/phase-6-todolist.md`

| Fitur | Phase | Status |
|:------|:-----:|:------:|
| **Import data anggota massal (Excel/CSV)** | P6 | [ ] |
| Auto-mapping grup & auto-QR pada import | P6 | [ ] |
| Attendance Grid (Matrix), Dashboard Leaderboard | P7 | [ ] |
| **Performance Tuning (Lazy Loading & Caching)** | **P6** | **✅** |
| **Hierarchical Dashboard (Ancestor Support)** | **P6** | **✅** |
| **Role Flexibility (ENUM to String)** | **P6** | **✅** |
| Early Warning System (Low participation) | P7 | [ ] |
| Cetak kartu anggota (Bulk PDF A4) | P8 | [ ] |
| Branding laporan Excel (Header/Logo) | P8 | [ ] |
| Query optimization (Chunking/LazyCollection) | P8 | [ ] |
| Self-Permit Public Form & Approval system | P9 | [ ] |
| **Registrasi Anggota Baru Langsung di Scanner (On-the-spot)** | **P10** | **[ ]** |

### 5.4 Catatan Perpindahan Fitur

Fitur berikut **awalnya di Phase 4**, dipindahkan ke Phase 5 atas keputusan USER:
- Download QR Code (Single/Bulk) — *Alasan: Prioritas reporting lebih tinggi*
- ~~Deteksi Terlambat~~ — ✅ **Ternyata sudah diimplementasi** di Phase 4 (ditemukan di `LiveScannerController.process()` dan `manualStore()`)
- Filter Search Scanner — *Alasan: Ditunda bersama scanner enhancements. Filter grup sudah ada, perlu tambah filter gender & usia.*

---

## 6. Arsitektur Kode

### 6.1 Model & Relationships

```
User ──belongs_to──→ Group
Member ──belongs_to──→ Group
Member ──belongs_to──→ AgeGroup
Member ──has_many───→ Attendance

Group ──belongs_to──→ Level
Group ──belongs_to──→ Group (parent)
Group ──has_many───→ Group (children)
Group ──has_many───→ Member
Group ──has_many───→ User
Group ──has_many───→ Meeting

Meeting ──belongs_to──→ Group
Meeting ──belongs_to──→ User (creator)
Meeting ──has_many───→ Attendance

Attendance ──belongs_to──→ Meeting
Attendance ──belongs_to──→ Member

Level ──has_many───→ Group
AgeGroup ──(no inverse)──→ (missing members() — QA-15)
```

### 6.2 Filament Resource Structure

> 📌 **Keputusan #8:** `ChildGroupsRelationManager` akan **di-refactor menjadi custom Livewire component** di halaman ViewMeeting, bukan RelationManager.

```
app/Filament/Resources/
├── AgeGroups/AgeGroupResource.php
├── Groups/
│   ├── GroupResource.php          ← getEloquentQuery() scoping
│   ├── Schemas/GroupForm.php
│   └── Tables/GroupsTable.php
├── Levels/LevelResource.php
├── Meetings/
│   ├── MeetingResource.php        ← getEloquentQuery() scoping
│   ├── Schemas/MeetingForm.php, MeetingInfolist.php
│   ├── Tables/MeetingsTable.php
│   ├── Pages/
│   │   ├── CreateMeeting.php
│   │   ├── EditMeeting.php
│   │   ├── ListMeetings.php
│   │   ├── ViewMeeting.php
│   │   └── MeetingAttendanceDetails.php  ← Custom page
│   └── RelationManagers/
│       └── ChildGroupsRelationManager.php ← ❌ AKAN DI-REFACTOR (keputusan #8)
├── Members/
│   ├── MemberResource.php         ← getEloquentQuery() scoping
│   ├── Schemas/MemberForm.php, MemberInfolist.php
│   └── Tables/MembersTable.php
└── Users/
    ├── UserResource.php           ← getEloquentQuery() scoping
    ├── Schemas/UserForm.php
    └── Tables/UserTable.php
```

### 6.3 Custom Controllers & Routes

| Route | Controller | Method | Middleware | Fungsi |
|:------|:-----------|:-------|:-----------|:-------|
| `GET /live-scanner/{meeting}` | `LiveScannerController` | `index` | `auth` | Halaman scanner |
| `POST /live-scanner/{meeting}/process` | `LiveScannerController` | `process` | `auth` | Proses scan QR |
| `GET /live-scanner/{meeting}/search` | `LiveScannerController` | `search` | `auth` | AJAX search manual |
| `POST /live-scanner/{meeting}/manual` | `LiveScannerController` | `manualStore` | `auth` | Submit manual attendance |
| `GET /meeting/{meeting}/report/pdf` | `MeetingReportController` | `pdf` | `auth` | Download PDF report |

### 6.4 Observers

| Observer | Model | Events | Fungsi |
|:---------|:------|:-------|:-------|
| `MemberObserver` | `Member` | `saving`, `deleting` | Auto-calculate age, match age group, generate/delete QR code |

### 6.5 Widgets

| Widget | Type | Fungsi |
|:-------|:-----|:-------|
| `AttendanceOverview` | Stats | Kehadiran hari ini, anggota aktif, persentase |
| `AttendanceTrend` | Line Chart | Tren 10 hari terakhir |
| `GroupRanking` | Table | Ranking grup berdasarkan total kehadiran |

---

## 7. Kebijakan & Konvensi

### 7.1 Penamaan

| Area | Konvensi | Contoh |
|:-----|:---------|:-------|
| Model | Singular PascalCase | `Member`, `AgeGroup` |
| Migration | Laravel default | `create_members_table` |
| Filament Resource | Subfolder per-entity | `Resources/Members/MemberResource.php` |
| Form/Table classes | Extracted ke Schemas/Tables | `Schemas/MemberForm.php` |
| Routes | kebab-case | `/live-scanner/{meeting}` |
| Bahasa database | English column names | `full_name`, `meeting_date` |
| Bahasa UI | Indonesian labels | `Nama Lengkap`, `Tanggal Pertemuan` |

### 7.2 Data Format

| Data | Format | Contoh |
|:-----|:-------|:-------|
| Nama anggota | UPPERCASE (auto-transform) | "LUBISA ULIYAK" |
| Nama grup | UPPERCASE (auto-transform) | "CABANG JAKARTA" |
| Level code | UPPERCASE | "T3" |
| QR Code | **PNG** file at `storage/app/public/qrcodes/{member_code}.png` | `qrcodes/IT-2024-001.png` |
| Evidence | Image at `storage/app/public/attendance-evidences/` | — |

> 📌 **Keputusan #7:** QR Code diubah dari SVG ke **PNG** sebagai format utama — lebih kompatibel untuk download dan cetak kartu.
| Tanggal | `Y-m-d` (database), `d M Y` (display) | `2026-02-11` → `11 Feb 2026` |
| Waktu | `H:i` (display) | `09:30` |

### 7.3 Environment

| Key | Dev Value | Catatan |
|:----|:----------|:--------|
| `APP_NAME` | `inTime` | — |
| `APP_LOCALE` | `id` | Bahasa Indonesia |
| `DB_CONNECTION` | `mysql` | Satu-satunya driver yang didukung |
| `DB_DATABASE` | `intime_db` | — |
| `FILESYSTEM_DISK` | `local` | QR & evidence via `Storage::disk('public')` |
| `SESSION_DRIVER` | `database` | — |
| `QUEUE_CONNECTION` | `database` | — |

> **SSOT environment:** `.env` (aktif) dan `.env.example` (template).

---

## 8. Keputusan Arsitektur

Keputusan yang telah disahkan oleh Product Owner dan dicatat di SSOT:

| # | Tanggal | Keputusan | Dampak |
|:--|:--------|:----------|:-------|
| 1 | 11 Feb 2026 | Operator **tidak bisa melihat** menu Kelompok & Anggota | Perlu update `canAccess()` di GroupResource & MemberResource |
| 2 | 11 Feb 2026 | Scanner & PDF **dibatasi** ke grup sendiri + turunan | Perlu middleware/validasi di LiveScannerController & MeetingReportController |
| 3 | 11 Feb 2026 | `group_id` **wajib** untuk role admin & operator | Perlu validasi di UserForm, UserResource |
| 4 | 11 Feb 2026 | `sidebar-panel.md` **dihapus** | File dihapus, konten sudah di SSOT §4 |
| 5 | 11 Feb 2026 | `member_migrations` **dibatalkan** | Hapus dari implementation-plan §5.3 |
| 6 | 11 Feb 2026 | Kolom `attendance_type` **dihapus** | Perlu migration drop column |
| 7 | 11 Feb 2026 | QR Code format diubah dari **SVG ke PNG** | Perlu update MemberObserver, QR generation logic |
| 8 | 11 Feb 2026 | `ChildGroupsRelationManager` → **custom Livewire component** | Refactor arsitektur ViewMeeting page |
| 9 | 18 Feb 2026 | **Dashboard Lazy Loading** | Mengurangi beban CPU serentak di shared hosting |
| 10 | 18 Feb 2026 | **Hierarchical Dashboard Logic** | Menampilkan pertemuan pusat untuk user cabang (dengan filter data cabang) |
| 11 | 18 Feb 2026 | **Role Column Migration (String)** | Mendukung peran kustom tanpa batas tanpa modifikasi schema enum |

---

## 9. Changelog SSOT

| Tanggal | Perubahan |
|:--------|:----------|
| 11 Feb 2026 | 📄 Dokumen SSOT dibuat — konsolidasi dari seluruh 14 file dokumentasi dan 22 migration files |
| 11 Feb 2026 | 📝 Revisi 1 — 8 keputusan arsitektur dicatat dari sesi QA bottleneck |
| 16 Feb 2026 | 🔄 Revisi 2 — Sinkronisasi §5.3 Phase 5: Deteksi Terlambat di-checklist (sudah ada di kode), QR format dikoreksi ke PNG saja, filter search diperjelas scope-nya |
| 18 Feb 2026 | 🚀 Revisi 3 — Implementasi Dashbord Optimization (Lazy Loading, Caching) & Role Migration (ENUM to String) |

---

> 📌 **Cara menggunakan dokumen ini:**
> 1. Saat bingung "yang benar yang mana?" → cek dokumen ini dulu.
> 2. Saat menambah fitur baru → update §5 (Status) dan §3 (jika ada perubahan schema).
> 3. Saat ada developer baru → berikan `README.md` lalu `ssot.md`.
> 4. Saat dokumen lain konflik dengan dokumen ini → **dokumen ini yang benar**.
