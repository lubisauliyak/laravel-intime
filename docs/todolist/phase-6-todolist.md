# 📊 Detailed Design Phase 6: Monitoring Global & Operasional

Dokumen ini merinci langkah-langkah implementasi fitur monitoring dasbor untuk pemangku kebijakan, pemurnian otorisasi scanner, serta rencana fitur import data anggota.

## 1. Monitoring Global & Detail (Dashboard Enhancements) - [SELESAI ✅]
*   [x] **Peta Gender (Pie Chart):** Menampilkan distribusi gender anggota aktif.
*   [x] **Analisis Kedisiplinan (Doughnut Chart):** Rasio "Tepat Waktu" vs "Terlambat".
*   [x] **Partisipasi per Usia (Bar Chart):** Grafik keaktifan per kategori usia.
*   [x] **Live Scan Feed (Table Widget):** 10 aktivitas scan terakhir secara real-time.
*   [x] **Jam Sibuk Scanner (Line Chart):** Grafik distribusi jumlah scan per jam (Hari Ini).

## 2. Pemurnian Otorisasi & UI UX - [SELESAI ✅]
*   [x] **Sinkronisasi Izin Scanner:**
    *   Mengintegrasikan tombol di Tabel Pertemuan & Detail Pertemuan dengan izin `View:ScanAttendance`.
    *   Memastikan halaman scanner menolak akses jika izin tidak ada (403).
*   [x] **Hierarki Data Widget:** Memastikan semua widget dasbor menampilkan data **Grup Sendiri + Grup di Bawahnya**.
*   [x] **Localization ID:**
    *   `SetExcusedAttendance` -> "Input Izin dan Sakit".
    *   `ScanAttendance` -> "Gunakan Scanner".
    *   Kategori "Custom" -> "Widget Scanner".
*   [x] **Security Shield:** Penambahan `HasPageShield` & `HasWidgetShield` pada semua entitas dasbor agar sinkron dengan centang izin.

## 3. Fitur Import Data Anggota dari Excel - [ON PROGRESS 🏗️]
*   [ ] **Requirement Check:** Pastikan `maatwebsite/excel` atau library pendukung Filament Import sudah siap.
*   [ ] **Create Importer Class:** `app/Filament/Imports/MemberImporter.php`.
*   [ ] **Pencarian Group otomatis:** Mapping `group_id` berdasarkan nama yang diinput di Excel.
*   [ ] **Auto-Generation QR:** Memastikan anggota yang di-import otomatis memiliki QR Code.

## 4. Optimasi Performa & Struktur Data Berjenjang - [SELESAI ✅]
*   [x] **Dashboard Performance Tuning (Shared Hosting Ready):**
    *   Implementasi **Lazy Loading** pada 8 widget (memecah request, cegah CPU spiking).
    *   **Persistent File Caching** untuk data widget dan struktur hierarki grup.
    *   Eliminasi **Polling Interval** yang berlebihan (pindah ke manual refresh/on-load).
    *   Optimasi query **N+1** pada widget partisipasi usia (Single Query dengan Joins).
*   [x] **Refinansi Logika Hierarki Dasbor:**
    *   Widget kini mendeteksi pertemuan terbaru dari **Own Group** atau **Parent Group** (Atasan).
    *   **Pengecualian Children:** Pertemuan dari grup di bawah (cabang) tidak muncul di dasbor atasan untuk menjaga fokus koordinasi.
    *   Filter kontribusi tetap presisi: angka statistik hanya menghitung anggota di bawah wewenang user login.
*   [x] **Fleksibilitas Role & Peran Pengguna:**
    *   Migrasi kolom `role` dari `ENUM` ke `string` untuk mendukung peran kustom dinamis (Spatie).
    *   Standarisasi tampilan UI: Nama Peran otomatis **UPPERCASE** di tabel manajemen pengguna.
    *   Implementasi Hierarchical Access Policy pada Pertemuan (Cegah edit/delete data atasan oleh bawahan).

---

## 5. Definition of Done (DoD) - Phase 6
1. [x] Pemangku kebijakan dapat memantau data strategis dan operasional secara real-time di Dasbor.
2. [x] Fitur "Buka Scanner" hanya muncul dan bisa diakses jika izin dicentang (Full Security).
3. [x] Dasbor berjalan ringan di Shared Hosting berkat Lazy Loading & Caching.
4. [x] User tidak dapat mengubah/menghapus data milik atasan (Hierarchical Integrity).
5. [ ] Admin dapat mengunggah file anggota secara massal (Excel/CSV) tanpa error mapping.

---
*Status: Aktif (18 Feb 2026 - Update Performance & Hierarchy).*
