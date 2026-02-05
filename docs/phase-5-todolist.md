# 📈 Detailed Design Phase 5: QR Management, Advanced Export & Member Cards

Dokumen ini merinci langkah-langkah teknis untuk Phase 5, mencakup fitur manajemen QR yang tersisa, ekspor data yang mendalam, dan pembuatan kartu anggota.

## 1. QR Code Management (Pindahan dari Phase 4 📥)
*   [ ] **Single Download:** 
    *   Tambahkan tombol 'Unduh QR' di Tabel Anggota.
    *   Format: PNG/JPG kualitas tinggi.
*   [ ] **Bulk Download:** 
    *   Action masal (Bulk Action) untuk mengunduh banyak QR Code sekaligus dalam file .ZIP.

## 2. Live Scanner Enhancements (Pindahan dari Phase 4 📥)
*   [ ] **Deteksi Terlambat:** 
    *   Logic: Bandingkan `now()` dengan `meeting.start_time`.
    *   Berikan label 'TERLAMBAT' pada data kehadiran jika melewati waktu.
*   [ ] **Filter Search Target:** Memastikan fitur 'Pencarian Manual' di scanner hanya memunculkan anggota yang sesuai kriteria (Grup, Gender, Usia).

## 3. Sistem Ekspor Excel Terpadu
*   [ ] **Ekspor Excel per Pertemuan:**
    *   **Sheet 1: Ringkasan Statistik** (Hierarki grup, total, hadir, %, dll).
    *   **Sheet 2: Detail Nama Anggota** (Daftar lengkap seluruh nama, status, dan waktu).
*   [ ] **Branding Laporan:** Header laporan yang rapi (Logo, Nama Organisasi, Judul Pertemuan).

## 4. Cetak Kartu Anggota (Member Cards)
*   [ ] **Template Desain:** Template kartu profesional berbasis HTML/CSS (Nama, Kode, Grup, QR).
*   [ ] **Bulk Printing PDF:** Generate PDF siap cetak dengan layout A4 (8-10 kartu per lembar).

## 5. Optimasi & Handover
*   [ ] **Penanganan Data Besar:** Optimasi memori (Chunking) untuk ribuan baris data Excel.
*   [ ] **Final Documentation:** Panduan penggunaan fitur pelaporan dan cetak kartu.

## 6. Definition of Done (DoD) - Phase 5
1.  QR Code dapat didistribusikan ke anggota sebagai file gambar mandiri (Single/Bulk).
2.  Scanner memberikan informasi ketepatan waktu anggota (Terlambat).
3.  Admin dapat mengunduh laporan Excel multi-sheet yang komprehensif.
4.  Kartu anggota dapat dicetak dalam jumlah banyak melalui satu file PDF yang rapi.
5.  Proses ekspor dan cetak berjalan stabil pada dataset besar.

---
*Status: Direncanakan (5 Feb 2026).*
