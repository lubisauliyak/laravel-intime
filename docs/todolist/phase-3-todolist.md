# 🏗️ Detailed Design Phase 3: Attendance & Reporting

Dokumen ini merinci langkah-langkah teknis untuk Phase 3. Fokus utama adalah fungsionalitas operasional yaitu sistem presensi berbasis QR Code dan pelaporan data.

## 1. Sistem Presensi (Attendance)
*   [x] **Skema Database:**
    *   Buat tabel `meetings` (id, group_id, name, date, description, updated_at, deleted_at).
    *   Buat tabel `attendances` (id, meeting_id, member_id, scanned_at, status, notes).
*   [x] **Scan QR Logic:**
    *   Buat halaman atau modal khusus "Scanner Station" yang dapat mengakses kamera.
    *   Integrasi library JS Scanner (seperti `html5-qrcode`).
    *   Logic Backend: Validasi QR Code -> Cari Member -> Catat Kehadiran ke tabel `attendances`.
    *   Feedback Visual: Suara atau animasi sukses/gagal saat scan.

## 2. Dashboard & Statistik
*   [x] **Filament Widgets:**
    *   Widget jumlah kehadiran hari ini.
    *   Chart tren kehadiran mingguan/bulanan.
    *   Ranking grup dengan tingkat kehadiran tertinggi.
*   [x] **Real-time Updates:**
    *   Gunakan Livewire polling untuk update dashboard otomatis saat scan berlangsung.

## 3. Sistem Pelaporan (Reporting)
*   [x] **Export Data:**
    *   Integrasi `maatwebsite/excel` untuk ekspor laporan ke Excel/CSV.
    *   Integrasi `barryvdh/laravel-dompdf` untuk pembuatan laporan PDF.
*   [x] **Fitur Laporan:**
    *   Laporan detail kehadiran per pertemuan.
    *   Rekapitulasi kehadiran per anggota (Persentase kehadiran).
    *   Filter laporan berdasarkan Rentang Tanggal dan Grup.

## 4. Definition of Done (DoD) - Phase 3
1.  Admin/Operator dapat melakukan presensi dengan scan QR Code anggota.
2.  Sistem menolak scan ganda untuk anggota yang sama pada pertemuan yang sama.
3.  Laporan kehadiran dapat diunduh (PDF/Excel) dengan data yang akurat.
4.  Dashboard memberikan gambaran statistik kehadiran secara real-time.

---
*Next Action: Implementation of Phase 4 (Advanced Reporting & Member Cards).*
