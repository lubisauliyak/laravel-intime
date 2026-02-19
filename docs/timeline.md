# ⏳ Development Timeline: inTime

## 🚀 Foundation & Setup (22 - 25 Januari 2026)
*   **Inisialisasi Project**: Setup Laravel 12 dan Filament PHP v5.
*   **Arsitektur Database**: Perancangan skema tabel `groups`, `members`, dan `users`.

## 🏗️ Phase 1 & 2: Core & Security (26 - 31 Januari 2026)
*   **Hierarchy Engine**: Breadcrumbs dan navigasi tree-view.
*   **QR Code Integration**: Otomatisasi pembuatan QR Code (SVG) -> di-upgrade ke (PNG) di P5.
*   **RBAC**: filament-shield & hierarchical scoping.

## 📡 Phase 3: Attendance Operations (1 - 3 Februari 2026)
*   **Manajemen Pertemuan (Meetings)**: Filter target (Gender & Usia).
*   **Custom Live Scanner Station**: HUD kamera futuristik dan batch processing.

## 📊 Phase 4: Reporting & Mobile UX (4 - 10 Februari 2026)
*   **Advanced Attendance Details**: Drill-down Statistics & Widgets.
*   **Manual Management**: Fitur 'Set Status' susulan & 'Lampiran Bukti'.

## 🏁 Phase 5: QR Management & Advanced Export (11 - 16 Februari 2026)
*   ✅ **Download QR Assets**: Penomoran unduhan QR PNG (Single/Bulk ZIP).
*   ✅ **Scanner Enhancement**: Filter pencarian manual berdasarkan kriteria meeting.
*   ✅ **Unified Excel Report**: Laporan multi-sheet (Ringkasan Statistik + Detail Nama).

## 🚀 Phase 6: Monitoring & Optimization (18 Februari 2026 - Present)
*   ✅ **Dashboard Optimization**: Lazy loading widgets dan caching data statistik.
*   ✅ **Hierarchical Dashboard**: Perluasan scope data untuk user di level induk.
*   ✅ **Role Migration**: Transisi role ke basis string dan permission sistem.
*   **Bulk Import**: Fitur import massal via Excel (.xlsx) menggunakan Filament Importer.
*   **Auto-Mapping**: Logika pencarian ID Grup berdasarkan nama kelompok di Excel.

## 🚀 Phase 7: Analytics & System Refinement (20 Februari 2026 - Present)
*   ✅ **Scanner Vertical Lineage**: Pengurus cabang (child) bisa presensi di pertemuan induk (parent).
*   ✅ **Dynamic Scanner Widget**: Grafik beban scanner dengan sumbu X dinamis (pertama & terakhir scan).
*   ✅ **Auto-Verified Users**: Pengguna baru otomatis terverifikasi dan bisa langsung login.
*   ✅ **Dashboard Optimization**: Riwayat kehadiran 30 hari kini menyesuaikan rentang pertemuan aktual.
*   **Attendance Matrix Grid**: Visualisasi pola absensi berbasis tanggal.

---
*Terakhir diperbarui: 20 Februari 2026*
