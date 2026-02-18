# 🧪 Manual Test Phase 5: QR, Reports & Permission Migration

Dokumen ini berisi panduan pengujian manual untuk seluruh fitur di Phase 5.

## 1. Pengujian Manajemen QR & Scanner (PASSED ✅)
- [x] Download Single QR (PNG).
- [x] Bulk Download QR (ZIP).
- [x] Scanner: Deteksi terlambat.
- [x] Scanner: Filter kriteria target (Gender/Usia).

## 2. Pengujian Ekspor Excel Pertemuan (PASSED ✅)
- [x] Excel Multi-sheet (Statistik & Detail Anggota).
- [x] Penanganan status 'TIDAK HADIR' otomatis.

## 3. Pengujian Rekap Kehadiran Global (PASSED ✅)
| Langkah | Ekspektasi Hasil | Status |
| :--- | :--- | :---: |
| Buka Menu Rekap Kehadiran | Muncul kolom Izin/Sakit, Tanpa Keterangan, dan % Kehadiran yang akurat. | [x] |
| Test Filter Tanggal & Grup | Perhitungan statistik berubah sesuai rentang waktu dan hierarki grup. | [x] |
| Klik Export Excel (Global) | Mendownload file Excel berisi ringkasan kehadiran seluruh anggota sesuai filter. | [x] |

## 4. Pengujian Migrasi Permission Spatie (PASSED ✅)
| Langkah | Ekspektasi Hasil | Status |
| :--- | :--- | :---: |
| Login sebagai Admin (Non-Super) | Tidak bisa melihat tombol atau menu yang permission-nya dimatikan di UI Shield. | [x] |
| Edit User (Role Sync) | Saat mengubah kolom Role di User, role di Spatie otomatis tersinkronisasi. | [x] |
| Akses Scanner (Group Barrier) | User hanya bisa men-scan meeting yang masuk dalam hierarki grupnya. | [x] |
| Dashboard Widgets | Statistik di dashboard hanya menampilkan grup yang diizinkan (berbasis permission/hierarki). | [x] |


---
## 5. Catatan Penting
- **Branding Laporan Excel** & **Cetak Kartu Anggota** tetap di **Phase 8**.
- Fokus Phase 5 sekarang adalah **Stabilitas Laporan Global** dan **Transisi Keamanan (Permission)**.

---
*Terakhir diupdate: 17 Feb 2026 (Refined hierarchical permissions).*
