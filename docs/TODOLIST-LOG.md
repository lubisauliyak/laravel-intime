# Dokumen Rencana & Arsitektur: Fitur Audit Trail & Activity Log (Custom Native)

Dokumen ini memandu implementasi fitur **Activity Log (Audit Trail)** pada sistem **inTime** dengan pendekatan **Custom Native (Zero-Bloat)**. Desain ini dirancang khusus untuk keandalan *shared hosting* (efisiensi CPU, memori, dan disk I/O), integritas *multi-tenancy* berbasis hierarki wilayah (`getManageableGroupIds()`), kepatuhan *Filament Shield*, narasi log humanis dalam Bahasa Indonesia, serta kompatibilitas penuh dengan arsitektur **Filament v5** dan **Laravel 12** tanpa dependensi package eksternal yang rentan usang.

---

## 1. Prinsip Arsitektur & Filosofi Desain

1. **Custom Native & Zero-Bloat (Shared Hosting Friendly):**
   * **Bebas Dependensi Pihak Ketiga:** Tidak bergantung pada `spatie/laravel-activitylog` atau plugin Filament v3 komunitas yang tidak kompatibel dengan Filament v5. Menjaga folder `vendor/` tetap bersih dan bebas dari *dependency conflict* (seperti constraint PHP 8.2 vs 8.4).
   * **First-Class Multi-Tenancy Scoping (`group_id`):** Tabel `activity_logs` memiliki kolom `group_id` berindeks komposit `(group_id, created_at)`. Admin lokal menyaring data wilayahnya secara instan (< 2 ms) tanpa *polymorphic table scan* yang berat.
   * **Pencegahan *Write-Storm* & *Log Flooding*:**
     * Absensi massal via QR Scanner atau presensi mandiri **tidak mencatat event `create`** ke tabel log, karena tabel `attendances` sendiri sudah berfungsi sebagai *ledger* kehadiran.
     * Audit presensi difokuskan secara presisi pada **manipulasi data administratif** (`update` status/keterangan, dan `delete` pembatalan kehadiran oleh admin).
   * **Pembersihan Otomatis Mandiri (*Automated Retention*):** Log dibersihkan secara terjadwal setiap malam via Laravel Scheduler (`dailyAt('02:00')`), dilengkapi tombol pembersihan manual di antarmuka khusus untuk Super Admin.
   * **Aggregated Caching:** Metrik pada *Stat Cards* dan tren *sparkline* dikalkulasi dalam **satu kueri agregasi tunggal** bersyarat dan di-cache (TTL 120–300 detik).

2. **Penyajian Berlapis Ganda (*Dual-Layer Architecture*):**
   * **Lapisan Utama (Human-First Bahasa Indonesia):** Tampilan visual utama ditujukan untuk Admin dan Pengurus non-teknis. Setiap log dinarasikan dalam kalimat aktif yang alami (misal: *"Mendaftarkan anggota baru: Budi Santoso"*). Nama field database diterjemahkan ke istilah operasional lapangan (misal: `is_permanent` $\rightarrow$ *"Status Anggota Tetap"*).
   * **Lapisan Pengembang (Developer Inspector):** Data teknis mendalam (Namespace Model, ID Record, Alamat IP, User Agent browser, dan payload mentah JSON *Old vs New*) tersimpan rapi dan disajikan dalam bagian terlipat (*Collapsible Section*) agar tidak membingungkan pengguna umum.

3. **UX Berdaya Prediksi & Filament Native (Filament v5):**
   * **Sparkline Terintegrasi di Stat Card:** Tren grafik garis (*line chart*) diletakkan langsung di dalam kartu metrik menggunakan `Stat::make()->chart([...])` bawaan Filament tanpa library chart JavaScript eksternal.
   * **Modal Diff Ramah Pengguna:** Modal detail menampilkan tabel perbandingan visual 3-kolom: *Data yang Berubah* | *Sebelumnya* | *Menjadi*, dengan penanda kontras warna yang lembut.
   * **Tab Riwayat Per-Resource:** Integrasi sub-navigasi pada halaman detail entitas (`ViewMember` dan `ViewMeeting`) untuk menelusuri riwayat perubahan data spesifik secara langsung.
   * **Audit Trail Bersifat Imutabel (*Read-Only*):** Log aktivitas tidak dapat disunting atau dimanipulasi oleh siapa pun di antarmuka sistem.

4. **Integritas Hak Akses & Scoping Wilayah (Filament Shield Ready):**
   * **Super Admin:** Akses penuh ke seluruh jejak aktivitas di semua level hierarki kelompok.
   * **Admin Lokal:** Terbatas secara ketat (*scoping*) hanya pada aktivitas di yurisdiksi kelompok turunannya (`getManageableGroupIds()`).
   * **Operator & Pengurus:** Menu disembunyikan sepenuhnya (*Zero access*).

---

## 2. Cakupan Model, Event, & Format Bahasa Indonesia

### 2.1 Pemetaan Model & Strategi Pencatatan

Pencatatan dilakukan secara terukur pada 5 model inti dengan proteksi data sensitif:

| Model Target | Event yang Dicatat | Rasional Strategis & Sanitasi Data |
| :--- | :---: | :--- |
| **User** | Create, Update, Delete | Perubahan akun pengelola, role, dan status login. Atribut `password` dan `remember_token` diabaikan (*excluded*). |
| **Group** | Create, Update, Delete | Pondasi struktur organisasi & wilayah. Mengawasi perubahan hierarki induk (*parent_id*) dan nama kelompok. |
| **Member** | Create, Update, Delete | Data induk anggota, verifikasi KTA, dan mutasi kelompok. Atribut `qr_code_path` dan biner foto diabaikan. |
| **Meeting** | Create, Update, Delete | Jadwal pertemuan, kuota, toleransi checkin, dan perizinan. |
| **Attendance** | **Update, Delete** *(Create Manual Khusus)* | **Event create massal (QR scan / self-presence) dikecualikan** untuk mencegah lonjakan I/O hosting. Hanya mencatat intervensi administratif (perubahan status Hadir/Izin/Alpa atau pembatalan data). |

### 2.2 Format Narasi Humanis (Bahasa Indonesia)

Formatter menghasilkan kalimat aktif alami berdasarkan subjek dan pelaku:
* **Create:** *"Mendaftarkan anggota baru: [Nama Lengkap]"*
* **Update:** *"Memperbarui data anggota: [Nama Lengkap]"*
* **Delete:** *"Menghapus data pertemuan: [Nama Pertemuan]"*
* **Attendance Update:** *"Mengubah status presensi [Nama Anggota] dari [Status Lama] menjadi [Status Baru]"*
* **Attendance Delete:** *"Membatalkan / Menghapus catatan kehadiran [Nama Anggota]"*

### 2.3 Kamus Istilah Lapangan (Field Dictionary)

Saat menampilkan perubahan nilai (*diff*), nama field database diterjemahkan ke istilah ramah pengguna:

| Field Database | Label Ramah Pengguna | Format Nilai (Humanized Value) |
| :--- | :--- | :--- |
| `full_name` / `name` | Nama Lengkap / Nama | Teks biasa |
| `is_permanent` | Status Keanggotaan | `1` $\rightarrow$ "Anggota Tetap", `0` $\rightarrow$ "Sementara / Menunggu Verifikasi" |
| `status` (Member) | Keaktifan Anggota | `1` $\rightarrow$ "Aktif", `0` $\rightarrow$ "Kurang Aktif" |
| `status` (Attendance) | Status Presensi | Label Enum (Hadir, Izin, Sakit, Alpa) |
| `membership_type` | Peran Organisasi | Label Enum / "Pengurus", "Anggota Biasa" |
| `method` | Metode Presensi | "Scan QR", "Input Manual", "Mandiri" |
| `gender` | Jenis Kelamin | "Laki-laki", "Perempuan" |
| `birth_date` | Tanggal Lahir | Format tanggal lokal (misal: 17 Agu 1995) |
| `meeting_date` | Tanggal Pertemuan | Format tanggal lokal (misal: 25 Sep 2026) |
| `group_id` / `parent_id` | Kelompok / Wilayah | Di-*resolve* ke Nama Kelompok |
| `level_id` | Tingkat Hierarki | Di-*resolve* ke Nama Tingkat (Desa, Kelompok, dll.) |
| `role` | Peran Akun | Label Role (Super Admin, Admin, Operator, Pengurus) |

---

## 3. Skema Basis Data & Desain Model Custom Native

### 3.1 Skema Tabel `activity_logs`
```sql
CREATE TABLE activity_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_id BIGINT UNSIGNED NULL,
    causer_type VARCHAR(255) NULL,
    causer_id BIGINT UNSIGNED NULL,
    causer_name VARCHAR(255) NULL,
    subject_type VARCHAR(255) NOT NULL,
    subject_id BIGINT UNSIGNED NOT NULL,
    subject_name VARCHAR(255) NULL,
    event VARCHAR(50) NOT NULL, -- created, updated, deleted, restored
    description VARCHAR(255) NOT NULL,
    properties JSON NULL, -- {old: {...}, new: {...}, ip: "...", user_agent: "..."}
    created_at TIMESTAMP NULL,
    
    INDEX idx_group_created (group_id, created_at),
    INDEX idx_subject (subject_type, subject_id),
    INDEX idx_causer (causer_type, causer_id),
    INDEX idx_created_at (created_at)
);
```

### 3.2 Model `App\Models\ActivityLog`
* Relasi: `causer()` (*morphTo*), `subject()` (*morphTo*), `group()` (*belongsTo*).
* Casts: `properties` => `array`, `created_at` => `datetime`.
* Scopes: `forManageableGroups($user)`, `byEvent($event)`, `bySubjectType($type)`.

### 3.3 Trait `App\Traits\LogsActivity`
Trait ringkas (~80 baris kode murni) yang disematkan pada model target:
* Mendengarkan event Eloquent `created`, `updated`, `deleted`.
* Pada `updated`, mendeteksi perubahan atribut via `$model->getDirty()` dan `$model->getOriginal()`.
* Mengabaikan atribut terproteksi (`password`, `remember_token`, file biner).
* Mengisi `group_id` otomatis dari entitas bersangkutan atau causer.
* Mengisi `ip_address` dan `user_agent` dari `request()`.

---

## 4. Matriks Otorisasi & Kebijakan Filament Shield

| Kemampuan / Permission | Super Admin | Admin Lokal | Operator / Pengurus | UX Behavior di Antarmuka |
| :--- | :---: | :---: | :---: | :--- |
| **ViewAny:ActivityLog** | ✅ Ya | ✅ Ya (Scoped) | ❌ Tidak | Menu "Log Aktivitas" hanya tampil untuk Super Admin & Admin. |
| **View:ActivityLog** | ✅ Ya | ✅ Ya (Scoped) | ❌ Tidak | Membuka modal detail riwayat aktivitas & perbandingan diff. |
| **Prune:ActivityLog** | ✅ Ya | ❌ Tidak | ❌ Tidak | Tombol "Bersihkan Log Lama" hanya muncul untuk Super Admin. |
| **Export:ActivityLog** | ✅ Ya | ✅ Ya (Scoped) | ❌ Tidak | Opsional: Unduh rekaman log ke spreadsheet/CSV. |

---

## 5. Spesifikasi UI/UX (Filament v5 Native)

### 5.1 Halaman Khusus: ActivityLogResource
* **Navigasi:** Grup `Akses Pengguna` (Sort: 3, di bawah Roles/Shield).
* **Stat Cards (Header Widget dengan Sparkline Terintegrasi):**
  1. **Total Aktivitas (30 Hari):** Angka total + mini line chart tren harian (*Emerald / Primary*).
  2. **Pembaruan Data (Update):** Frekuensi edit data + sparkline (*Info / Sky*).
  3. **Aksi Kritis (Penghapusan):** Jumlah data dihapus + sparkline (*Danger / Rose* — peringatan dini anomali).
  4. **Kontributor Teraktif:** Nama akun + jumlah aksi (indikator produktivitas tim).
* **Tabel Log Utama:**
  * Kolom: Waktu (*relatif ramah & format presisi*), Pelaku (*Nama + role badge*), Tipe Aksi (*Badge: Dibuat, Diperbarui, Dihapus*), Entitas (*Modul & Nama Objek*), Narasi Deskripsi Humanis.
  * Fitur Tabel: Filter rentang tanggal, filter tipe entitas, filter aksi, serta pencarian instan nama pelaku atau deskripsi.

### 5.2 Modal Detail Aktivitas (Dual-Layer Schema)
Saat baris log diklik / aksi *"Lihat Detail"* dipilih:
1. **Header & Ringkasan:** Pelaku aksi, waktu pelaksanaan, dan narasi kalimat aktif.
2. **Layer Utama — Tabel Perubahan (Human-First):**
   * Grid 3 kolom: **Data yang Berubah** | **Sebelumnya** | **Menjadi**.
   * Format teks manusiawi (menggunakan translasi dari `ActivityLogDictionary`).
3. **Layer Pengembang — Section Lipat "Detail Teknis (Developer)":**
   * Terlipat secara default (*collapsed*).
   * Menampilkan: Model Class, Record ID, Alamat IP, User Agent lengkap, dan payload raw JSON.

### 5.3 Tab Riwayat di Halaman Detail Resource
* **Detail Anggota (`ViewMember`):** Sub-navigasi *"Riwayat Aktivitas"* menampilkan riwayat perubahan profil anggota yang bersangkutan.
* **Detail Pertemuan (`ViewMeeting`):** Sub-navigasi *"Riwayat Aktivitas"* menampilkan riwayat perubahan jadwal dan rekam manipulasi presensi pertemuan tersebut.

### 5.4 Otomasi Retensi & Tombol Pembersihan Manual
* **Otomasi Terjadwal (Scheduler):**
  * Dijalankan di background setiap pukul 02:00 malam via `Schedule::call(...)` di `routes/console.php`.
  * Menghapus log yang lebih tua dari `ACTIVITY_LOG_RETENTION_DAYS` (default: 30 hari).
* **Tombol Pembersihan Manual (Super Admin Only):**
  * Header Action pada tabel utama Activity Log.
  * Modal konfirmasi menampilkan jumlah data siap dibersihkan dan notifikasi hasil pembersihan.

---

## 6. Checklist Implementasi (To-Do List)

### Fase 1: Fondasi Skema Basis Data & Model Custom Native
- [x] Buat file migrasi `create_activity_logs_table.php` dengan indeks `(group_id, created_at)`.
- [x] Jalankan migrasi: `php artisan migrate`.
- [x] Buat model `app/Models/ActivityLog.php` beserta casts dan relasinya.
- [x] Tambahkan konfigurasi retensi di `.env` dan `.env.example`:
  ```env
  ACTIVITY_LOG_RETENTION_DAYS=30
  ```

### Fase 2: Service Formatter & Kamus Istilah Lapangan
- [x] Buat helper / presenter `app/Support/ActivityLogDictionary.php`:
  - [x] Kamus nama field database $\rightarrow$ Label Bahasa Indonesia.
  - [x] Kamus format nilai khusus (boolean status, enum peran, metode presensi, resolusi group/level ID).
  - [x] Generator kalimat narasi aktif ramah pengguna.

### Fase 3: Trait Pelacak Aktivitas & Instrumentasi Model
- [x] Buat trait `app/Traits/LogsActivity.php`:
  - [x] Deteksi `getDirty()` & `getOriginal()`.
  - [x] Resolusi otomatis `group_id` dan *capture* IP/User-Agent.
  - [x] Sanitasi atribut sensitif/biner.
- [x] Sematkan trait pada model:
  - [x] `app/Models/User.php`
  - [x] `app/Models/Group.php`
  - [x] `app/Models/Member.php`
  - [x] `app/Models/Meeting.php`
  - [x] `app/Models/Attendance.php` *(hanya mendengarkan updated & deleted)*

### Fase 4: Otorisasi & Scoping Keamanan (Shield & Policy)
- [x] Buat `app/Policies/ActivityLogPolicy.php`:
  - [x] `viewAny`: Super Admin & Admin (scoped).
  - [x] `view`: Super Admin & Admin (dalam `getManageableGroupIds()`).
  - [x] `create`, `update`, `delete`: `false` (prinsip audit imutabel).
  - [x] `prune`: Khusus Super Admin.
- [x] Daftarkan permission ActivityLog pada Filament Shield / Policy.

### Fase 5: Pembuatan Resource & Antarmuka Filament v5 Native
- [x] Buat Resource `app/Filament/Resources/ActivityLogs/ActivityLogResource.php`:
  - [x] Atur navigation label, icon, dan grup `Akses Pengguna`.
  - [x] Terapkan scoping kueri `getEloquentQuery()` berbasis `getManageableGroupIds()`.
- [x] Buat Widget Stat Cards `ActivityLogStatsOverview.php` / `ActivityLogStatsWidget.php`:
  - [x] Hitung metrik Total, Update, Delete (Kritis), dan Top Kontributor.
  - [x] Single cached query (120 detik) + tren sparkline array.
- [x] Bangun Tabel & Modal Diff Infolist `ActivityLogResource`:
  - [x] Kolom badge event, waktu relatif Indonesia, nama pelaku, dan deskripsi naratif.
  - [x] Modal aksi detail dengan Infolist 3-kolom ramah pengguna + Section Developer lipat (*collapsed*).
  - [x] Header Action *"Bersihkan Log Lama"* (khusus Super Admin).

### Fase 6: Integrasi Tab Riwayat & Otomasi Retensi
- [x] Buat halaman riwayat anggota: `app/Filament/Resources/Members/Pages/MemberActivityLog.php`.
- [x] Daftarkan halaman ke `getRecordSubNavigation()` di `MemberResource.php`.
- [x] Buat halaman riwayat pertemuan: `app/Filament/Resources/Meetings/Pages/MeetingActivityLog.php`.
- [x] Daftarkan halaman ke `getRecordSubNavigation()` di `MeetingResource.php`.
- [x] Daftarkan background pruning harian di `routes/console.php`.

### Fase 7: Pengujian & Validasi Mutu
- [x] Uji fungsionalitas narasi kalimat aktif & modal diff (Bahasa Indonesia).
- [x] Uji scoping multi-wilayah: Admin Grup A tidak dapat melihat log Grup B.
- [x] Uji non-logging pada presensi massal QR Scanner (pastikan performa scan tetap instan).
- [x] Uji tombol pembersihan log Super Admin dan background scheduler.
