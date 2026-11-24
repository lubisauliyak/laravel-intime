# AI Agent Guidelines - inTime Project

Panduan ini wajib dipatuhi oleh seluruh AI coding assistant (Antigravity, Cursor, Claude Code, Gemini CLI, dll.) saat membaca, menganalisis, atau memodifikasi basis kode pada proyek **inTime**.

---

## 1. Spesifikasi Stack & Versi Framework

* **PHP:** ^8.2 (Running: PHP 8.4)
* **Framework:** Laravel 12.x (`laravel/framework: ^12.0`)
* **Admin Panel:** **Filament v5.0** (`filament/filament: 5`)
* **Role & Otorisasi:** Native Laravel Policy & Hierarchical ABAC (Group-scoped, `UserRole` Enum)
* **Livewire:** v4.x
* **Tailwind CSS:** v4.x
* **Code Styler:** Laravel Pint (`laravel/pint: ^1.24`)
* **AI Tooling & MCP:** Laravel Boost (`laravel/boost: ^2.0`)

---

## 2. ATURAN KRUSIAL: Filament v5 Unified Schema (ANTI-HALUSINASI)

> [!CAUTION]
> **DILARANG KERAS MENGGUNAKAN SYNTAX FILAMENT v2 ATAU v3!**
> Proyek ini menggunakan **Filament 5**. Sebagian besar pelatihan model AI bawaan masih mengasumsikan Filament v2/v3. Kegagalan mematuhi aturan ini akan menyebabkan *syntax error* dan merusak panel admin.

### Aturan Arsitektur Filament 5 di Proyek Ini:
1. **Pemisahan Kelas Schema:**
   Form dan Infolist **TIDAK** ditulis secara *inline* di dalam Resource class. Seluruh definisi form dan infolist dipisah ke dalam folder:
   `app/Filament/Resources/{ResourceName}/Schemas/`
   * Contoh Form: `GroupForm.php`, `CardTemplateForm.php`, `MeetingForm.php`
   * Contoh Infolist: `MemberInfolist.php`, `GroupInfolist.php`, `CardTemplateInfolist.php`

2. **Gunakan Unified `Filament\Schemas\Schema`:**
   * Method konfigurasi schema menggunakan:
     ```php
     use Filament\Schemas\Schema;

     public static function configure(Schema $schema): Schema
     {
         return $schema->components([
             // komponen schema di sini
         ]);
     }
     ```
   * **JANGAN GUNAKAN:** `public static function form(Form $form): Form` atau `public static function infolist(Infolist $infolist): Infolist` gaya Filament 3.

3. **Komponen Layout:**
   * Gunakan `Filament\Schemas\Components\Section` (BUKAN `Card::make()` atau `Forms\Components\Card`).
   * Gunakan `Filament\Schemas\Components\Grid` untuk tata letak kolom.

4. **File Referensi Ground-Truth:**
   Sebelum membuat atau memodifikasi komponen Filament, **WAJIB** membaca file berikut sebagai acuan pola kode yang valid:
   * **Infolist:** `app/Filament/Resources/Members/Schemas/MemberInfolist.php`
   * **Form:** `app/Filament/Resources/Groups/Schemas/GroupForm.php`

---

## 3. Disiplin Vibe Coding & Pencegahan Konflik

Proyek ini berskala besar dengan interkoneksi antar-modul yang erat (Member, Group, Meeting, Attendance, ActivityLog). Untuk mencegah konflik dan regresi:

1. **Prinsip Perubahan Minimal & Terfokus:**
   * Jangan menulis ulang (*rewrite*) keseluruhan file jika hanya perlu mengubah sebagian kecil logika.
   * Pertahankan *existing docblock*, komentar bisnis, dan *method signatures*.

2. **Pemeriksaan Dampak Antar-Modul (Cross-Module Impact):**
   * Perubahan pada model `Member` atau `Group` berdampak langsung pada `MeetingAttendance`, Observers (`MemberObserver`), Export Sheets (`MeetingPengurusDetailSheet`), dan Policy.
   * Selalu periksa relasi dan observer sebelum menghapus atau mengubah nama kolom/method.

3. **Verifikasi Sintaks Sebelum Selesai:**
   * Selalu jalankan *linting* atau verifikasi sintaks PHP sebelum menyatakan tugas selesai:
     ```powershell
     vendor/bin/pint --test
     ```

4. **Disiplin Git:**
   * Selalu buat commit kecil per fitur/modul (*atomic commits*).
   * Gunakan branch fitur untuk perubahan eksperimental (`git checkout -b feat/nama-fitur`).
   * Jangan pernah menambahkan file `.zip`, `.env`, atau data rahasia ke Git.

---

## 4. Konteks AI & Token Management
* Jika sesi percakapan terasa panjang atau mulai melambat, gunakan perintah `/compact` atau isolasi tugas per modul menggunakan subagent.
