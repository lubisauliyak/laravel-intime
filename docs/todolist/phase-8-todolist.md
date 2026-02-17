# 💳 Detailed Design Phase 8: Member Cards & Optimization

Dokumen ini merinci fitur pencetakan fisik kartu anggota dan optimasi sistem untuk menangani data besar secara stabil.

## 1. Cetak Kartu Anggota (Member Cards)

> **Konteks Aktual:** Package `barryvdh/laravel-dompdf` sudah terinstall. Perlu template kartu dan bulk action.

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
4.  Dokumentasi penggunaan untuk admin telah selesai.

---
*Status: Direncanakan (16 Feb 2026)*
