# 💳 Detailed Design Phase 8: Analytics, Cards & UI Refinement

> **Status:** ⏳ Current / In Progress  
> **Periode:** 20 Februari 2026 ~  

Dokumen ini merinci fitur analitik kehadiran, pencetakan kartu anggota, dan perbaikan UI operasional.

## 1. UI & Analytics (Prioritas Baru 🚀)

*   [x] **Branding Panel (inTime Identity):** 🆕
    *   [x] Menyelaraskan font Filament dengan landing page (**Manrope**).
    *   [x] Implementasi Palette **Emerald** (#065f46) & Background Light/Dark sesuai branding.
    *   [x] Custom Sidebar: Indigo labels, Royal Blue active items, dan efek glassmorphism pada Topbar.
*   [x] **Refined Attendance Report Filters:** 🆕
    *   [x] Implementasi **Dependent Select** (Pilih Desa → Filter Kelompok).
    *   [x] Sinkronisasi `parent_id` untuk filter Desa (sesuai skema database).
    *   [x] Custom `noOptionsMessage` untuk panduan interaksi user.
    *   [x] Perbaikan `TypeError` pada param `Get` data (Filament v3 Compatibility).
*   [ ] **Matriks Kehadiran (Pindahan Phase 7):**
    *   [ ] Tabel pivot kehadiran Anggota vs Tanggal.
    *   [ ] Filter Gender & Kategori Usia.

## 2. Cetak Kartu Anggota (Member Cards)

*   [ ] **Template Desain:** 
    *   Template kartu profesional berbasis **HTML/CSS** (Blade view).
    *   Konten: Nama lengkap, Member Code, Grup, QR Code (PNG).
    *   Ukuran kartu standar (85.6mm × 54mm).
*   [ ] **Bulk Printing PDF:** 
    *   Bulk Action di `MembersTable.php` untuk generate PDF kartu terpilih.
    *   Layout A4 (8-10 kartu per lembar) dengan garis potong.

## 2. Optimasi & Handover

*   [ ] **Penanganan Data Besar:** 
    *   Optimasi memori (Chunking) untuk ribuan baris data Excel.
    *   Gunakan `FromQuery` + `LazyCollection`.
*   [ ] **Final Documentation:** 
    *   Panduan penggunaan fitur pelaporan dan cetak kartu.
    *   Update `README.md` secara menyeluruh.

## 3. Branding Laporan Excel (Pindahan dari Phase 5 📥)

*   [ ] **Custom Styling:**
    *   Header laporan: Logo organisasi, Nama Organisasi, Judul Pertemuan, Tanggal.
    *   Implementasi via `WithEvents` + `AfterSheet` atau `WithCustomStartCell` pada `maatwebsite/excel`.

## 4. Definition of Done (DoD) - Phase 8
1.  Kartu anggota dapat dicetak dalam jumlah banyak melalui satu file PDF yang rapi (Layout A4).
2.  Laporan Excel memiliki branding profesional (Logo/Header).
3.  Proses ekspor dan cetak berjalan stabil pada dataset besar (Optimasi memori).
4.  Panel Admin memiliki identitas visual yang konsisten dengan Landing Page (**inTime Branding**).
5.  Filter laporan kehadiran berfungsi secara cerdas (Dependent filtering).
6.  Dokumentasi penggunaan untuk admin telah selesai.

---
*Status: Aktif (Update 05 Mar 2026 — inTime Branding & Report Refinement)*
