# 🕒 inTime - Smart Membership & Attendance System

inTime adalah aplikasi berbasis web yang dirancang untuk manajemen pendataan anggota dan sistem absensi pertemuan dengan struktur organisasi bertingkat yang skalabel. Sistem ini dioptimalkan untuk menangani ribuan anggota dengan efisiensi tinggi melalui integrasi Scan QR Code.

## 🚀 Fitur Utama (Phase 1 Completed)

-   **Hierarki Organisasi Fleksibel**: Mendukung struktur organisasi tak terbatas menggunakan sistem Master Levels (Pusat, Wilayah, Cabang, Kelompok, dll).
-   **Manajemen Anggota Cerdas**:
    *   Kalkulasi usia otomatis berdasarkan tanggal lahir.
    *   Pengelompokan kategori usia otomatis (Anak, Remaja, Dewasa, Lansia).
    *   Identitas unik (Member Code) untuk setiap anggota.
-   **Manajemen Akun Sistem Terpisah**: Pemisahan antara *User* (Admin/Operator) dan *Member* (Subjek Absensi) untuk keamanan data yang lebih baik.
-   **Antarmuka Lokal (Bahasa Indonesia)**: Seluruh panel admin menggunakan terminologi yang ramah pengguna lokal.
-   **Visual Badge & Status**: Identifikasi cepat status aktif, peran, dan tingkatan melalui sistem badge berwarna.

## 🛠️ Teknologi

-   **Backend**: Laravel 12
-   **Admin Panel**: Filament PHP v5
-   **Database**: MySQL
-   **Localization**: Indonesian (Bahasa Indonesia)

## 📋 Status Pengembangan

### ✅ Phase 1: Core Architecture (Selesai)
-   [x] Setup Framework Laravel & Filament.
-   [x] Skema Database Skalalbel (Groups, Levels, Users, Members).
-   [x] Implementasi Model & Relationship.
-   [x] Refaktor Filament Resource (Schemas & Tables classes).
-   [x] Lokalisasi Bahasa Indonesia & UI Polishing.
-   [x] Logika Otomatisasi (Usia & Kategori).

### ✅ Phase 2: QR & Authorization (Selesai)
-   [x] Integrasi QR Code Generator (Simple QR Code).
-   [x] Auto-generate QR Code saat pendaftaran anggota.
-   [x] Implementasi Role & Permission (Filament Shield).
-   [x] **Hierarchical Data Scoping**: Admin hanya dapat mengelola data sesuai tingkat hirarki grup mereka.

### ✅ Phase 3: Attendance Engine (Selesai)
-   [x] Live Scanner Station (QR Code & Manual Search).
-   [x] Real-time Attendance Validation (Gender, Age, Active Status).
-   [x] Multi-level Attendance Statistics (Summary & Drill-down).
-   [x] Dashboard Widgets for Organization-wide metrics.

### ✅ Phase 4: Reporting & Mobile UX (Selesai)
-   [x] Drill-down Statistics per sub-grup.
-   [x] Manual Attendance Management (Set status susulan/Izin/Sakit).
-   [x] Sistem Lampiran Bukti Izin (Foto/Keterangan).
-   [x] Smart Status (BELUM HADIR -> TIDAK HADIR) otomatis.
-   [x] Optimasi Mobile UX (Responsive Scanner & Tables).

### ⏳ Phase 5: QR Management, Reporting & Member Cards (Current)
-   [ ] **QR Management**: Download PNG QR Code (Single/Bulk Zip).
-   [ ] **Deep Reporting**: Multi-sheet Excel report (Summary & Member Details).
-   [ ] **Scanner Enhancements**: Deteksi Terlambat & Filter Target Search.
-   [ ] **Cetak Kartu Anggota**: Bulk print selected members to PDF ready-to-print.

## ⚙️ Instalasi

1.  Clone repository:
    ```bash
    git clone https://github.com/username/inTime.git
    ```
2.  Install dependencies:
    ```bash
    composer install
    npm install && npm run dev
    ```
3.  Konfigurasi `.env`, jalankan migrasi & link storage:
    ```bash
    php artisan migrate --seed
    php artisan storage:link
    ```
4.  Jalankan server:
    ```bash
    php artisan serve
    ```

---
Dibuat dengan ❤️ untuk efisiensi organisasi.
