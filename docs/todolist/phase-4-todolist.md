# 📊 Detailed Design Phase 4: QR Management & Mobile UX

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

## 2. UI/UX Responsive & Mobile Optimization (SELESAI ✅)
*   [x] **Infolist Optimization:**
    *   Ubah layout Infolist (Member & Meeting) agar menjadi 1 kolom di mobile menggunakan `columnSpan`.
    *   QR Code di View Member harus responsive (pusat di mobile, samping di desktop).
*   [x] **Table Optimization:**
    *   Sembunyikan kolom yang kurang krusial di mobile menggunakan `visibleFrom('md')`.
    *   Pastikan `ActionGroup` digunakan konsisten untuk menghemat ruang horizontal.
*   [x] **Live Scanner Mobile UX:**
    *   Optimasi Navbar agar tidak memakan banyak ruang.
    *   Optimasi ukuran box scanner `qrbox` (250px) untuk layar HP.
    *   Perbesar target tap pada tombol manual attendance (Hadir/Izin/Sakit).
    *   Implementasi `loading states` pada saat submit manual agar tidak double tap.
*   [x] **UI Polish:**
    *   Penyesuaian radius sudut (rounded) ke `2xl` agar lebih modern dan profesional.
    *   Peningkatan kontras warna tombol manual.

## 3. Definition of Done (DoD) - Phase 4
1.  Admin dapat mengelola data kehadiran lengkap dengan bukti fisik & keterangan.
2.  Scanner memberikan informasi akurat mengenai ketepatan waktu anggota.
3.  Tampilan aplikasi responsif dan nyaman digunakan di perangkat mobile (HP).
4.  Seluruh file bukti lampiran dapat diakses tanpa error 403.

---
*Terakhir dikerjakan: Optimasi Mobile UX & Radius UI (5 Feb 2026).*
