# 📉 Detailed Design Phase 5: QR, Reports & Permission Migration

Dokumen ini merinci langkah-langkah teknis untuk Phase 5, mencakup fitur manajemen QR, perbaikan laporan rekapitulasi, dan migrasi sistem otorisasi ke Spatie.

> **Referensi SSOT:** `docs/ssot.md` §5.3

## 1. QR Code Management & Scanner (Pindahan dari Phase 4 📥) - [SELESAI ✅]

*   [x] **Single & Bulk Download QR:** ✅
    *   Tersedia di `MembersTable.php` (Individual & Bulk ZIP).
*   [x] **Live Scanner Enhancements:** ✅
    *   Deteksi terlambat otomatis.
    *   Filter target kriteria (Gender, Usia, Grup).

## 2. Sistem Ekspor Excel Terpadu - [SELESAI ✅]

*   [x] **Ekspor Excel per Pertemuan (Multi-Sheet):** ✅
    *   Sheet 1: Ringkasan Statistik per Grup.
    *   Sheet 2: Detail Nama Anggota & Status.

## 3. Rekap Kehadiran Global (Attendance Report Enhancements) - [SELESAI ✅]

*   [x] **UI/UX Optimization:** ✅
    *   Kolom Izin/Sakit & Tanpa Keterangan sudah tersedia.
    *   Badge warna persentase sudah akurat.
*   [x] **Global Export Excel:** ✅
    *   Export dengan header periode & identitas pencetak sudah diimplementasi.

## 4. Migrasi Role Hardcoded ke Spatie Permission - [NEW 🚀]

Tujuan: Mengalihkan logika `hasRole('admin')` ke `$user->can('permission')`.

### 4.1. Core Models & Logic - [SELESAI ✅]
- [x] **App\Models\User.php**: Rebranding helper methods & update `canAccessPanel`.
- [x] **App\Models\Group.php**: Update `canBeManagedBy` dengan permission check/helper.

### 4.2. Filament UI & Otorisasi - [SELESAI ✅]
- [x] **MeetingsTable.php**: Update visibility Action (Edit, Export) ke permission.
- [x] **UserResource & UserForm**:
    - [x] Update `getEloquentQuery` (Filter view admin/superadmin).
    - [x] Update `UserForm`: Integrasi Select Role dari Spatie Roles.
- [x] **Policies**: Integrasi barrier grup ke `MeetingPolicy`, `MemberPolicy`, `UserPolicy`, dan `GroupPolicy`.
- [x] **Live Scanner**: Integrasi custom permission `scan_attendance` dan `set_excused_attendance`.

### 4.3. Dashboards & Widgets - [SELESAI ✅]
- [x] **Widgets**: Update filter data (Trend, Ranking, Overview).

---

## 5. Definition of Done (DoD) - [SELESAI ✅]
1. [x] Admin dapat melihat dan mengekspor rekap kehadiran global secara akurat.
2. [x] Seluruh tombol dan fitur sensitif dikendalikan oleh Role & Permission Spatie (via Shield UI).
3. [x] Tidak ada lagi pengecekan role manual (`hasRole`) di level controller/view (kecuali Super Admin bypass).

---
*Status: Selesai (17 Feb 2026).*
