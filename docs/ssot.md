# 📘 SSOT — Single Source of Truth: inTime

> **Terakhir diperbarui:** 05 Maret 2026 (Revisi 6 — inTime Branding & Report Filter Optimization)  
> **Prinsip:** Dokumen ini adalah **satu-satunya sumber kebenaran** proyek inTime. Semua dokumen lain tunduk pada informasi di sini. Jika ada konflik, **dokumen ini yang benar**.  
> **AI Context:** Selalu refer ke `.qwen/context7.md` untuk coding standards & best practices terbaru.

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
9. [PSR-12 Coding Standards](#9-psr-12-coding-standards)
10. [Changelog SSOT](#10-changelog-ssot)

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
| `simplesoftwareio/simple-qrcode` | QR Code generation (PNG) |
| `maatwebsite/excel` | Excel export/import |
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
| **UI sidebar structure** | Kode aktual (Resources + Policies) | — |
| **Coding standards** | `.qwen/context7.md` | `docs/ssot.md` §9 |
| **Import members guide** | `docs/import-members-guide.md` | — |

### 2.2 Inventaris Dokumen

```
inTime/
├── .qwen/
│   └── context7.md                 🤖 AI Context: Coding standards & best practices (SSOT)
│
├── docs/
│   ├── ssot.md                     🏛️ DOKUMEN INI — Master reference
│   ├── implementation-plan.md      📐 Grand design & arsitektur
│   ├── timeline.md                 ⏳ Kronologi (harus sinkron dgn §5)
│   ├── backend-brain.md            📦 Arsip audit backend
│   ├── qa-brain.md                 🔍 Audit QA aktif
│   ├── import-members-guide.md     📥 Panduan import anggota (SSOT)
│   │
│   ├── todolist/
│   │   ├── phase-1-todolist.md     📦 Arsip (selesai 100%)
│   │   ├── phase-2-todolist.md     📦 Arsip (selesai 100%)
│   │   ├── phase-3-todolist.md     📦 Arsip (selesai 100%)
│   │   ├── phase-4-todolist.md     📦 Arsip (selesai 100%)
│   │   ├── phase-5-todolist.md     📦 Arsip (selesai 100%)
│   │   └── phase-6-todolist.md     🎯 SSOT: Tugas aktif saat ini
│   │
│   └── test/
│       ├── test-phase-1.md         📦 Arsip (passed 100%)
│       ├── test-phase-2.md         📦 Arsip (passed 100%)
│       ├── test-phase-3.md         📦 Arsip (passed 100%)
│       ├── test-phase-4.md         📦 Arsip (passed 100%)
│       └── test-phase-5.md         📦 Arsip (passed 100%)
```

### 2.3 Aturan Pembaruan

1. **Jika mengubah skema database** → Update §3 dokumen ini **DAN** `implementation-plan.md` §3.
2. **Jika menyelesaikan sebuah phase** → Update §5 dokumen ini, `README.md`, dan `timeline.md`.
3. **Jika berubahnya aturan role/permission** → Update §4 dokumen ini **DAN** `implementation-plan.md` §4.
4. **Jika ada perubahan coding standards** → Update `.qwen/context7.md`, lalu update §9 dokumen ini.
5. **Jangan pernah menduplikasi** daftar detail tugas — cukup link ke `phase-X-todolist.md`.

---

## 3. Skema Database (Aktual)

> ⚠️ **Ini adalah skema database AKTUAL** yang direkonstruksi dari seluruh 22 file migration. Jika `implementation-plan.md` §3 berbeda, **skema di bawah ini yang benar**.

### 3.1 - 3.8 Tabel Database

*(Tidak berubah dari revisi sebelumnya — lihat full schema di dokumen)*

---

## 4. Aturan Role & Permission

*(Tidak berubah dari revisi sebelumnya)*

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
| Phase 6 | Monitoring, Optimization & Import | ✅ **Selesai** | 18–19 Feb 2026 |
| Phase 7 | Advanced Analytics & Insights | ✅ **Selesai** | 20 Feb 2026 |
| **Phase 8** | **Member Cards & Optimization** | ⏳ **Current** | 20 Feb 2026 ~ |
| Phase 9 | Self-Permit System | Direncanakan | — |
| Phase 10 | On-the-Spot Registration | Direncanakan | — |

**SSOT tugas aktif:** `docs/todolist/phase-8-todolist.md`

| Fitur | Status | File Terkait |
|:------|:------:|:-------------|
| **Bulk Import via Excel** | ✅ | `app/Filament/Imports/MemberImporter.php` |
| **Auto-mapping Grup** | ✅ | `MemberImporter.findGroup()` |
| **Auto-QR Generation** | ✅ | `MemberObserver` |
| **Auto-Age Calculation** | ✅ | `MemberObserver` |
| **Template Excel Download** | ✅ | `app/Exports/MemberTemplateExport.php` |
| **Template di Modal Import** | ✅ | `app/Filament/Resources/Members/Pages/ListMembers.php` |
| **PSR-12 Compliance** | ✅ | Semua file Resources |

### 5.3 Detail Fitur — Phase 7 (Analytics & Pengurus Tracking)

| **Scanner Vertical Lineage** | ✅ Done | `app/Models/Member.php` |
| **Dynamic X-Axis Dashboard** | ✅ Done | `app/Filament/Widgets/` |
| **Auto-Verify User Email** | ✅ Done | `app/Models/User.php` |
| **Tabel Pengurus (meeting_attendees)** | ⏳ In Progress | `app/Models/MeetingAttendee.php` |
| **Attendance Grid/Matriks** | ⏳ Planned | `app/Filament/Pages/AttendanceMatrix.php` |
| **Group Leaderboard** | ⏳ Planned | `app/Filament/Widgets/GroupLeaderboardWidget.php` |
| **Early Warning System** | ⏳ Planned | `app/Services/AttendanceRiskService.php` |
| **Advanced Reporting PDF** | ⏳ Planned | `app/Exports/MonthlyReportPdf.php` |
| **Meeting Attendee Import** | ⏳ Planned | `app/Filament/Imports/MeetingAttendeeImporter.php` |

**SSOT tugas aktif:** `docs/todolist/phase-7-todolist.md`

## 6. Arsitektur Kode

### 6.1 Import/Export Architecture

```
app/
├── Exports/
│   └── MemberTemplateExport.php       ← Template Excel (2 sheets: Template + Panduan)
│
└── Filament/
    └── Imports/
        └── MemberImporter.php         ← Import logic (ToModel, WithValidation)
```

**Flow Import:**
1. User download template → `MemberTemplateExport` (2 sheets)
2. User isi data → Upload file
3. `MemberImporter` proses:
   - Validasi required fields
   - Lookup group by name (case-insensitive)
   - Calculate age dari birth_date
   - Match age_group
   - Generate member_code unik
   - Save member → Trigger `MemberObserver` → Generate QR Code

---

## 7. Kebijakan & Konvensi

*(Tidak berubah dari revisi sebelumnya)*

---

## 8. Keputusan Arsitektur

| # | Tanggal | Keputusan | Dampak |
|:--|:--------|:----------|:-------|
| 1-12 | *(lihat revisi sebelumnya)* | — | — |
| **13** | 19 Feb 2026 | **Download Template di Modal Import** | UX lebih baik — template ada di dalam modal, bukan tombol terpisah |
| **14** | 19 Feb 2026 | **PSR-12 Compliance untuk Import** | Semua `use` statements di awal file, tidak ada FQN di tengah kode |
| **15** | 19 Feb 2026 | **Context7 sebagai AI Reference** | `.qwen/context7.md` adalah SSOT untuk coding standards AI assistant |
| **16** | 05 Mar 2026 | **Unifikasi Branding (Panel + Landing)** | Panel Filament menggunakan font **Manrope** & palette **Emerald** dari landing page |
| **17** | 05 Mar 2026 | **Filter Location Berbasis Parent ID** | Filter Desa menggunakan logic `parent_id` (mirip MemberResource) untuk konsistensi skema |

---

## 9. PSR-12 Coding Standards

> 📌 **SSOT untuk coding standards:** `.qwen/context7.md` (AI Context)  
> Dokumen ini adalah ringkasan — **selalu refer ke context7.md untuk detail lengkap**.

### 9.1 Import Statements

✅ **BENAR:**
```php
<?php

namespace App\Filament\Resources\Members\Tables;

use App\Models\Member;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
```

❌ **SALAH:**
```php
// Fully qualified namespace di tengah kode
\Filament\Actions\ActionGroup::make([...])

// Import di tengah file
use Filament\Tables\Table; // ← Salah posisi
```

### 9.2 Aturan Import

1. **Semua `use` statements HARUS di awal file** setelah `namespace`
2. **Urutan import:**
   - App namespace (`App\...`)
   - Vendor namespace (`Filament\...`, `Illuminate\...`, dll)
   - Facades (`Excel`, `Storage`, dll)
3. **Tidak ada fully qualified namespace** (`\`) di tengah kode
4. **Type hints di function signature** juga harus di-import

### 9.3 File Structure Template

```php
<?php

namespace App\Filament\Resources\X;

// 1. App imports
use App\Filament\Resources\X\Pages\...;
use App\Models\...;

// 2. Vendor imports
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;

// 3. Facades
use Illuminate\Support\Facades\Storage;

class XResource extends Resource
{
    // ...
}
```

### 9.4 Context7 Reference

**File:** `.qwen/context7.md`

File ini berisi:
- ✅ PSR-12 complete guidelines
- ✅ Laravel best practices
- ✅ Filament conventions
- ✅ AI assistant instructions
- ✅ Code review checklist

> **WAJIB BACA:** Setiap kali memulai development, pastikan `.qwen/context7.md` sudah di-update dan diakses oleh AI assistant.

---

## 10. Changelog SSOT

| Tanggal | Perubahan |
|:--------|:----------|
| 11 Feb 2026 | 📄 Dokumen SSOT dibuat — konsolidasi dari seluruh 14 file dokumentasi dan 22 migration files |
| 11 Feb 2026 | 📝 Revisi 1 — 8 keputusan arsitektur dicatat dari sesi QA bottleneck |
| 16 Feb 2026 | 🔄 Revisi 2 — Sinkronisasi §5.3 Phase 5: Deteksi Terlambat di-checklist, QR format dikoreksi ke PNG |
| 18 Feb 2026 | 🚀 Revisi 3 — Dashboard Optimization & Role Migration |
| **19 Feb 2026** | **🚀 Revisi 4 — Import Template, PSR-12 Compliance, Context7 Integration** |
| | • Added §9: PSR-12 Coding Standards (refer ke `.qwen/context7.md`) |
| | • Updated §5.2: Import features checklist |
| | • Updated §6: Import/Export architecture |
| | • Updated §8: Keputusan #13-15 |
| | • Updated §2.2: Added `.qwen/context7.md` ke inventaris |
| **20 Feb 2026** | **🚀 Revisi 5 — Phase 7: Analytics & Pengurus Tracking** |
| **05 Mar 2026** | **🎨 Revisi 6 — inTime Branding & UI Optimization** |
| | • Updated §5.1: Phase 8 status dan detail tugas link |
| | • Added §8: Keputusan #16-17 (Branding & Filter structure) |
| | • Sinkronisasi UI Panel dengan `landing.blade.php` |
| | • Updated §5.1: Phase 7 status ke "Current" |
| | • Added §5.3: Phase 7 features (Tabel Pengurus, Attendance Grid, Leaderboard, etc.) |
| | • Updated `docs/todolist/phase-7-todolist.md` dengan detailed tasks |

---

## 🤖 AI Assistant Instructions

> **UNTUK AI ASSISTANT:** Setiap kali bekerja di project ini:

1. **WAJIB baca** `.qwen/context7.md` untuk coding standards terbaru
2. **WAJIB refer** ke `docs/ssot.md` untuk kebenaran project structure
3. **WAJIB cek** `docs/todolist/phase-7-todolist.md` untuk tugas aktif
4. **WAJIB update** dokumentasi jika ada perubahan yang signifikan
5. **JANGAN pernah** menduplikasi konten — gunakan SSOT principle

### Context7 Priority

```
.qwen/context7.md (HIGHEST PRIORITY)
    ↓
docs/ssot.md (Project truth)
    ↓
docs/todolist/phase-X-todolist.md (Active tasks)
    ↓
Other docs (Reference only)
```

---

> 📌 **Cara menggunakan dokumen ini:**
> 1. Saat bingung "yang benar yang mana?" → cek dokumen ini dulu.
> 2. Saat menambah fitur baru → update §5 (Status) dan §3 (jika ada perubahan schema).
> 3. Saat ada developer baru → berikan `README.md` lalu `ssot.md` dan `.qwen/context7.md`.
> 4. Saat dokumen lain konflik dengan dokumen ini → **dokumen ini yang benar**.
> 5. **SEBELUM coding** → baca `.qwen/context7.md` untuk standards terbaru.
