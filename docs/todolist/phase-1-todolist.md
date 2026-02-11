# 🏗️ Detailed Design Phase 1: Core Architecture [REFINED]

Dokumen ini merinci langkah-langkah teknis untuk Phase 1 sesuai dengan `implementation-plan.md`, termasuk perbaikan minor untuk meningkatkan user experience.

## 1. Setup Framework & Panel (Infrastructure)
*   [x] **Install Filament 3 (Latest Stable):**
    *   Command: `composer require filament/filament:"^3"`
    *   Command: `php artisan filament:install --panels`
*   [x] **Setup Database:**
    *   Pastikan `.env` terkonfigurasi ke DB MySQL/PostgreSQL.
    *   Buat database sesuai nama di `.env`.

## 2. Skema Database & Migrasi (Minor Improved)
*   [x] **Tabel `levels`** (Sistem Master Hirarki)
*   [x] **Tabel `groups`** (Data Organisasi Bertingkat)
*   [x] **Update Tabel `users`** (Akses Admin/Operator)
*   [x] **Tabel `members`** (Data Anggota)
    *   [x] **Minor Update:** Merubah kolom `status` dari `enum` menjadi `boolean` agar lebih simpel (Toggle Aktif/Non-aktif).
*   [x] **Tabel `age_groups`** (Master Data Kategori Usia)

## 3. Implementasi Model & Relationship (Eloquent)
*   [x] **Model `Level`, `Group`, `User`, `Member`, `AgeGroup`**
*   [x] **Minor Update:** Penambahan method `getParentAtLevel(int $levelNumber)` pada model `Group` untuk mendukung penampilan hirarki dinamis.
*   [x] **Minor Update:** Penambahan method `canBeManagedBy(User $user)` untuk validasi hak akses berbasis hirarki.

## 4. Filament Resource & UI Refactoring (Minor Improved)
*   [x] `UserResource`, `MemberResource`, `LevelResource`, `GroupResource`, `AgeGroupResource`.
*   [x] **Localization**: Full Indonesian Translation (Bahasa Indonesia).
*   [x] **Architecture**: Refactoring to Schemas/Tables classes for maintainability.
*   [x] **Minor Update (UI/UX):**
    *   Otomatisasi **Title Case** pada label kolom dinamis tingkat hirarki.
    *   Otomatisasi **Upper Case** pada field Kode dan Nama saat input data Master.
    *   Penggunaan **Toggle Component** untuk status aktif di seluruh form.
    *   Pemindahan aksi massal (Restore/Force Delete) ke menu terpadu.

## 5. Logic & Automation (Enhanced)
*   [x] Auto-Age calculation on Member creation/edit.
*   [x] Auto-Categorization based on Age Groups.
*   [x] **Dynamic Hierarchy Columns:** Menampilkan kolom tingkat (Pusat, Wilayah, Cabang, dll) secara dinamis di tabel anggota berdasarkan data Master Levels.
*   [x] **Advanced Sorting:** Menyortir data grup berdasarkan urutan angka hirarki level (Level tertinggi di atas).

## 6. Definition of Done (DoD) - Phase 1 [REFINED]
1.  [x] Admin bisa login ke panel Filament.
2.  [x] Struktur Kelompok (Group) bisa diinput secara bertingkat.
3.  [x] Tabel Anggota menampilkan hirarki grup secara dinamis dan rapi.
4.  [x] Input data master (Level/Group) otomatis terformat rapi (Uppercase).
5.  [x] Admin bisa membedakan status aktif/non-aktif melalui Toggle berwarna.

---
*Next Action: Move to Phase 2 (QR Code & Membership PDF).*
