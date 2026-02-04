# 📊 Detailed Design Phase 4: QR Management & Advanced Actions

Dokumen ini merinci langkah-langkah teknis untuk Phase 4.

## 1. Reporting & Attendance Details (SELESAI ✅)
*   [x] **Statistik per Grup Turunan:** Implementasi tabel drill-down hierarki grup di halaman View Meeting.
*   [x] **Detail Presensi Grup:** Halaman khusus yang memuat daftar seluruh nama anggota per grup turunan.
*   [x] **Smart Status Logic:** 
    *   Waktu < Jam Selesai: Status 'BELUM HADIR' (Abu-abu).
    *   Waktu > Jam Selesai: Status 'TIDAK HADIR' (Merah).
*   [x] **Manual Management (ActionGroup):**
    *   Fitur **Set Status**: Pilihan Hadir, Izin, Sakit secara manual.
    *   Fitur **Hapus Presensi**: Menghapus data kehadiran untuk koreksi.
    *   Tampilan: Semua aksi dikelompokkan dalam menu titik tiga (...) agar rapi.
*   [x] **Sistem Lampiran Bukti:**
    *   Dukungan kolom **Keterangan** dan **Unggah Foto** saat set status Izin/Sakit.
    *   Akses lampiran via **Action 'Lihat Lampiran'** (Ikon Mata) dengan proteksi visibilitas.
    *   Penyimpanan: Public Storage (`storage/app/public/attendance-evidences`) untuk akses URL langsung.

## 2. QR Code Management (CURRENT ⏳)
*   [ ] **Single Download:** 
    *   Tambahkan tombol 'Unduh QR' di Tabel Anggota.
    *   Format: PNG/JPG kualitas tinggi.
*   [ ] **Bulk Download:** 
    *   Action masal (Bulk Action) untuk mengunduh banyak QR Code sekaligus dalam file .ZIP.

## 3. Live Scanner Enhancements (NEXT 📡)
*   [ ] **Deteksi Terlambat:** 
    *   Logic: Bandingkan `now()` dengan `meeting.start_time`.
    *   Berikan label 'TERLAMBAT' pada data kehadiran jika melewati waktu.
*   [ ] **Filter Search Target:** Memastikan fitur 'Pencarian Manual' di scanner hanya memunculkan anggota yang sesuai kriteria (Grup, Gender, Usia).

## 4. Definition of Done (DoD) - Phase 4
1.  Admin dapat mengelola data kehadiran lengkap dengan bukti fisik & keterangan.
2.  QR Code dapat didistribusikan ke anggota sebagai file gambar mandiri.
3.  Scanner memberikan informasi akurat mengenai ketepatan waktu anggota.
4.  Seluruh file bukti lampiran dapat diakses tanpa error 403.

---
*Terakhir dikerjakan: Implementasi Sistem Lampiran & ActionGroup (4 Feb 2026).*
