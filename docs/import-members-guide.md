# 📥 Panduan Import Anggota dari Excel

> **SSOT:** Dokumen ini adalah sumber kebenaran untuk fitur import anggota.  
> **Terakhir diperbarui:** 19 Februari 2026  
> **Context7:** Pastikan coding standards sesuai dengan `.qwen/context7.md`

---

## Daftar Isi

1. [Overview](#overview)
2. [Download Template](#1-download-template)
3. [Format Kolom](#2-format-kolom)
4. [Langkah Import](#3-langkah-import)
5. [Fitur Otomatis](#4-fitur-otomatis)
6. [Validasi & Error Handling](#5-validasi--error-handling)
7. [Tips & Best Practices](#6-tips--best-practices)
8. [Troubleshooting](#7-troubleshooting)

---

## Overview

Fitur import anggota memungkinkan Anda untuk mengimport data anggota secara massal melalui file Excel (.xlsx). Sistem akan otomatis:

- ✅ Generate Member Code unik
- ✅ Generate QR Code
- ✅ Kalkulasi usia
- ✅ Match kategori usia
- ✅ Validasi data

**Permission Required:** `Import:Member`

---

## 1. Download Template

### Cara Download

1. Buka halaman **Daftar Anggota** di admin panel
2. Klik tombol **"Import Anggota"** (ikon panah naik)
3. Di dalam modal, klik tombol **"Download Template Excel"**
4. File akan otomatis terdownload: `Template_Import_Anggota_YYYYMMDD_HHMMSS.xlsx`

### Isi Template

Template terdiri dari **2 sheets**:

#### Sheet 1: "Template Import"
Berisi contoh data dengan format yang benar:

| member_code | full_name | group_name | nick_name | birth_date | gender | status | membership_type |
|-------------|-----------|------------|-----------|------------|--------|--------|-----------------|
| | CONTOH ANGGOTA PERTAMA | KELOMPOK CONTOH | CONTOH | 15/01/1990 | male | active | anggota |
| M202602190001 | CONTOH ANGGOTA KEDUA | KELOMPOK CONTOH | | 20/06/1995 | female | active | pengurus |

#### Sheet 2: "Panduan"
Dokumentasi lengkap setiap kolom:

| Kolom | Keterangan | Contoh/Nilai Valid |
|-------|------------|-------------------|
| member_code | Kode anggota (OPSIONAL) | Kosongkan untuk anggota baru. Isi jika ingin update data anggota yang sudah ada (contoh: M202602190001) |
| full_name | Nama lengkap anggota (WAJIB) | Contoh: BUDI SANTOSO |
| group_name | Nama grup/kelompok (WAJIB) | Harus sesuai dengan nama grup yang sudah terdaftar |
| nick_name | Nama panggilan (OPSIONAL) | Contoh: BUDI |
| birth_date | Tanggal lahir (OPSIONAL) | Format: DD/MM/YYYY (contoh: 15/01/1990). Kosongkan jika tidak ada. |
| gender | Jenis kelamin (OPSIONAL) | male (Laki-laki), female (Perempuan). Default: male |
| status | Status keaktifan (OPSIONAL) | active (Aktif), inactive (Non-aktif). Default: active |
| membership_type | Tipe keanggotaan (OPSIONAL) | anggota (Anggota), pengurus (Pengurus). Default: anggota |

---

## 2. Format Kolom

### Kolom Wajib

| Kolom | Type | Validasi | Catatan |
|-------|------|----------|---------|
| `full_name` | String | Required, max 255 | Akan otomatis diubah ke UPPERCASE |
| `group_name` | String | Required, max 255 | Case-insensitive lookup, harus ada di sistem |

### Kolom Opsional

| Kolom | Type | Default | Validasi | Catatan |
|-------|------|---------|----------|---------|
| `member_code` | String | null | max 50 | **PENTING:** Isi jika ingin update data anggota yang sudah ada. Kosongkan untuk anggota baru. |
| `nick_name` | String | null | max 255 | Akan otomatis diubah ke UPPERCASE |
| `birth_date` | Date | null | Format: DD/MM/YYYY | Jika kosong, age & age_group null, otomatis masuk kategori "Pra Nikah" |
| `gender` | Enum | 'male' | male, female | male = Laki-laki, female = Perempuan |
| `status` | Enum | 'active' | active, inactive | Status keanggotaan |
| `membership_type` | Enum | 'anggota' | anggota, pengurus | Tipe keanggotaan |

### Catatan Penting

- **member_code**: 
  - ✅ **Kosong** = Anggota baru, sistem auto-generate member code & QR code
  - ✅ **Diisi** = Update data anggota existing dengan member code tersebut
  
- **birth_date**:
  - Format yang diharapkan: **DD/MM/YYYY** (contoh: 15/01/1990)
  - Jika kosong: Anggota akan dimasukkan ke kategori **"Pra Nikah"**
  - Age dan age_group_id akan di-kalkulasi otomatis
  
- **gender**:
  - Gunakan: `male` atau `female`
  - Default: `male`
  
- **membership_type**:
  - Gunakan: `anggota` atau `pengurus`
  - Default: `anggota`

---

## 3. Langkah Import

### Step-by-Step

1. **Download Template**
   - Klik "Import Anggota" di halaman Members
   - Klik "Download Template Excel"
   - Simpan file template

2. **Isi Data**
   - Buka file template dengan Excel/Google Sheets/LibreOffice
   - **JANGAN UBAH NAMA KOLON HEADER**
   - Isi data mulai dari baris ke-2 (baris 1 adalah header)
   - Hapus baris contoh jika perlu
   - Simpan file (.xlsx atau .xls)

3. **Upload File**
   - Kembali ke modal import
   - Klik "Choose File" atau drag-drop file Excel
   - Tunggu upload selesai

4. **Proses Import**
   - Klik "Import Anggota"
   - Sistem akan memproses file
   - Notifikasi akan muncul jika berhasil/gagal

5. **Verifikasi**
   - Refresh halaman
   - Cek data anggota yang sudah diimport
   - QR Code otomatis di-generate

---

## 4. Fitur Otomatis

Saat import, sistem akan otomatis melakukan:

### 4.1 Generate Member Code
```
Format: M + YYYYMMDDHHMMSS + RANDOM4
Contoh: M20260219143025A7F3
```

### 4.2 Generate QR Code
- Format: PNG
- Lokasi: `storage/app/public/qrcodes/{member_code}.png`
- Ukuran: 300x300px
- Auto-linked ke member

### 4.3 Kalkulasi Usia
```php
age = today - birth_date (in years)
```

### 4.4 Match Kategori Usia
```php
age_group = AgeGroup where min_age <= age <= max_age
```

### 4.5 Group Lookup
- Case-insensitive matching
- Cari berdasarkan nama grup
- Jika tidak ditemukan → baris di-skip dengan error

### 4.6 Data Normalization
- `full_name` → UPPERCASE
- `nick_name` → UPPERCASE
- `gender` → lowercase
- `status` → lowercase
- `membership_type` → lowercase

---

## 5. Validasi & Error Handling

### Validasi Import

Import akan **GAGAL** per baris jika:

| Error | Penyebab | Solusi |
|-------|----------|--------|
| `Nama lengkap atau nama grup kosong` | Kolom full_name atau group_name kosong | Isi kedua kolom wajib tersebut |
| `Grup '{name}' tidak ditemukan` | Nama grup tidak ada di sistem | Cek nama grup di halaman Kelompok |
| `Format tanggal lahir tidak valid` | birth_date tidak sesuai format DD/MM/YYYY | Gunakan format DD/MM/YYYY (contoh: 15/01/1990) |
| `Gender harus salah satu dari: male, female` | Gender tidak valid | Gunakan 'male' atau 'female' |
| `Status harus salah satu dari: active, inactive` | Status tidak valid | Gunakan 'active' atau 'inactive' |
| `Tipe keanggotaan harus salah satu dari: anggota, pengurus` | membership_type tidak valid | Gunakan 'anggota' atau 'pengurus' |

### Error Handling

- **Baris error di-skip** → tidak menghentikan import keseluruhan
- **Baris valid tetap di-import** → proses berlanjut
- **Log error** → tercatat di Laravel log (`storage/logs/laravel.log`)
- **Notifikasi** → user mendapat notifikasi sukses/gagal

### Contoh Log Error
```log
[2026-02-19 14:30:25] local.INFO: Member import completed
{
    "imported": 45,
    "failed_rows": 3,
    "failed_details": [
        {"row": 5, "reason": "Grup 'JAKARTA PUSAT' tidak ditemukan"},
        {"row": 12, "reason": "Format tanggal lahir tidak valid"},
        {"row": 28, "reason": "Nama lengkap atau nama grup kosong"}
    ]
}
```

---

## 6. Tips & Best Practices

### ✅ DO

- ✅ **Download template resmi** — pastikan format kolom sesuai
- ✅ **Backup data** sebelum import besar-besaran
- ✅ **Test dengan data kecil** (5-10 baris) sebelum import massal
- ✅ **Gunakan format DD/MM/YYYY** untuk tanggal lahir (contoh: 15/01/1990)
- ✅ **Pastikan grup sudah ada** di sistem sebelum import
- ✅ **Refresh halaman** setelah import untuk melihat hasil
- ✅ **Cek notifikasi error** untuk baris yang gagal
- ✅ **Kosongkan member_code** untuk anggota baru
- ✅ **Isi member_code** jika ingin update data anggota existing

### ❌ DON'T

- ❌ **Jangan ubah nama kolom header** — sistem tidak akan recognize
- ❌ **Jangan hapus baris header** — wajib ada untuk validasi
- ❌ **Jangan gunakan format tanggal lain** — selain DD/MM/YYYY
- ❌ **Jangan import file besar** (>10MB) — split menjadi beberapa file
- ❌ **Jangan skip validasi** — cek data sebelum upload
- ❌ **Jangan gunakan l/p** untuk gender — gunakan 'male' atau 'female'
- ❌ **Jangan gunakan member/pengurus** — gunakan 'anggota' atau 'pengurus'

---

## 7. Troubleshooting

### Problem: "File tidak ditemukan"

**Penyebab:** File terhapus atau path salah  
**Solusi:** Re-upload file Excel

### Problem: "Import Gagal — Timeout"

**Penyebab:** File terlalu besar (>1000 baris)  
**Solusi:** Split file menjadi beberapa bagian (max 500 baris per file)

### Problem: "Grup tidak ditemukan"

**Penyebab:** Nama grup di Excel tidak sama dengan di sistem  
**Solusi:** 
1. Cek nama grup di halaman Kelompok
2. Copy-paste nama grup dari sistem ke Excel
3. Pastikan tidak ada typo/spasi berlebih

### Problem: "QR Code tidak muncul"

**Penyebab:** Storage link belum dibuat  
**Solusi:** 
```bash
php artisan storage:link
```

### Problem: "Data tidak ter-calculate usia"

**Penyebab:** birth_date kosong atau format salah  
**Solusi:** Pastikan birth_date format YYYY-MM-DD

---

## 8. Technical Details

### Import Flow Architecture

```
User Upload Excel
    ↓
ListMembers.action()
    ↓
Excel::import(new MemberImporter(), $file)
    ↓
MemberImporter.model()
    ├─ Validate required fields
    ├─ findGroup(group_name)
    ├─ calculateAge(birth_date)
    ├─ determineAgeGroup(age)
    ├─ generateMemberCode()
    └─ Create Member
         ↓
    MemberObserver.saving()
    ├─ Calculate age
    ├─ Match age_group
    └─ Generate QR Code
```

### File Locations

| File | Path |
|------|------|
| Importer Class | `app/Filament/Imports/MemberImporter.php` |
| Template Export | `app/Exports/MemberTemplateExport.php` |
| Import Page | `app/Filament/Resources/Members/Pages/ListMembers.php` |
| QR Storage | `storage/app/public/qrcodes/` |
| Import Temp | `storage/app/public/imports/` |

### Performance

| Metric | Target |
|--------|--------|
| Max file size | 10MB |
| Max rows per import | 1000 (recommended: 500) |
| Batch size | 100 rows |
| Average import time | ~2-5 detik per 100 rows |

---

## Related Documentation

- **SSOT:** `docs/ssot.md` — Single Source of Truth
- **Phase 6 Todo:** `docs/todolist/phase-6-todolist.md` — Active tasks
- **Context7:** `.qwen/context7.md` — Coding standards
- **README:** `README.md` — Project overview

---

*Dibuat dengan ❤️ untuk inTime — Phase 6: Import & Template*
