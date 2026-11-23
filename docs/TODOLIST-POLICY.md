# Dokumen Rencana & Checklist: Standardisasi Policy, Keamanan Hierarki Grup, & Peningkatan UX Otorisasi

Dokumen ini disusun oleh peran gabungan **Product Manager (PM)** dan **User Experience (UX) Professional** untuk memandu standardisasi arsitektur otorisasi (Laravel Policy & Filament Shield), penutupan celah keamanan hierarki grup (*parent-child scoping*), optimasi performa evaluasi hak akses, serta peningkatan kepastian interaksi pengguna pada sistem **inTime**.

---

## 1. Prinsip Utama & Filosofi Desain

1. **UX Humanis & Berdaya Prediksi (*Predictable UX*):**
   * **Bebas Error Teknis (Zero 500/TypeErrors):** Tidak boleh ada crash halaman akibat ketidakcocokan tipe parameter saat sistem mengevaluasi otorisasi.
   * **Informasi Alasan Penolakan (*Disabled with Context*):** Aksi yang tidak dapat dilakukan karena kondisi data (misal: kartu belum dibuat, anggota belum permanen) ditampilkan dalam kondisi **Disabled dengan Tooltip informatif**, bukan tiba-tiba lenyap tanpa alasan (*silently hidden*).
   * **Umpan Balik Aksi Massal yang Jujur (*Granular Bulk Feedback*):** Jika pengguna mengeksekusi aksi massal pada 10 data dan hanya 6 yang diizinkan oleh wewenangnya, sistem memberikan notifikasi yang transparan: *"6 data berhasil diproses. 4 data dilewati karena berada di luar wilayah wewenang Anda."*
   * **Proteksi Kesalahan Fatal (*Safety Net*):** Mencegah pengguna secara tidak sengaja menghapus atau mengunci akunnya sendiri (*self-lockout*) atau menghapus satu-satunya akun Super Admin.

2. **Performa Tinggi & Bebas Beban Ganda (*Anti N+1 Query*):**
   * Evaluasi otorisasi pada tabel dengan puluhan baris tidak boleh melakukan kueri basis data rekursif berulang-ulang untuk mencari anak cucu grup (*descendants*).
   * Menggunakan teknik *memoization* pada objek `User` dan eager loading relasi grup induk-anak.

3. **Integritas Ruang Lingkup Hierarki (*Strict Scoping*):**
   * Pengguna tingkat cabang/kelompok hanya memiliki wewenang penuh atas grup miliknya dan seluruh grup di bawahnya (*descendants*).
   * Policy harus memvalidasi kepemilikan objek secara ketat untuk mencegah manipulasi ID via URL langsung (*Direct Object Reference / IDOR*).

4. **100% Native Filament & Standard Laravel Gate:**
   * Menjaga kompatibilitas penuh dengan ekosistem Filament Shield tanpa merusak generator permission otomatis.

---

## 2. Matriks Aturan Bisnis & Logika Otorisasi

### 2.1 Hierarki Pengelolaan Wilayah (Parent-Child Jurisdiction)
* **Super Admin:** Memiliki akses bypass ke seluruh data di semua level hierarki grup.
* **Admin / Operator / Pengurus:**
  * Terikat pada satu `group_id` penugasan.
  * Hanya berhak mengelola data grup miliknya dan grup-grup di bawahnya (anak, cucu, cicit, dst).
  * Rumus validasi:
    $$\text{Boleh Dikelola} \iff \text{Record Group ID} \in \text{User's Descendant Group IDs}$$
* **Cakupan Entitas yang Terikat Hierarki:**
  * **Group:** Hanya bisa diubah/dihapus jika berada di bawah naungan user.
  * **Meeting:** Hanya bisa diubah/dikelola jika `meeting->group_id` berada di bawah naungan user.
  * **Member:** Hanya bisa diubah, dihapus, diverifikasi permanen, atau di-KTA-kan jika `member->group_id` berada di bawah naungan user.
  * **Attendance:** Hanya bisa diubah/dikelola jika `attendance->meeting->group_id` berada di bawah naungan user.

### 2.2 Aturan Khusus Model Pengguna (User Safety Guardrails)
* Pengguna yang sedang aktif **dilarang menghapus akunnya sendiri** (baik via tombol hapus individual maupun aksi massal).
* Pengguna dengan role non-Super Admin **dilarang mengubah role atau menghapus akun Super Admin**.
* Sistem harus mencegah penghapusan akun Super Admin terakhir agar akses panel manajemen tidak terkunci permanen.

### 2.3 Matriks Penampilan Komponen UI (Hidden vs Disabled with Tooltip)

| Skenario | Status Hak Akses | Status Data Record | Tampilan Tombol | UX Microcopy / Tooltip |
| :--- | :--- | :--- | :--- | :--- |
| **Akses Terbatas** | ❌ Tidak Memiliki Izin | Bebas | **Hidden** (Sembunyi) | *(Tidak ditampilkan untuk menjaga kerapian antarmuka)* |
| **Cetak KTA** | ✅ Memiliki Izin | ❌ Template KTA belum disetel | **Disabled** (Abu-abu) | *"Template KTA belum ditentukan pada grup ini atau induknya."* |
| **Download KTA** | ✅ Memiliki Izin | ❌ KTA belum digenerate | **Disabled** (Abu-abu) | *"KTA belum dibuat. Silakan klik aksi 'Buat KTA' terlebih dahulu."* |
| **Download QR** | ✅ Memiliki Izin | ❌ QR belum di-generate | **Disabled** (Abu-abu) | *"Kode QR belum tersedia untuk anggota ini."* |
| **Jadikan Permanen** | ✅ Memiliki Izin | ⚠️ Sudah permanen | **Hidden / Disabled** | *"Anggota ini sudah berstatus permanen."* |
| **Hapus Akun Sendiri** | ✅ Punya `Delete:User` | ⚠️ ID Akun == ID Sendiri | **Disabled** (Abu-abu) | *"Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif."* |

---

## 3. Checklist Pengerjaan Berdasarkan Fase

### Fase 1: Standardisasi Signature & Safe Defaults pada Policy Inti
Fase ini bertujuan memperbaiki ketidakcocokan parameter dan mencegah terjadinya `TypeError` fatal di aplikasi.

- [x] **1.1 Perbaikan [UserPolicy.php](file:///d:/SandBox/Antigravity/inTime/app/Policies/UserPolicy.php)**
  - [x] Perbarui method record-level (`view`, `update`, `delete`, `restore`, `forceDelete`, `replicate`) agar menerima parameter kedua: `(AuthUser $authUser, User $user)`.
  - [x] Tambahkan validasi proteksi diri pada method `delete` & `forceDelete`:
    * Kembalikan `false` jika `$authUser->id === $user->id`.
  - [x] Tambahkan validasi perlindungan Super Admin:
    * Pengguna non-Super Admin tidak boleh mengedit/menghapus pengguna yang memiliki role `super_admin`.
  - [x] Pastikan Super Admin terakhir tidak dapat dihapus (`User::role('super_admin')->count() > 1`).

- [x] **1.2 Standardisasi Fleksibilitas Method `import` & `export`**
  - [x] Ubah signature pada [MemberPolicy.php](file:///d:/SandBox/Antigravity/inTime/app/Policies/MemberPolicy.php):
    * `export(AuthUser $authUser, ?Member $member = null): bool`
    * `import(AuthUser $authUser, ?Member $member = null): bool`
  - [x] Ubah signature pada [GroupPolicy.php](file:///d:/SandBox/Antigravity/inTime/app/Policies/GroupPolicy.php):
    * `export(AuthUser $authUser, ?Group $group = null): bool`
    * `import(AuthUser $authUser, ?Group $group = null): bool`
  - [x] Ubah signature pada [MeetingPolicy.php](file:///d:/SandBox/Antigravity/inTime/app/Policies/MeetingPolicy.php):
    * `export(AuthUser $authUser, ?Meeting $meeting = null): bool`
  - [x] Sesuaikan pemanggilan otorisasi pada halaman [ImportMembers.php](file:///d:/SandBox/Antigravity/inTime/app/Filament/Resources/Members/Pages/ImportMembers.php) dan [ImportGroups.php](file:///d:/SandBox/Antigravity/inTime/app/Filament/Resources/Groups/Pages/ImportGroups.php) menggunakan `$user->can('import', Member::class)` yang standar dan bersih.

---

### Fase 2: Penguatan Logika Hierarki Grup pada `MemberPolicy`
Fase ini menutup celah keamanan URL tampering (*IDOR*) pada data anggota dan memastikan wewenang cabang/kelompok terisolasi dengan aman.

- [x] **2.1 Penambahan Helper Manajemen Anggota pada [MemberPolicy.php](file:///d:/SandBox/Antigravity/inTime/app/Policies/MemberPolicy.php)**
  - [x] Tambahkan helper method privat:
    ```php
    private function canManageMember(AuthUser $authUser, Member $member): bool
    {
        if (! $authUser instanceof \App\Models\User) return false;
        if ($authUser->isSuperAdmin()) return true;
        if (! $member->group) return false;
        return $member->group->canBeManagedBy($authUser);
    }
    ```
  - [x] Terapkan pengecekan `$this->canManageMember($authUser, $member)` pada method mutasi data:
    - [x] `update`
    - [x] `delete`
    - [x] `restore`
    - [x] `forceDelete`
    - [x] `replicate`
    - [x] `generateCard`
    - [x] `makePermanent`
    - [x] `deactivate`
    - [x] `downloadKta`
    - [x] `downloadQr`
    - [x] `viewMeetingHistory`

- [x] **2.2 Proteksi Pemilihan Grup pada Form Input/Edit Anggota**
  - [x] Periksa [MemberForm.php](file:///d:/SandBox/Antigravity/inTime/app/Filament/Resources/Members/Schemas/MemberForm.php):
    * Pastikan dropdown `group_id` hanya menampilkan daftar grup yang berada dalam wewenang pengguna login (menggunakan scope `getAllDescendantIds()`).

---

### Fase 3: Pembuatan Policy Baru untuk Model yang Belum Terdaftar
Fase ini melengkapi coverage otorisasi pada model yang belum memiliki policy tersendiri.

- [x] **3.1 Pembuatan `App\Policies\AttendancePolicy`**
  - [x] Buat file policy baru `app/Policies/AttendancePolicy.php`.
  - [x] Daftarkan method standar:
    * `viewAny(AuthUser $authUser): bool`
    * `view(AuthUser $authUser, Attendance $attendance): bool`
    * `create(AuthUser $authUser): bool`
    * `update(AuthUser $authUser, Attendance $attendance): bool`
    * `delete(AuthUser $authUser, Attendance $attendance): bool`
    * `setExcusedAttendance(AuthUser $authUser, Attendance $attendance): bool`
  - [x] Hubungkan validasi hierarki grup:
    * Pastikan perubahan absensi hanya diizinkan jika pertemuan terkait (`$attendance->meeting->group`) berada dalam wewenang `$authUser`.

- [x] **3.2 Pembuatan `App\Policies\MemberPositionPolicy`**
  - [x] Buat file policy baru `app/Policies/MemberPositionPolicy.php`.
  - [x] Terapkan validasi wewenang berbasis grup anggota yang memiliki jabatan tersebut.

- [x] **3.3 Pendaftaran Model & Policy di Laravel Providers**
  - [x] Pastikan Laravel 11 auto-discovery mengenali policy baru atau daftarkan secara eksplisit jika diperlukan.

---

### Fase 4: Penyelarasan Konfigurasi Filament Shield & Matrix Role
Fase ini menyinkronkan definisi izin dengan tampilan antarmuka manajemen hak akses (*Role & Permissions*).

- [x] **4.1 Pembaruan [config/filament-shield.php](file:///d:/SandBox/Antigravity/inTime/config/filament-shield.php)**
  - [x] Periksa dan daftarkan method kustom `MemberResource`:
    * Pastikan `import`, `export`, `generateCard`, `makePermanent`, `deactivate`, `downloadKta`, `downloadQr`, `viewMeetingHistory` terdaftar rapi.
  - [x] Daftarkan resource `Attendance` jika ingin dikelola sebagai tab perizinan mandiri, atau pertahankan permission kustom `SetExcusedAttendance` dengan label yang jelas.
  - [x] Jalankan perintah artisan non-destruktif untuk memperbarui matrix permission jika dibutuhkan:
    * `php artisan shield:generate --resource=MemberResource` (atau sesuaikan dengan kebutuhan).

- [x] **4.2 Proteksi Role Kritis pada [RolePolicy.php](file:///d:/SandBox/Antigravity/inTime/app/Policies/RolePolicy.php)**
  - [x] Tambahkan pengaman agar role `super_admin` tidak dapat diubah namanya (*update*) atau dihapus (*delete*) oleh siapapun demi stabilitas sistem.

---

### Fase 5: Peningkatan Pengalaman Pengguna (UX Refactoring pada Filament)
Fase ini mengimplementasikan micro-copy, tooltips, transisi status yang jelas, dan penanganan aksi massal.

- [x] **5.1 Penyelarasan Aksi Tunggal vs Aksi Massal pada [MembersTable.php](file:///d:/SandBox/Antigravity/inTime/app/Filament/Resources/Members/Tables/MembersTable.php)**
  - [x] Perbaiki pengecekan aksi massal (Bulk Actions):
    * Ganti pengecekan raw string yang tidak kontekstual agar konsisten dengan kemampuan otorisasi pengguna.
  - [x] Pada eksekusi aksi massal (`action(function ($records) { ... })`):
    * Filter data yang sah berdasarkan policy: `$records->filter(fn($rec) => auth()->user()->can('actionName', $rec))`.
    * Hitung jumlah yang berhasil diproses vs yang dilewati.
    * Tampilkan notifikasi Filament yang ramah dan transparan:
      - Jika semua sukses: Notifikasi sukses hijau standar.
      - Jika sebagian ditolak: Notifikasi warning oranye (*"X data berhasil diproses. Y data dilewati karena Anda tidak memiliki hak wewenang pada kelompok tersebut."*).

- [x] **5.2 Penerapan Status *"Disabled with Tooltip"* pada Tabel & Halaman Anggota**
  - [x] Pada aksi **Download KTA**:
    * Ganti `visible(...)` menjadi `disabled(fn($record) => empty($record->membership_card_photo_path))` disertai `tooltip('KTA belum dibuat untuk anggota ini. Silakan buat KTA terlebih dahulu.')`.
  - [x] Pada aksi **Download QR**:
    * Berikan status `disabled` dan `tooltip` jika file QR code belum terbentuk di disk storage.
  - [x] Pada aksi **Jadikan Anggota Permanen**:
    * Tampilkan icon centang hijau non-aktif jika anggota sudah berstatus permanen, disertai pesan bahwa status telah terverifikasi.

- [x] **5.3 Proteksi Tombol Hapus pada [UsersTable.php](file:///d:/SandBox/Antigravity/inTime/app/Filament/Resources/Users/Tables/UserTable.php)**
  - [x] Nonaktifkan tombol `DeleteAction` pada baris akun milik pengguna yang sedang login (`disabled(fn($record) => $record->id === auth()->id())`).
  - [x] Tambahkan tooltip native: *"Anda tidak dapat menghapus akun Anda sendiri."*

---

### Fase 6: Optimasi Performa & Pencegahan N+1 Query
Fase ini menjamin bahwa penambahan validasi hierarki pada policy tidak menurunkan kecepatan render halaman.

- [x] **6.1 Memoization Cache pada Objek User Aktif**
  - [x] Pastikan method `Group::canBeManagedBy(User $user)` tetap memanfaatkan properti memoized `$user->_descendant_group_ids` sehingga kueri pohon grup hanya dieksekusi **satu kali** per request lifecycle.
  - [x] Tambahkan memoization `static::$_ancestor_cache` dan null-safety handling jika pengguna tidak terikat ke grup manapun.
  - [x] Sediakan helper method `User::getManageableGroupIds()` dengan memoization internal `$this->_manageable_group_ids`.

- [x] **6.2 Eager Loading Relasi Grup pada Tabel Anggota & Pertemuan**
  - [x] Verifikasi `MemberResource::getEloquentQuery()`:
    * Pastikan relasi `group.level` bertingkat dan `ageGroup` sudah di-eager load serta dilengkapi null-safe scoping `whereRaw('1 = 0')`.
  - [x] Verifikasi `MeetingResource::getEloquentQuery()`:
    * Pastikan relasi `group.level` sudah di-eager load.
  - [x] Verifikasi `GroupResource::getEloquentQuery()`:
    * Pastikan relasi `level` dan `parent.level` sudah di-eager load dengan filter fallback yang aman.

---

### Fase 7: Pengujian, Verifikasi Hak Akses, & QA Skenario

- [x] **7.1 Skenario Pengujian Super Admin**
  - [x] Memastikan Super Admin tetap dapat melihat, mengubah, mengekspor, dan menghapus data di semua cabang dan tingkatan tanpa hambatan.

- [x] **7.2 Skenario Pengujian Operator Cabang / Kelompok (Scoping Test)**
  - [x] Login sebagai Operator Kelompok A:
    - [x] Pastikan hanya melihat data kelompok A dan anak-cucunya di tabel.
    - [x] Coba akses paksa via URL `/admin/members/{id_kelompok_B}/edit` $\rightarrow$ Sistem harus menolak dengan halaman 403 Forbidden atau redirect aman.
    - [x] Coba jalankan aksi massal pada kombinasi data $\rightarrow$ Verifikasi notifikasi granular menampilkan hitungan yang tepat.

- [x] **7.3 Skenario Pengujian Safety Net Akun Pengguna**
  - [x] Login sebagai Admin:
    - [x] Coba hapus akun sendiri $\rightarrow$ Tombol nonaktif / aksi ditolak dengan pesan yang jelas.
    - [x] Coba hapus akun Super Admin $\rightarrow$ Aksi ditolak oleh Policy.

- [x] **7.4 Skenario Pengujian Alur Bulk Import & Export**
  - [x] Buka halaman Import Anggota dan Import Kelompok $\rightarrow$ Pastikan halaman terbuka sempurna tanpa error type-hint.
  - [x] Jalankan export Excel & PDF pada pertemuan $\rightarrow$ Pastikan otorisasi berjalan mulus.

---

## 4. Rangkuman Dampak & Metrik Keberhasilan

| Metrik | Sebelum Implementasi | Target Setelah Implementasi |
| :--- | :--- | :--- |
| **Potensi Fatal Error (500)** | Rawan terjadi pada Gate import/export karena typehint kaku | **0 (Zero Fatal Error)** dengan safe nullable defaults |
| **Celah Direct URL Tampering (IDOR)** | Anggota antar-cabang bisa diedit jika ID URL diketahui | **100% Terlindungi** oleh verifikasi hierarki di `MemberPolicy` |
| **Kepastian Tombol Aksi (UX)** | Tombol sering hilang tanpa alasan jelas | **Jelas & Transparan** (Disabled dengan Tooltip informatif) |
| **Kejujuran Aksi Massal** | Notifikasi sukses palsu meski sebagian data terpotong izin | **Granular & Transparan** (Jumlah sukses & dilewati diinfokan) |
| **Beban Query Hierarki** | Potensi N+1 kueri pada tabel baris banyak | **Maksimal 1 kueri** pohon hierarki via *user memoization* |
