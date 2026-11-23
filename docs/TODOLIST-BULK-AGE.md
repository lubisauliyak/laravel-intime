# Dokumen Rencana & Checklist: Fitur Bulk Refresh Usia Anggota pada Detail Grup

Dokumen ini disusun dari sudut pandang **UX Professional**, **Backend Engineer (BE)**, dan **Product Manager (PM)** untuk merancang serta mengimplementasikan fitur pembaruan usia massal (*bulk refresh age*) anggota pada halaman detail grup di aplikasi **inTime**.

---

## 1. Latar Belakang & Analisis Masalah

### 1.1 Kondisi Saat Ini (Current State)
1. Nilai kolom `age` (usia) pada tabel `members` saat ini hanya terisi saat data disimpan atau diubah melalui form (`MemberForm`) dan ditangani secara reaktif via Observer (`MemberObserver`).
2. Seiring berjalannya waktu, nilai `age` yang tersimpan di database menjadi tidak mutakhir (*frozen/stale*) karena tidak pernah dihitung ulang kecuali profil anggota tersebut dibuka dan disimpan kembali secara manual.
3. Menghitung usia secara dinamis melalui Eloquent Accessor (*computed property*) berpotensi memperlambat performa kueri basis data saat ekspor data massal ribuan anggota.
4. Di sisi lain, menjalankan *cron job / scheduled task* harian berpotensi memberikan beban tak perlu (*overhead*) pada server karena usia manusia umumnya hanya berubah sekali dalam setahun.

### 1.2 Keputusan Desain & Batasan Fitur
* **Mekanisme On-Demand:** Disediakan tombol aksi penyegaran usia massal (*bulk refresh*) langsung pada halaman detail grup (**ViewGroup**). Tombol ini memperbarui seluruh anggota dalam grup tersebut (termasuk sub-grup di bawahnya).
* **Fokus Hanya pada Usia (`age`):**
  * Sesuai kebutuhan organisasi dan pertimbangan impor data massal, aksi ini **hanya memperbarui kolom `age`** berdasarkan `birth_date`.
  * Kolom kategori usia (**`age_group_id`**) **tidak disentuh/diubah otomatis** agar klasifikasi kategori usia yang telah ditentukan secara manual atau berdasarkan kebutuhan kepengurusan tetap utuh.
* **Performa & Keamanan Data:**
  * Menggunakan `updateQuietly(['age' => $newAge])` untuk mencegah pemicuan event Observer yang tidak perlu (seperti regenerasi QR Code atau penimpaan kategori usia).
  * Memproses pembaruan data secara efisien menggunakan teknik *chunking* (`chunkById`) dan hanya memperbarui baris data yang usianya memang mengalami perubahan.

---

## 2. Rencana UX & Tampilan Antarmuka (UI/UX Specification)

### 2.1 Lokasi & Anatomi Tombol
* **Halaman:** `ViewGroup` ([ViewGroup.php](file:///d:/SandBox/Antigravity/inTime/app/Filament/Resources/Groups/Pages/ViewGroup.php)) - Header Actions.
* **Tipe Komponen:** Native Filament **Icon Button** (`->iconButton()`).
* **Icon:** `heroicon-m-arrow-path` (animasi rotasi halus saat interaksi).
* **Warna:** `gray` atau `primary` (selaras dengan tone warna header).
* **Tooltip Jelas & Informatif:** 
  > *"Hitung ulang & mutakhirkan usia seluruh anggota di grup ini (dan sub-grup) berdasarkan tanggal lahir"*
* **Badge / Label Aksesibilitas:** Memiliki accessibility label `aria-label="Refresh Usia Anggota"`.

### 2.2 Modal Konfirmasi (Safety Confirmation Modal)
Untuk mencegah klik tidak sengaja pada data skala besar:
* **Modal Heading:** *"Perbarui Usia Anggota Grup"*
* **Modal Icon:** `heroicon-o-arrow-path`
* **Modal Description:**
  > *"Apakah Anda yakin ingin menghitung ulang usia seluruh anggota di dalam grup ini beserta seluruh sub-grup di bawahnya? Usia akan dihitung ulang secara otomatis berdasarkan tanggal lahir masing-masing anggota. Kategori usia (age group) tidak akan diubah."*
* **Submit Action Label:** *"Ya, Perbarui Usia"*
* **Cancel Action Label:** *"Batal"*

### 2.3 Umpan Balik Transparan (User Notification)
* **Jika ada perubahan:**
  * Status: `success`
  * Judul: *"Pembaruan Usia Selesai"*
  * Pesan: *"Berhasil memutakhirkan usia untuk X anggota (dari total Y anggota yang memiliki data tanggal lahir)."*
* **Jika semua data sudah mutakhir:**
  * Status: `info`
  * Judul: *"Data Usia Sudah Mutakhir"*
  * Pesan: *"Seluruh anggota di grup ini sudah memiliki nilai usia yang sesuai dengan tanggal lahir."*
* **Jika tidak ada anggota bertanggal lahir:**
  * Status: `warning`
  * Judul: *"Tidak Ada Data Usia"*
  * Pesan: *"Tidak ditemukan anggota dengan data tanggal lahir yang valid di grup ini."*

---

## 3. Skema Otorisasi & Hak Akses (Policy & Shield Integration)

### 3.1 Permission Shield
* **Nama Permission:** `RefreshMemberAge:Group`
* **Target Resource:** `\App\Filament\Resources\Groups\GroupResource::class`
* **Label Bahasa Indonesia:** `Tombol - Refresh Usia Anggota`

### 3.2 Aturan Otorisasi pada `GroupPolicy`
Method `refreshMemberAge` pada [GroupPolicy.php](file:///d:/SandBox/Antigravity/inTime/app/Policies/GroupPolicy.php):
```php
public function refreshMemberAge(AuthUser $authUser, Group $group): bool
{
    return $authUser->can('RefreshMemberAge:Group') && $this->canManageGroup($authUser, $group);
}
```
* **Super Admin:** Memiliki hak akses penuh secara otomatis.
* **Admin Wilayah / Kelompok:** Hanya dapat menyegarkan usia pada grup miliknya atau grup anak-cucu yang berada di bawah yurisdiksinya (`canManageGroup`).

---

## 4. Checklist Pelaksanaan (TODOLIST)

### Fase 1: Konfigurasi Permission & Policy
- [x] Daftarkan permission `refreshMemberAge` pada konfigurasi Filament Shield di file [config/filament-shield.php](file:///d:/SandBox/Antigravity/inTime/config/filament-shield.php) pada bagian `GroupResource`.
- [x] Tambahkan method otorisasi `refreshMemberAge(AuthUser $authUser, Group $group): bool` di [GroupPolicy.php](file:///d:/SandBox/Antigravity/inTime/app/Policies/GroupPolicy.php) yang memvalidasi `RefreshMemberAge:Group` serta integritas hierarki grup (`canManageGroup`).

### Fase 2: Lokalisasi Bahasa (Filament Shield Translation)
- [x] Tambahkan label terjemahan Bahasa Indonesia untuk prefix permission `refresh_member_age` => `'Tombol - Refresh Usia Anggota'` pada file [lang/vendor/filament-shield/id/filament-shield.php](file:///d:/SandBox/Antigravity/inTime/lang/vendor/filament-shield/id/filament-shield.php).

### Fase 3: Implementasi Action Icon Button pada ViewGroup
- [x] Buka file [ViewGroup.php](file:///d:/SandBox/Antigravity/inTime/app/Filament/Resources/Groups/Pages/ViewGroup.php).
- [x] Tambahkan Header Action `refreshMemberAge`:
  - [x] Format sebagai Icon Button dengan icon `heroicon-m-arrow-path`.
  - [x] Tambahkan tooltip informatif yang jelas dan mudah dipahami pengguna.
  - [x] Pasang pengecekan visibilitas berdasarkan otorisasi `auth()->user()->can('refreshMemberAge', $record)`.
  - [x] Tambahkan dialog konfirmasi native (`requiresConfirmation()`, `modalHeading()`, `modalDescription()`, `modalSubmitActionLabel()`).

### Fase 4: Logika Perhitungan & Optimasi Performa Basis Data
- [x] Ambil seluruh ID grup hierarki terkait menggunakan `$record->getAllDescendantIds()`.
- [x] Ambil anggota yang memiliki `birth_date IS NOT NULL` menggunakan teknik chunking (`chunkById(250)`) agar aman untuk grup besar.
- [x] Hitung umur sebenarnya menggunakan `\Carbon\Carbon::parse($member->birth_date)->age`.
- [x] Bandingkan dengan nilai `member->age` saat ini:
  - [x] Hanya update jika usia berbeda (`$member->age !== $calculatedAge`).
  - [x] Gunakan `updateQuietly(['age' => $calculatedAge])` agar tidak menimpa `age_group_id` dan tidak memicu regenerasi QR Code / trigger Observer lainnya.
- [x] Hitung jumlah anggota yang diperbarui dan total anggota yang diproses.
- [x] Tampilkan Filament Notification interaktif sesuai hasil pemrosesan (berhasil diubah / sudah mutakhir / tanpa tanggal lahir).

### Fase 5: Pengujian & Validasi
- [ ] Jalankan pengecekan hak akses:
  - [ ] Pastikan tombol hanya muncul bagi user yang memiliki permission `RefreshMemberAge:Group` dan berhak mengelola grup tersebut.
  - [ ] Pastikan tombol tidak muncul atau ditolak bagi user di luar yurisdiksi grup.
- [ ] Uji fungsionalitas pembaruan:
  - [ ] Ubah sementara field `age` salah satu anggota menjadi tidak sesuai dengan `birth_date`.
  - [ ] Klik icon button refresh pada halaman detail grup.
  - [ ] Konfirmasi dialog modal.
  - [ ] Verifikasi bahwa nilai `age` anggota tersebut kembali akurat.
  - [ ] Verifikasi bahwa nilai `age_group_id` tetap utuh (tidak tertimpa).
  - [ ] Verifikasi bahwa file QR Code anggota tidak dibuat ulang secara sia-sia.
- [ ] Validasi tampilan UI: pastikan icon button rapi dan tooltip muncul saat kursor diarahkan ke tombol.
