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

*   [x] **Custom Styling & Logic Refinement:** 🆕
    *   [x] Header laporan: Nama Organisasi, Judul Pertemuan, Tanggal (Tanpa background fill).
    *   [x] Branding warna: Latar belakang biru muda (`#CEDCEA`) khusus untuk level DAERAH & DESA.
    *   [x] Hierarchical Aggregation: Sheet Ringkasan menghitung kehadiran secara berjenjang (Desa masuk ke dalam statistik Daerah).
    *   [x] Dynamic Row Height: Implementasi tinggi baris otomatis untuk data multi-line (Dapukan pengurus) dengan minimum height 18.75.
    *   [x] Table 2 Cleanup: Reorganisasi kolom Rekapitulasi Pengurus (Nama, Level, Hadir berdampingan).
    *   [x] Filter Accuracy: Sinkronisasi kriteria target (Gender, Usia, Wilayah) antara sistem dan ekspor Excel.
    *   [x] Penanganan Alpa: Tampilkan cell kosong (string kosong) untuk target yang tidak hadir agar labih bersih.

## 4. Definition of Done (DoD) - Phase 8
1.  Kartu anggota dapat dicetak dalam jumlah banyak melalui satu file PDF yang rapi (Layout A4).
5.  Laporan Excel memiliki branding profesional (Color coding berjenjang & Clean layout).
6.  Filter laporan kehadiran berfungsi secara cerdas (Dependent filtering & Target logic synchronization).
7.  Dokumentasi penggunaan untuk admin telah selesai.

---
*Status: Aktif (Update 06 Mar 2026 — Advanced Excel Engine & Report Refinement)*
