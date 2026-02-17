# 📥 Detailed Design Phase 6: Fitur Import Data Anggota dari Excel

Dokumen ini merinci langkah-langkah implementasi fitur import data anggota secara massal dari file Excel (.xlsx / .csv) menggunakan **Filament Import Action**.

## 1. Persiapan Infrastruktur
*   [ ] **Requirement Check:** Pastikan `maatwebsite/excel` atau library pendukung Filament Import sudah siap.
*   [ ] **Penyimpanan Temporary:** Pastikan folder `storage/app/filament-imports` dapat ditulis oleh web server.
*   [ ] **Queue Worker:** Karena import berjalan di background, pastikan `php artisan queue:work` berjalan.

## 2. Inisialisasi Importer
*   [ ] **Create Importer Class:** `app/Filament/Imports/MemberImporter.php`
*   [ ] **Definisi Kolom (Mapping):** `member_code`, `full_name`, `nick_name`, `gender`, `birth_date`, `group_id`, `membership_type`, `status`.

## 3. Implementasi Logic Khusus
*   [ ] **Hierarchical Group Mapping:** Pencarian `group_id` berdasarkan nama yang diinput user.
*   [ ] **Trigger MemberObserver:** Pastikan import memicu auto-generation QR Code.
*   [ ] **Auto-Formatting:** Force `full_name` menjadi **UPPERCASE**.

## 4. Integrasi UI (Filament)
*   [ ] **Pemasangan Action:** Tambahkan `ImportAction` di `MembersTable.php`.
*   [ ] **Download Template:** Sediakan link untuk mengunduh template Excel.

## 5. Validasi & Error Handling
*   [ ] **Validasi Baris:** Cegah duplikasi `member_code`.
*   [ ] **Error Reporting:** Feedback baris mana yang gagal dan alasannya.

## 6. Definition of Done (DoD) - Phase 6
1.  Admin dapat mengunggah file .xlsx dengan data anggota secara massal.
2.  Data Kelompok ter-mapping secara otomatis ke `group_id` database.
3.  Setiap anggota yang di-import otomatis memiliki QR Code di storage.
4.  Proses berjalan di background dan memberikan notifikasi saat selesai.

---
*Status: Aktif (16 Feb 2026)*
