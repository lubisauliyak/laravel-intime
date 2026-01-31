# 🏗️ Detailed Design Phase 2: QR & Authorization [COMPLETED]

Dokumen ini merinci langkah-langkah teknis untuk Phase 2. Fokus utama adalah integrasi QR Code sebagai identitas digital dan sistem otorisasi (Role & Permission).

## 1. Setup QR Code Integration
*   [x] **Install Simple QR Code Library:**
    *   Command: `composer require simplesoftwareio/simple-qrcode`

## 2. Fitur Auto-Generate QR Code
*   [x] **Logic Generation:**
    *   Buat Observer atau logic di Model Member untuk membuat QR Code secara otomatis saat `member_code` dibuat/diubah.
    *   Simpan gambar ke storage (`public/qrcodes/`).
*   [x] **Display QR in Filament:**
    *   Tampilkan preview QR Code di Tabel Anggota (Thumbnail).
    *   Tampilkan QR Code besar di halaman Detail Anggota agar bisa di-scan langsung dari layar HP/Tablet.
    *   Sediakan tombol "Download QR" bagi Admin.

## 3. Sistem Otorisasi & Scoping (Selesai)
*   [x] **Install & Setup Filament Shield:**
    *   Command: `composer require bezhansalleh/filament-shield`
    *   Command: `php artisan shield:install`
*   [x] **Konfigurasi Role & Scoping:**
    *   **SUPER ADMIN**: Akses penuh tanpa batasan.
    *   **ADMIN**: Mengelola data anggota dan grup di bawah hirarkinya. Dapat melihat grup induk sebagai referensi (read-only).
*   [x] **Implementasi Hierarchical Scoping**:
    *   Scoping otomatis pada level Query (Resource classes).
    *   Scoping aksi baris (Ubah/Hapus) di Tabel menggunakan logic `canBeManagedBy`.
*   [x] **Sinkronisasi Otomatis**: Sync role Spatie saat update kolom role di tabel Users.

## 4. Definition of Done (DoD) - Phase 2 [COMPLETED]
1.  Setiap Anggota memiliki QR Code unik yang tergenerate otomatis.
2.  Admin hanya bisa mengelola data di bawah hirarkinya sendiri.
3.  Visibilitas Induk: Admin bisa melihat grup induk (Pusat/Wilayah di atasnya) namun tombol aksi disembunyikan.
4.  Standardisasi Aksi: Aksi Massal (Restore/Force Delete) tersedia di toolbar saat filter Tempat Sampah aktif.

---
*Next Action: Phase 3 - Attendance & Reporting.*
