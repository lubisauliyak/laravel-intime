# 🗂️ Filament Sidebar Panel Structure

Dokumen ini merinci struktur menu sidebar yang akan diimplementasikan pada Filament Admin Panel untuk sistem **inTime**. Menu disesuaikan berdasarkan Role dan Level akses.

---

## 1. Grup Menu: Master Data
*Hanya dapat diakses oleh Admin & Super Admin.*

*   **🏷️ Kelompok (Groups)**
    *   Fungsi: Manajemen hierarki (Tingkat 3, 2, 1).
    *   Akses: Super Admin (Semua), Admin (Dibatasi scope kelompoknya).
*   **👥 Anggota (Members)**
    *   Fungsi: Manajemen data personil, kategori usia, dan cetak kartu QR.
    *   Akses: Super Admin & Admin.

---

## 2. Grup Menu: Aktivitas
*Dapat diakses oleh Admin (untuk manajemen) dan Operator (untuk entry).*

*   **📅 Pertemuan (Meetings)**
    *   Fungsi: Membuat jadwal pertemuan, menentukan target gender/usia.
    *   Akses: Super Admin & Admin (Membuat), Operator (Hanya melihat daftar).
*   **📸 Presensi (Scan & Manual)**
    *   Fungsi: Tampilan terpadu untuk absensi. Bagian atas untuk Scanner Kamera, bagian bawah untuk List/Search Anggota.
    *   Akses: Super Admin, Admin, dan Operator.

---

## 3. Grup Menu: Laporan
*Dapat diakses oleh Admin & Super Admin.*

*   **📊 Statistik Kehadiran**
    *   Fungsi: Visualisasi grafik persentase per kelompok.
*   **📑 Rekap Per Kelompok**
    *   Fungsi: Export data kehadiran ke Excel/PDF.

---

## 4. Grup Menu: Pengaturan Sistem
*Hanya untuk Super Admin.*

*   **👤 Manajemen User**
    *   Fungsi: Pengaturan akun Admin & Operator beserta penempatan kelompoknya.
*   **🛡️ Roles & Permissions**
    *   Fungsi: Pengaturan hak akses teknis.

---

## 5. Ringkasan Hak Akses Sidebar

| Nama Menu | Super Admin | Admin (T1-T3) | Operator |
| :--- | :---: | :---: | :---: |
| Kelompok | ✅ | ✅ (Limited Scope) | ❌ |
| Anggota | ✅ | ✅ (Limited Scope) | ❌ |
| Pertemuan | ✅ | ✅ (Own Group Only) | 👁️ (View Only) |
| Presensi (Scan & Manual) | ✅ | ✅ | ✅ |
| Laporan | ✅ | ✅ (Scoped) | ❌ |
| Manajemen User | ✅ | ❌ | ❌ |

---
*Catatan: Ikon sidebar menggunakan Heroicons (Default Filament).*
