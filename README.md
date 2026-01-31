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
-   **Admin Panel**: Filament PHP v3
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
-   [x] **Hierarchical Data Scoping**: Admin hanya dapat mengelola data sesuai tingkat hirarki grup mereka (Ancestors read-only, Descendants full-access).
-   [x] Sinkronisasi Role Otomatis antara database dan Spatie Permissions.

### ⏳ Phase 3: Attendance & Reporting (Next)
-   [ ] Sistem Presensi Berbasis Scan QR.
-   [ ] Dashboard Statistik Kehadiran.
-   [ ] Ekspor Laporan Bulanan/Mingguan (PDF/Excel).
-   [ ] Cetak Kartu Anggota Digital.

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
3.  Konfigurasi `.env` dan jalankan migrasi:
    ```bash
    php artisan migrate --seed
    ```
4.  Jalankan server:
    ```bash
    php artisan serve
    ```

---
Dibuat dengan ❤️ untuk efisiensi organisasi.
