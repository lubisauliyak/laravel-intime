# ⏳ Development Timeline: inTime

## 🚀 Foundation & Setup (22 - 25 Januari 2026)
*   **Inisialisasi Project**: Setup Laravel 12 dan Filament PHP v5.
*   **Arsitektur Database**: Perancangan skema tabel `groups`, `members`, dan `users` dengan dukungan hierarki.
*   **Hierarki Organisasi**: Implementasi struktur kelompok bertingkat (Unlimited Depth) menggunakan `parent_id`.

## 🏗️ Phase 1 & 2: Core, Security & QR (26 - 31 Januari 2026)
*   **Hierarchy Engine**: 
    *   Implementasi breadcrumbs dan navigasi tree-view.
    *   **Dynamic Group Columns**: Tabel anggota menampilkan kolom tingkat secara dinamis.
*   **QR Code Integration**: Otomatisasi pembuatan QR Code (SVG) untuk identitas anggota.
*   **RBAC (Role-Based Access Control)**: filament-shield & hierarchical scoping.
*   **Refinement**: Kategori usia otomatis, soft deletes, dan normalisasi data.

## 📡 Phase 3: Attendance Operations (1 - 3 Februari 2026)
*   **Manajemen Pertemuan (Meetings)**: Modul jadwal terintegrasi dengan filter target (Gender & Usia).
*   **Custom Live Scanner Station**:
    *   HUD kamera futuristik dengan batch processing.
    *   Tabel kehadiran real-time dan feedback suara.
    *   **Logic SC-5**: Validasi otomatis keaktifan, target kriteria, dan scan ganda.

## 📊 Phase 4: Reporting & QR Management (4 Februari 2026 - Present)
*   **Advanced Attendance Details**:
    *   **Drill-down Statistics**: Tabel statistik per sub-grup secara hierarkis di halaman pertemuan.
    *   **Smart Attendance Status**: Otomatisasi status 'BELUM HADIR' ke 'TIDAK HADIR' berdasarkan jam selesai.
    *   **Manual Management**: Fitur 'Set Status' susulan & 'Hapus Presensi' dalam dropdown ActionGroup.
    *   **Evidence System**: Dukungan lampiran foto bukti (Izin/Sakit) dan catatan keterangan (Public Storage).
*   **QR Management (InProgress)**: Fitur pengunduhan aset gambar QR Code (Single/Bulk).
*   **Scanner Polishing (InProgress)**: Deteksi keterlambatan otomatis dan optimasi filter pencarian.

## 📈 Phase 5: Final: Deep Reporting & Member Cards (Planned)
*   **Unified Excel Report**: Laporan multi-sheet (Ringkasan Statistik + Detail Rincian Nama).
*   **Bulk Member Cards**: Cetak kartu fisik masal via PDF (Layout A4).
*   **Optimasi Data Besar**: Implementasi chunking/query optimization untuk dataset >5000 records.

---
*Terakhir diperbarui: 4 Februari 2026*
