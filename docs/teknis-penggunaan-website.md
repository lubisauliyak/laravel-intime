# Dokumentasi Teknis Penggunaan Website inTime

Selamat datang di sistem **inTime**. Dokumen ini adalah panduan teknis yang menjelaskan langkah-langkah penggunaan seluruh fitur yang ada di dalam sistem, mulai dari pengelolaan data anggota, manajemen rapat, pencatatan kehadiran, hingga desain kartu anggota.

---

## 1. Pendahuluan

Sistem **inTime** dirancang untuk membantu organisasi dalam mengelola data keanggotaan dan kehadiran secara terpusat dan real-time. Sistem ini mencakup beberapa modul utama:

- **Manajemen Anggota** — Pengelolaan data anggota beserta kelompok dan kepengurusannya.
- **Manajemen Rapat** — Penjadwalan rapat, pencatatan kehadiran (absensi), dan pelaporan.
- **Kehadiran Mandiri** — Anggota dapat mengisi absensi atau mengajukan izin secara mandiri melalui tautan khusus.
- **Live Scanner** — Absensi real-time menggunakan pemindaian QR Code oleh panitia/operator.
- **Card Editor** — Alat desain kartu anggota berbasis kanvas secara langsung di browser.

**Panduan ini ditujukan bagi:**

| Peran | Keterangan |
|---|---|
| 🔴 **Administrator** | Akses penuh ke seluruh fitur dan pengaturan sistem |
| 🟡 **Staf / Operator** | Akses ke fitur operasional (rapat, absensi, scanner) |
| 🟢 **Anggota / Publik** | Hanya dapat mengakses halaman Kehadiran Mandiri & Izin Mandiri via tautan |

---

## 2. Kebutuhan Sistem & Persiapan

Agar sistem dapat berjalan dengan optimal, kami menyarankan:

- **Browser**: Google Chrome, Mozilla Firefox, atau Safari versi terbaru.
- **Perangkat**: Komputer PC atau Laptop dengan resolusi layar minimal 1366×768. *(Penggunaan Card Editor sangat disarankan menggunakan perangkat desktop.)*
- **Koneksi Internet**: Koneksi internet yang stabil untuk kelancaran penyimpanan data dan memuat kanvas editor.

---

## 3. Akses & Autentikasi (Login)

> 🔴 Administrator | 🟡 Staf

Untuk masuk ke dalam sistem, ikuti langkah berikut:

1. Buka browser dan kunjungi tautan login aplikasi: `[Masukkan URL Aplikasi]`
2. Pada halaman login, masukkan **Email** dan **Password** Anda yang telah terdaftar.
3. Klik tombol **Sign in** atau **Login**.

> *[Masukkan Screenshot: Halaman Login]*

**Lupa Kata Sandi:**
1. Klik tautan **Forgot password?** di bawah form login.
2. Masukkan email Anda dan sistem akan mengirimkan tautan untuk mereset kata sandi.

---

## 4. Gambaran Umum Antarmuka

Setelah berhasil login, Anda akan diarahkan ke halaman **Dashboard**.

- **Sidebar (Menu Kiri)**: Berisi menu-menu utama aplikasi (*Dashboard*, *Members*, *Meetings*, dll).
- **Header (Navigasi Atas)**: Terdapat informasi akun (profil), tombol notifikasi, dan opsi *Logout*.
- **Area Konten Utama**: Area tengah layar yang menampilkan data dari menu yang sedang Anda buka.

> *[Masukkan Screenshot: Halaman Dashboard Utama (Full Screen)]*

---

## 5. Master Data (Data Referensi)

> 🔴 Administrator

Master data adalah data acuan yang digunakan oleh seluruh modul dalam sistem. Pastikan data ini diisi terlebih dahulu sebelum menggunakan fitur-fitur lainnya.

### A. Kelompok (Groups) & Tingkatan (Levels)

Sistem menggunakan struktur **kelompok hierarkis** (bertingkat), di mana setiap kelompok memiliki induk (*parent*) dan dapat memiliki sub-kelompok (*children*). Setiap kelompok memiliki **tingkatan (Level)** yang mendefinisikan posisinya dalam struktur organisasi.

- **Contoh hierarki**: Pengurus Pusat → Pengurus Wilayah → Pengurus Cabang
- Nama kelompok otomatis disimpan dalam huruf kapital oleh sistem.
- Kelompok yang dinonaktifkan tidak akan muncul sebagai pilihan di fitur lainnya.

> *[Masukkan Screenshot: Daftar Kelompok & Strukturnya]*

### B. Kelompok Usia (Age Groups)

Pengelompokan usia anggota yang digunakan sebagai filter target peserta pada saat membuat rapat.

> *[Masukkan Screenshot: Daftar Kelompok Usia]*

### C. Kategori Pengurus (Position Categories)

Daftar kategori pengurus yang dapat dimiliki oleh anggota berstatus **Pengurus**. Kategori ini memiliki urutan prioritas (*sort order*) yang menentukan kategori pengurus utama anggota.

> *[Masukkan Screenshot: Daftar Kategori Pengurus]*

---

## 6. Manajemen Anggota (Members)

> 🔴 Administrator | 🟡 Staf

Fitur ini digunakan untuk mengelola pendaftaran dan data seluruh anggota organisasi.

### Struktur Data Anggota

Setiap anggota dalam sistem memiliki data sebagai berikut:

| Field | Keterangan |
|---|---|
| **Kode Anggota** | Kode unik identitas anggota |
| **Nama Lengkap** | Nama resmi anggota |
| **Nama Panggilan** | Nama akrab/panggilan sehari-hari |
| **Kelompok** | Kelompok/cabang tempat anggota terdaftar |
| **Tanggal Lahir / Usia** | Digunakan untuk pengelompokan usia |
| **Kelompok Usia** | Kategori usia (misal: Remaja, Dewasa, dst.) |
| **Jenis Kelamin** | Laki-laki / Perempuan |
| **Jenis Keanggotaan** | **ANGGOTA** (biasa) atau **PENGURUS** |
| **Status** | Aktif / Tidak Aktif |
| **Status Tetap** | Menandai apakah anggota sudah berstatus tetap |
| **QR Code** | Kode QR unik yang digunakan untuk absensi |
| **Foto Profil** | Foto anggota |
| **Foto Kartu Anggota** | Hasil desain kartu anggota yang telah dicetak |

> **Catatan**: Anggota dengan jenis keanggotaan **PENGURUS** dapat memiliki penugasan/posisi di satu atau lebih kelompok pengurus. Struktur pengurus ini digunakan sistem untuk menentukan hak akses dan peran pada rapat tertentu.

### Melihat & Mencari Data Anggota

1. Klik menu **Members** pada *sidebar* sebelah kiri.
2. Anda akan melihat tabel yang berisi daftar anggota.
3. Gunakan kolom **Search** di bagian kanan atas untuk mencari berdasarkan nama atau kode anggota.
4. Gunakan ikon **Filter** (corong) untuk menyaring anggota berdasarkan: status aktif, jenis keanggotaan, kelompok, kelompok usia, atau jenis kelamin.

> *[Masukkan Screenshot: Tampilan Tabel Anggota & Kolom Pencarian/Filter]*

### Menambah Anggota Baru

1. Di halaman *Members*, klik tombol **New Member** di pojok kanan atas.
2. Isi formulir kelengkapan data anggota sesuai kolom yang tersedia.
3. Setelah selesai, klik tombol **Create** untuk menyimpan data ke database.

> *[Masukkan Screenshot: Tampilan Form Penambahan Anggota Baru]*

### Import Data Anggota (Massal)

Sistem **inTime** menyediakan halaman khusus (*dedicated page*) untuk impor data anggota massal dengan fasilitas validasi *dry-run* sebelum data disimpan ke database:

1. Klik tombol **Impor Data** di pojok kanan atas halaman daftar anggota (*Members*). Anda akan diarahkan ke halaman **Impor Data Anggota** (`/admin/members/import`).
2. **Unduh Panduan & Template:** Buka accordion *Panduan & Template Impor*, lalu klik tombol **Unduh Panduan & Template Excel** untuk mendapatkan berkas spreadsheet resmi.
3. **Isi Berkas:** Masukkan data anggota pada berkas template. Pastikan kolom wajib terisi: *Nama Lengkap*, *Kelompok* (sesuai nama master data), dan *L/P* (Jenis Kelamin: L atau P).
4. **Unggah File:** Seret atau pilih berkas spreadsheet Anda pada area unggah. Sistem akan langsung memeriksa seluruh baris data di memori secara instan.
5. **Periksa Hasil Validasi:**
   - **Kartu Statistik:** Menampilkan total baris, jumlah data yang siap diimpor (hijau), dan jumlah data bermasalah (merah).
   - **Filter Tampilan:** Gunakan tombol segmentasi (*Semua Data*, *Siap Diimpor*, *Perlu Perbaikan*) untuk meninjau baris data. Jika terdapat baris salah, tab *Perlu Perbaikan* akan otomatis terbuka.
   - **Tabel Pratinjau (Read-Only):** Periksa kolom *Keterangan Masalah* berwarna merah tebal untuk mengetahui letak dan alasan kesalahan.
6. **Penanganan Data Bermasalah:**
   - Jika terdapat data yang belum valid, klik tombol **Unduh CSV Data Bermasalah**.
   - Buka berkas CSV tersebut di Excel, perbaiki nilai yang salah berdasarkan kolom *Keterangan Error*, lalu simpan.
   - Berkas hasil perbaikan ini dapat langsung diunggah kembali pada sesi impor baru tanpa perlu menghapus kolom keterangan error.
7. **Eksekusi Penyimpanan:**
   - **Impor Data Valid:** Klik tombol **"Impor Data Valid Saja"** (atau *"Simpan & Impor Semua"* jika 100% valid) di pojok kanan atas.
   - Konfirmasikan pada dialog modal native yang muncul.
   - Sistem akan menyimpan data dalam transaksi aman, otomatis menghasilkan QR Code bagi setiap anggota baru, dan mengarahkan kembali ke daftar anggota. Baris yang bermasalah dilewati dengan aman.

> *[Masukkan Screenshot: Tampilan Proses Import Data]*

### Mengedit dan Menghapus Data

- **Mengedit**: Klik tombol **Edit** (ikon pensil) pada baris anggota yang ingin diperbarui. Lakukan perubahan lalu klik **Save changes**.
- **Menghapus**: Klik tombol **Delete** (ikon tempat sampah) pada baris anggota, lalu konfirmasi penghapusan. *(Data yang dihapus bersifat soft-delete — tidak langsung hilang dari database.)*

> *[Masukkan Screenshot: Tombol Aksi Edit & Hapus]*

### Export Data Anggota

Di halaman *Members*, klik tombol **Export** untuk mengunduh seluruh data anggota dalam format **Excel (XLSX)** atau **CSV**.

---

## 7. Manajemen Rapat (Meetings)

> 🔴 Administrator | 🟡 Staf

Modul ini digunakan untuk membuat dan mengelola agenda rapat/pertemuan, termasuk pengaturan target peserta dan sistem kehadiran yang menyertainya.

### Struktur Data Rapat

| Field | Keterangan |
|---|---|
| **Nama Rapat** | Judul/nama agenda pertemuan |
| **Deskripsi** | Keterangan tambahan tentang rapat |
| **Tanggal Rapat** | Tanggal pelaksanaan |
| **Waktu Mulai** | Jam dimulainya rapat |
| **Waktu Buka Check-in** | Jam absensi dibuka (jika berbeda dari jam mulai) |
| **Waktu Selesai** | Jam berakhirnya rapat |
| **Kelompok** | Kelompok penyelenggara rapat |
| **Target Jenis Kelamin** | Semua / Laki-laki / Perempuan |
| **Target Kelompok Usia** | Filter peserta berdasarkan kelompok usia |
| **Fitur Izin Mandiri** | Aktifkan/nonaktifkan form izin mandiri untuk anggota |
| **Fitur Kehadiran Mandiri** | Aktifkan/nonaktifkan form absensi mandiri untuk anggota |

### Membuat Rapat Baru

1. Klik menu **Meetings** pada *sidebar*.
2. Klik tombol **New Meeting** di pojok kanan atas.
3. Isi seluruh detail rapat, termasuk waktu dan pengaturan target peserta.
4. Aktifkan toggle **Fitur Izin Mandiri** dan/atau **Fitur Kehadiran Mandiri** jika diperlukan.
5. Klik **Create** untuk menyimpan.

> *[Masukkan Screenshot: Form Pembuatan Rapat Baru]*

### Mencetak Laporan Rapat (PDF)

Setelah rapat selesai, laporan kehadiran dapat dicetak dalam format PDF:

1. Buka detail rapat yang ingin dicetak laporannya.
2. Klik tombol **Laporan PDF**.
3. File PDF akan otomatis diunduh atau ditampilkan di browser Anda.

> *[Masukkan Screenshot: Contoh Tampilan Laporan PDF Rapat]*

---

## 8. Kehadiran Mandiri & Izin Mandiri (Publik)

> 🟢 Anggota / Publik *(tidak perlu login)*

Fitur ini memungkinkan anggota untuk mengisi kehadiran atau mengajukan izin secara mandiri melalui tautan khusus yang dibagikan oleh panitia — **tanpa perlu memiliki akun** di sistem.

### A. Kehadiran Mandiri (Self-Presence)

Digunakan oleh anggota untuk **mengonfirmasi kehadiran sendiri** pada suatu rapat.

1. Anggota membuka tautan yang diberikan panitia (tautan mengandung kode rapat).
2. Sistem menampilkan halaman pencarian. Anggota mencari dirinya sendiri berdasarkan nama atau kode anggota.
3. Setelah ditemukan, anggota mengonfirmasi kehadiran.
4. Sistem mencatat kehadiran dengan metode **MANDIRI**.

> **Catatan**: Jika anggota tidak terdaftar dalam sistem, terdapat opsi **Daftar Sementara** untuk mengisi biodata dasar.

> *[Masukkan Screenshot: Halaman Self-Presence (Tampilan Anggota)]*

### B. Izin Mandiri (Self-Permission)

Digunakan oleh anggota untuk **mengajukan izin tidak hadir** pada suatu rapat.

1. Anggota membuka tautan izin yang diberikan panitia.
2. Anggota mencari nama dirinya, lalu memilih alasan izin (**IZIN** atau **SAKIT**).
3. Sistem mencatat status kehadiran anggota tersebut sesuai alasan yang dipilih.

> *[Masukkan Screenshot: Halaman Self-Permission (Tampilan Anggota)]*

---

## 9. Live Scanner (Absensi QR Real-Time)

> 🔴 Administrator | 🟡 Staf / Operator

Fitur **Live Scanner** digunakan oleh panitia/operator pada hari pelaksanaan rapat untuk mencatat kehadiran anggota secara real-time menggunakan pemindaian QR Code.

### Cara Kerja

Setiap anggota memiliki **QR Code unik** yang tersimpan di dalam sistem. QR Code ini dapat dicetak pada kartu anggota atau ditampilkan di perangkat anggota.

### Menggunakan Live Scanner

1. Akses halaman Live Scanner melalui menu yang tersedia (memerlukan login).
2. Arahkan kamera perangkat ke QR Code anggota yang datang.
3. Sistem otomatis mengenali dan mencatat kehadiran dengan metode **QR CODE**.

### Input Manual (Jika QR Code Tidak Terbaca)

Jika QR Code anggota tidak dapat dipindai (misal: kartu rusak):
1. Gunakan fitur **Input Manual** yang tersedia di halaman scanner.
2. Cari anggota menggunakan nama atau kode anggota.
3. Pilih anggota yang ditemukan dan konfirmasi kehadiran.
4. Sistem mencatat kehadiran dengan metode **MANUAL**.

### Daftarkan Anggota Sementara

Jika ada peserta yang belum terdaftar di sistem:
1. Klik tombol **Daftarkan Sementara**.
2. Isi biodata singkat peserta.
3. Peserta tercatat sebagai anggota sementara dan kehadirannya tersimpan.

> *[Masukkan Screenshot: Tampilan Halaman Live Scanner]*

### Status Kehadiran

Setiap catatan kehadiran dalam sistem memiliki salah satu dari empat status berikut:

| Kode | Status | Keterangan |
|---|---|---|
| **H** | HADIR | Anggota hadir dalam rapat |
| **I** | IZIN | Anggota mengajukan izin tidak hadir |
| **S** | SAKIT | Anggota tidak hadir karena sakit |
| **A** | ALPA | Anggota tidak hadir tanpa keterangan |

---

## 10. Card Editor (Desain Kartu Anggota)

> 🔴 Administrator | 🟡 Staf

Fitur interaktif untuk menyusun dan mendesain layout kartu anggota secara langsung melalui browser.

### Membuka Area Kerja

1. Navigasikan ke menu **Card Templates**, lalu pilih template yang ingin diedit.
2. Klik tombol **Buka Editor** untuk masuk ke halaman Card Editor.
3. Anda akan dihadapkan pada layar **kanvas** di bagian tengah dan **panel alat** di sisi layar.

> *[Masukkan Screenshot: Tampilan Penuh Halaman Card Editor]*

### Menambahkan Teks & Gambar

1. **Menambah Teks**: Klik tombol **Teks (T)** pada menu alat. Teks *placeholder* akan muncul di kanvas. Klik ganda pada teks tersebut untuk mengganti tulisannya.
2. **Menambah Gambar/Logo**: Klik menu **Image**, pilih file gambar dari komputer Anda, dan gambar akan otomatis diletakkan pada kanvas.

> *[Masukkan Screenshot: Panel Alat (Toolbar)]*

### Upload Background & Font Kustom

- **Background**: Unggah gambar latar belakang kartu melalui tombol **Upload Background**.
- **Font**: Unggah file font kustom (format TTF/OTF) melalui tombol **Upload Font** jika font yang dibutuhkan tidak tersedia secara default.

### Mengatur & Mengedit Elemen

- **Menggeser**: Klik dan tahan (*drag*) objek/elemen pada kanvas ke posisi yang diinginkan.
- **Mengubah Ukuran**: Klik salah satu objek, lalu tarik titik-titik kontrol di ujung elemen.
- **Kustomisasi Teks**: Saat teks diklik/dipilih, atur jenis huruf, ukuran, ketebalan, dan warnanya menggunakan panel properti.

> *[Masukkan Screenshot: Proses Resize Elemen & Penyesuaian Properti Font]*

### Menyimpan & Ekspor Hasil

- **Menyimpan**: Klik tombol **Save** pada halaman editor untuk menyimpan rancangan sementara ke dalam sistem.
- **Ekspor/Download**: Klik tombol **Download** untuk mengunduh hasil desain kartu ke komputer Anda.

> *[Masukkan Screenshot: Tombol Simpan & Unduh]*

---

## 11. Laporan & Pengaturan Akun

> 🔴 Administrator | 🟡 Staf

- **Laporan/Ekspor Data Anggota**: Pada halaman *Members*, gunakan fitur **Export** untuk mengunduh seluruh data anggota ke dalam format *Excel* (XLSX) atau CSV.
- **Profil Akun**: Klik pada nama profil Anda di pojok kanan atas, lalu pilih **Profile**. Di sini Anda dapat memperbarui nama, email, dan mereset kata sandi.

> *[Masukkan Screenshot: Halaman Profil & Pengaturan Akun]*

---

## 12. Pemecahan Masalah (Troubleshooting)

Beberapa kendala ringan yang sering dijumpai beserta langkah penanganannya:

- **Gagal Login (Kredensial Salah)**
  - Pastikan fitur *Caps Lock* tidak aktif saat mengetik password.
  - Jika Anda lupa sandi, gunakan alur *Forgot Password*.

- **Halaman Card Editor Memuat Terus / Kanvas Tidak Muncul**
  - Pastikan koneksi internet stabil.
  - Coba muat ulang halaman secara paksa dengan menekan `Ctrl + F5` (Windows) atau `Cmd + Shift + R` (Mac).

- **Proses Simpan (Save) Gagal**
  - Periksa kembali apakah ada kolom bertanda bintang merah (*required*) yang terlewat atau formatnya salah.

- **QR Code Anggota Tidak Terbaca di Scanner**
  - Pastikan pencahayaan ruangan cukup terang.
  - Coba bersihkan lensa kamera perangkat.
  - Gunakan fitur **Input Manual** sebagai alternatif.

- **Tautan Self-Presence / Self-Permission Tidak Dapat Diakses**
  - Pastikan panitia telah mengaktifkan fitur tersebut di pengaturan rapat yang bersangkutan.
  - Pastikan waktu rapat belum berakhir.

---

## 13. Bantuan & Dukungan Teknis

Apabila Anda mengalami masalah sistem yang fatal, halaman *error*, atau aplikasi tidak dapat diakses sama sekali, silakan hubungi tim dukungan kami:

| Nama | Kontak (WhatsApp/Telepon) |
|---|---|
| **Dian** | 085161423542 |
| **Lubis** | 085791978816 |

> **Catatan Penting**: Harap sertakan lampiran gambar layar (*screenshot*) saat error terjadi untuk mempercepat tim kami menganalisis masalah Anda.

Sistem inTime terus dikembangkan secara berkala. Masukan dan laporan kendala dari pengguna sangat kami hargai untuk meningkatkan kualitas sistem.

---
*Dokumen ini diperbarui terakhir pada: September 2026*
