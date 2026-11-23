# Rencana Kerja & Checklist: Perbaikan Logika, Keamanan, & UX
## Modul Presensi QR Scanner, Perizinan Mandiri, & Kehadiran Mandiri

Dokumen ini disusun dari perspektif **Product Manager (PM)** dan **UX Professional** untuk menjadi acuan standar, panduan teknis, serta daftar periksa (*checklist*) dalam memperbaiki dan memperkuat logika bisnis, sistem keamanan, integritas data, dan pengalaman pengguna (*user experience*) pada 3 modul presensi aplikasi **inTime**.

---

## 1. Analisis Kebutuhan & Hasil Temuan QA

### 1.1 Modul yang Dicakup
1. **Live Scanner Presensi (Operator / Gate):**
   * Controller: `App\Http\Controllers\LiveScannerController`
   * Blade View: `resources/views/scanner/live.blade.php`
2. **Perizinan Mandiri (Jemaat / Publik):**
   * Controller: `App\Http\Controllers\SelfPermissionController`
   * Blade View: `resources/views/permission/self.blade.php`
3. **Kehadiran Mandiri (Jemaat / Publik):**
   * Controller: `App\Http\Controllers\SelfPresenceController`
   * Blade View: `resources/views/presence/self.blade.php`

### 1.2 Ringkasan Permasalahan Utama
* **Security (High Risk):**
  * IDOR pada endpoint publik presensi & izin: siapa saja bisa mengabsenkan orang lain atau mengunci akun orang lain dengan status izin/sakit fiktif.
  * *Unrestricted overwrite* pada endpoint publik `complete-biodata`: siapa pun bisa mengubah tanggal lahir dan jenis kelamin anggota lain di database secara massal.
  * Celah DoS / Server Memory Exhaustion akibat unggahan string base64 selfie berukuran raksasa tanpa validasi ukuran & format mime.
  * Unggahan file lampiran surat bukti pada manual store scanner tanpa filter ekstensi dan ukuran.
* **Logic & Integrity (High / Medium Risk):**
  * *Race condition* saat scan cepat kamera (60 fps) atau double-click tombol submit memicu uncaught `SQLSTATE[23000] Duplicate entry` (HTTP 500 error).
  * Pendaftaran instan (`registerTemporary`) tidak menghitung `age` dan `age_group_id`, menyebabkan anggota baru otomatis ditolak jika pertemuan memiliki filter kategori usia.
  * Dropdown kelompok pada halaman mandiri hanya mengambil *leaf groups*, sehingga Pengurus dari grup induk tidak menemukan kelompoknya untuk mencari nama sendiri.
  * Bug pertemuan lintas tengah malam (*overnight meeting*): pertemuan jam 22:00–01:00 langsung dianggap kedaluwarsa seketika.
* **User Experience (UX):**
  * Anggota yang belum memiliki KTA / tidak tahu nomor ID harus dapat dengan mudah dan cepat menemukan namanya.
  * Anggota lama yang belum memiliki data tanggal lahir di database harus dipandu dengan alur yang ramah, cepat, dan intuitif untuk melengkapi tanggal lahir terlebih dahulu tanpa memutus proses perizinan/kehadiran.
  * Tombol submit tidak boleh aktif kembali saat proses sukses masih berjalan (mencegah double-tap yang memunculkan pesan peringatan membingungkan).

---

## 2. Alur Pengalaman Pengguna (Target User Journey)

### 2.1 Alur Perizinan Mandiri (Fokus UX Frictionless)
```
[Buka Link Pertemuan: /self-permission/{meeting}]
               │
               ├─ Opsi A: Scan QR KTA (Kamera)
               └─ Opsi B: Cari Nama (Tanpa ID / Belum Punya KTA)
                       │
                       ├─ Pilih Kelompok (Tersedia seluruh tingkatan yang relevan)
                       └─ Ketik / Pilih Nama Anggota
                               │
                               ▼
            [Sistem Memeriksa Kelengkapan Biodata]
                               │
             ┌─────────────────┴─────────────────┐
             ▼                                   ▼
   [Tanggal Lahir Kosong]             [Tanggal Lahir Sudah Ada]
             │                                   │
             ▼                                   │
   [Modal/Langkah Ramah:                         │
    "Halo [Nama]! Mohon lengkapi                 │
    tanggal lahir Anda untuk validasi"]          │
             │                                   │
             ▼                                   │
   [Simpan Tanggal Lahir]                        │
   (Kalkulasi Usia & Kategori Usia Otomatis)     │
             │                                   │
             └─────────────────┬─────────────────┘
                               │
                               ▼
        [Langkah Konfirmasi & Form Perizinan]
        • Pilih Status: IZIN / SAKIT
        • Teks Template Dinamis (Pembuka, Isi, Penutup)
        • Input Keterangan Alasan (Wajib)
        • Upload Foto Bukti (Opsional, max 2MB)
                               │
                               ▼
                     [Klik KIRIM PERIZINAN]
        • Tombol langsung terkunci (Debounce: "Menyimpan...")
        • Feedback Audio & Banner Berhasil
        • Kunci tombol permanen ("Tersimpan, mengalihkan...")
        • Reset otomatis setelah 3 detik
```

### 2.2 Alur Kehadiran Mandiri (Self-Presence)
```
[Buka Link Pertemuan: /self-presence/{meeting}]
               │
               ├─ Opsi A: Scan QR KTA (Kamera)
               └─ Opsi B: Cari Nama Anggota (Manual Input)
                               │
                               ▼
            [Cek Biodata: Tanggal Lahir & Panggilan]
            (Jika kosong -> Form lengkapi biodata singkat)
                               │
                               ▼
            [Ambil Selfie Bukti Kehadiran / Upload Foto]
            (Kompresi otomatis client-side < 800px)
                               │
                               ▼
                     [Klik KIRIM PRESENSI]
        • Debounce lock pada tombol submit
        • Feedback sukses & rincian status (Hadir / Terlambat)
```

---

## 3. Aturan Bisnis & Batasan Keamanan (Business Rules & Guardrails)

1. **Integritas Penanganan Duplikasi Presensi:**
   * Query penyimpanan presensi harus menggunakan transaksi DB dengan penanganan `try-catch` terhadap `UniqueConstraintViolationException` / `QueryException`.
   * Jika terjadi race condition (dua request datang bersamaan), request kedua harus menghasilkan respon rapi status `warning` (*"Data presensi sudah tersimpan sebelumnya"*), bukan HTTP 500 error.
2. **Proteksi Penimpaan Biodata (`completeBiodata`):**
   * Field `birth_date` atau `gender` hanya boleh diisi jika nilai di database saat ini masih kosong/`null`.
   * Jika data sudah terisi, nilai tidak boleh ditimpa secara sepihak via form publik (harus melalui admin Filament).
3. **Kalkulasi Usia Otomatis pada Pendaftaran Sementara:**
   * Setiap kali tanggal lahir diinputkan via `registerTemporary` maupun `completeBiodata`, sistem wajib menghitung `age` dan mencari relasi `age_group_id` yang cocok pada tabel `age_groups` sebelum menyimpan record.
4. **Validasi File Upload & Base64 Payload:**
   * String base64 selfie dibatasi maksimal panjang karakter setara file gambar 2.5MB.
   * File lampiran pada scanner manual wajib divalidasi `image,mimes:jpeg,png,jpg,pdf|max:2048`.
5. **Pertemuan Selesai di Hari yang Sama (Bukan Lintas Malam):**
   * Berdasarkan konfirmasi pengguna, seluruh acara pertemuan selesai pada hari yang sama (tidak lintas hari), sehingga logika penyesuaian lintas hari diabaikan agar logika tetap sederhana dan efisien.

---

## 4. Checklist Rencana Kerja (Work Breakdown Structure)

### Fase 1: Keamanan, Penanganan Gambar (HEIC & File Besar), & Sanitasi Input
- [x] **Kompresi Gambar Client-Side & Penanganan iPhone HEIC (Frontend):**
  - Buat helper kompresi berbasis HTML5 Canvas di frontend (`max: 1200px`, kualitas `0.7 JPEG`) pada `permission/self.blade.php`, `presence/self.blade.php`, dan `scanner/live.blade.php`.
  - Otomatis kompres foto kamera 5–15MB menjadi JPEG ringan (~150KB–250KB) sebelum dikirim ke server.
  - Tangani format HEIC iPhone agar otomatis dikonversi menjadi standar JPEG yang kompatibel di semua browser.
- [x] **Validasi Ketat Upload Selfie Base64:**
  - Tambahkan limit panjang string base64 pada `SelfPresenceController::store` untuk mencegah crash memori/DoS.
  - Tambahkan sanitasi format base64 dan validasi tipe gambar (`image/jpeg`, `image/png`, `image/webp`) sebelum ditulis ke storage.
- [x] **Proteksi Celah Overwrite Biodata Anggota:**
  - Perbarui `completeBiodata` pada `SelfPermissionController` dan `SelfPresenceController` agar hanya mengizinkan pengisian pada field yang saat ini bernilai `null` di database.
  - Tolak penimpaan jika data `birth_date` atau `gender` anggota tersebut sudah tercatat di sistem.
- [x] **Validasi File Lampiran Scanner Manual:**
  - Tambahkan validasi eksplisit pada [LiveScannerController.php](file:///d:/SandBox/Antigravity/inTime/app/Http/Controllers/LiveScannerController.php) `manualStore`: `evidence => nullable|file|mimes:jpeg,png,jpg,webp,pdf|max:2500`.
- [x] **Validasi Hirarki Grup pada Pendaftaran Instan:**
  - Pada fungsi `registerTemporary` di seluruh controller, pastikan `group_id` yang dikirim benar-benar berada dalam cakupan hirarki pertemuan (`in_array($group_id, $meeting->group->getAllDescendantIds())`).
  - Tambahkan pengecekan `isExpired()` dan status presensi aktif sebelum mengizinkan pendaftaran instan.


---

### Fase 2: Integritas Database & Logika Concurrency
- [x] **Penanganan Race Condition & Cegah Crash 500:**
  - Bungkus logika `Attendance::create()` pada `LiveScannerController`, `SelfPermissionController`, dan `SelfPresenceController` dalam blok `try ... catch (\Illuminate\Database\QueryException $e)`.
  - Jika terjadi `code 23000` (duplikasi unique key), kembalikan response JSON status `warning` yang ramah pengguna: *"Presensi anggota ini sudah tercatat sebelumnya."*
- [x] **Kalkulasi Usia & Kategori Usia Otomatis:**
  - Perbarui `MemberObserver` pada event `saving` agar otomatis menghitung ulang `age` dan mencari relasi `age_group_id` yang sesuai dari `birth_date` saat anggota baru dibuat (`registerTemporary`) ataupun saat melengkapi data (`completeBiodata` / dirty `birth_date`).
  - Menjamin relasi `ageGroup` selalu terisi dan akurat.
- [x] ~~**Perbaikan Bug Pertemuan Lintas Hari (Overnight Meetings):**~~ *(Diabaikan / Out of Scope sesuai review pengguna: acara tidak lintas hari)*
- [x] **Penghindaran Mutasi Obyek Carbon:**
  - Seluruh kalkulasi Carbon di controller, model, Filament page, dan export sheet telah diperbarui menggunakan `copy()->setTimeFrom(...)` dan `isExpired()` untuk mencegah mutasi instans tanggal pertemuan utama.

---

### Fase 3: Pengalaman Pengguna Form Perizinan Mandiri (UX Refinement)
- [x] **Tampilan Opsi "Cari Nama" yang Jelas:**
  - Pada [permission/self.blade.php](file:///d:/SandBox/Antigravity/inTime/resources/views/permission/self.blade.php) dan [presence/self.blade.php](file:///d:/SandBox/Antigravity/inTime/resources/views/presence/self.blade.php), tab kedua diperbarui menjadi **"🔍 Cari Nama / Manual"** dengan ikon pencarian dan micro-copy panduan yang jelas bagi anggota yang tidak memiliki KTA fisik.
- [x] **Alur Lengkapi Tanggal Lahir yang Halus (Frictionless):**
  - Pada saat pencarian manual atau scan QR menghasilkan respon `incomplete_biodata`:
    - Menampilkan antarmuka pengisian tanggal lahir yang ringkas dan ramah dengan sapaan personal: *"Halo [Nama Anggota]! 👋 Tanggal lahir Anda belum tercatat. Mohon lengkapi tanggal lahir untuk memverifikasi data dan melanjutkan perizinan."*
    - Jika jenis kelamin sudah terisi di database, input jenis kelamin otomatis disembunyikan dan input tanggal lahir melebar penuh (*full-width*) dengan auto-focus.
    - Setelah berhasil simpan, langsung otomatis mengarahkan ke Langkah 2 (Form Izin/Sakit) tanpa perlu mencari nama ulang.
- [x] **Dukungan Pencarian untuk Seluruh Tingkatan Kelompok Terkait:**
  - Perbarui query `$groups` pada `SelfPermissionController::index()` dan `SelfPresenceController::index()` agar menyertakan kelompok penyelenggara dan kelompok yang memiliki anggota langsung, lengkap dengan relasi `level` (`$grp->full_name`), sehingga Pengurus dapat memilih kelompoknya saat mencari nama manual.

---

### Fase 4: Optimasi Performa & Polish Interaksi
- [x] **Pemberian Throttling pada Live Scanner Search:**
  - Tambahkan middleware `throttle:60,1` pada route `scanner.search` di [web.php](file:///d:/SandBox/Antigravity/inTime/routes/web.php).
- [x] **Optimasi Query Pencarian Live Scanner:**
  - Menghilangkan N+1 query dan rekursi hirarki kelompok berulang pada `LiveScannerController::search` dengan memanfaatkan array `$supervisorGroupIds` dan `$supervisorMemberIds` in-memory saat pemetaan hasil.
- [x] **Debounce & Kunci Tombol Submit Permanen Pasca-Sukses:**
  - Pada `presence/self.blade.php` dan `permission/self.blade.php`, saat respon `success` diterima, tombol submit dikunci permanen dengan teks *"Tersimpan, mengalihkan..."* selama jeda 3 detik menuju reset form untuk mencegah double click/submit.
- [x] **Manajemen Stream Kamera:**
  - Fungsi `stopCamera()` dan `stopScanning()` di seluruh halaman publik dan live scanner mematikan seluruh track video (`stream.getTracks().forEach(track => track.stop())`), mengosongkan `srcObject`, dan mendaftarkan listener `pagehide`.

---

### Fase 5: Pengujian & Verifikasi (QA Test Matrix)
- [x] **Uji Kasus 1: Perizinan Anggota Tanpa Tanggal Lahir:**
  - Pilih anggota yang belum memiliki `birth_date`.
  - Pastikan sistem meminta tanggal lahir secara ramah.
  - Isi tanggal lahir -> pastikan tersimpan, usia terhitung, dan form perizinan langsung terbuka.
- [x] **Uji Kasus 2: Upaya Penimpaan Tanggal Lahir Ilegal:**
  - Coba kirim request POST manual ke `complete-biodata` untuk anggota yang sudah punya tanggal lahir.
  - Pastikan request ditolak/diabaikan dan data asli di DB tidak berubah.
- [x] **Uji Kasus 3: Simulasi Double Scan / Rapid Click (Race Condition):**
  - Kirim 2 request store presensi secara paralel dalam milidetik yang sama.
  - Pastikan request pertama berstatus `success`, request kedua berstatus `warning` (tidak ada error 500).
- [x] **Uji Kasus 4: Pendaftaran Instan pada Pertemuan Berfilter Usia:**
  - Buka pendaftaran instan pada pertemuan khusus kategori usia tertentu (misal Pemuda).
  - Daftarkan anggota baru dengan tanggal lahir kategori pemuda.
  - Pastikan presensi langsung berhasil tanpa peringatan "Kategori usia () tidak sesuai".
- [x] ~~**Uji Kasus 5: Pertemuan Lintas Hari (Overnight):**~~ *(Diabaikan / Out of Scope sesuai review pengguna: acara tidak lintas hari)*

---

## 5. Prioritas Eksekusi

| Prioritas | Komponen | Alasan & Dampak |
| :--- | :--- | :--- |
| **P0 (Urgent)** | Penanganan Race Condition (Cegah 500) & Proteksi Overwrite Biodata | Menjaga stabilitas server dan mencegah data pribadi anggota dirusak. |
| **P0 (Urgent)** | Alur Lengkapi Tanggal Lahir di Form Izin Mandiri | Kebutuhan utama pengguna agar jemaat tanpa tanggal lahir dapat izin dengan lancar. |
| **P1 (High)** | Kalkulasi Kategori Usia pada Pendaftaran Instan | Memastikan fitur pendaftaran darurat berfungsi normal di semua jenis acara. |
| **P1 (High)** | Validasi Payload Selfie Base64 & Lampiran File | Mencegah memory crash dan potensi upload berbahaya ke storage publik. |
| **P2 (Medium)** | Penanganan Overnight Meeting & Perbaikan Dropdown Kelompok | Mengakomodasi skenario khusus (acara malam dan presensi pengurus). |
| **P2 (Medium)** | Debounce Tombol & Manajemen Stream Kamera | Meningkatkan kenyamanan UX dan menghemat baterai perangkat jemaat. |

---

*Dokumen ini siap ditinjau dan dieksekusi bertahap sesuai persetujuan Tim Pengembang & Pengguna.*
