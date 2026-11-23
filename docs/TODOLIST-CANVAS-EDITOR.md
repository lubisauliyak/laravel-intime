# Dokumen Rencana & Checklist: Optimalisasi Canvas Editor, Preview Template, & Generator KTA

Dokumen ini disusun dari perspektif **Product Management (PM)** dan **User Experience (UX)** untuk memandu standardisasi, perbaikan logika backend, dan penyempurnaan antarmuka sistem pembuatan kartu anggota (KTA).

Prinsip Utama Perbaikan:
1. **Prinsip WYSIWYG Sejati (*What You See Is What You Get*):** Menjamin 100% keselarasan visual antara apa yang dirancang operator di kanvas browser (Fabric.js) dengan gambar yang digenerate oleh server (PHP Intervention Image / GD).
2. **Keandalan Tipografi & Aset:** Menyelesaikan kegagalan pemanggilan font custom yang diunggah (*silent fallback*) dan mencegah distorsi pada gambar latar maupun foto profil.
3. **Pengalaman Pengguna (UX) yang Mulus & Mencegah Kesalahan (*Error Prevention*):** Menghadirkan kontrol standar desain grafis (Undo/Redo, teks statis, layer order, koordinat intuitif, dan pratinjau langsung) tanpa membebani operator.

---

## 1. Analisis Alur & Logika Sistem

### 1.1 Alur Font Custom (Upload $\rightarrow$ Canvas $\rightarrow$ Save $\rightarrow$ Generate)
* **Kondisi Saat Ini (Masalah):**
  * Pengguna mengunggah `Lexend-Regular.ttf` atau `Lexend-Bold.ttf`.
  * Sistem menyimpannya sebagai `fonts/Lexend-Regular.ttf` dengan family name `Lexend`.
  * Di browser, font dimuat via `@font-face` dan dirender dengan baik di Fabric.js.
  * Saat kartu digenerate di backend (`CardGeneratorService.php`), sistem mencari `storage/app/public/fonts/Lexend.ttf`. Karena nama file berbeda, sistem **gagal menemukan font** dan diam-diam beralih (*silent fallback*) ke `Inter-Regular.ttf`.
  * Upload font mengizinkan `.woff` dan `.woff2`, padahal library PHP GD **tidak mendukung** format web font tersebut (hanya mendukung TrueType `.ttf` dan OpenType `.otf`).
* **Target Solusi & Logika:**
  * Batasi validasi upload font hanya pada ekstensi `.ttf` dan `.otf`. Berikan pesan edukatif jika pengguna mencoba mengunggah `.woff`/`.woff2`.
  * Simpan relasi/pola nama file secara cerdas. Jika pengguna memilih font `Lexend` dengan ketebalan `Bold`, sistem backend harus secara berurutan mencari:
    1. `fonts/Lexend-Bold.ttf` (atau `.otf`)
    2. `fonts/Lexend.ttf` (atau `.otf`)
    3. `fonts/Lexend-Regular.ttf` (atau `.otf`)
    4. Baru fallback ke default `Inter-Regular.ttf` jika benar-benar tidak ada.

### 1.2 Alur Pemrosesan Gambar (Background & Foto Profil)
* **Kondisi Saat Ini (Masalah):**
  * Di Canvas Editor, background dan foto profil menggunakan teknik `cover` (proporsional, kelebihan bidang dipotong di tengah).
  * Di Backend Generator, background dan foto dieksekusi dengan `$image->resize($width, $height)`. Akibatnya, jika rasio gambar tidak persis sama dengan ukuran kanvas, gambar akan **melar atau gepeng (distorsi rasio)**.
* **Target Solusi & Logika:**
  * Di backend (`CardGeneratorService.php`), gunakan method `cover($width, $height)` dari Intervention Image v3 untuk background dan foto profil, menjaga rasio aspek asli (1:1 untuk foto, dan rasio kartu untuk background).

### 1.3 Alur Text Wrapping & Typography Metrics
* **Kondisi Saat Ini (Masalah):**
  * Fabric.js memecah baris teks secara otomatis berdasarkan batas lebar kotak teks (*bounding box width*).
  * Backend Generator menggunakan rumus perkiraan karakter kasar: `$maxChars = floor($width / ($fontSize * 0.6))` lalu menjalankan `wordwrap($text, $maxChars, "\n", true)`. Parameter `true` menyebabkan kata-kata panjang dipotong secara paksa di tengah suku kata (misal: "Sukamaj" - "u").
* **Target Solusi & Logika:**
  * Matikan pemotongan paksa kata (`cut_long_words = false`).
  * Terapkan algoritma wrapping berbasis batas kata utuh (*word-boundary wrapping*).
  * Tambahkan toleransi padding horizontal pada teks agar posisi jatuh teks di backend identik dengan yang terlihat di kanvas editor.

### 1.4 Alur Koordinat & Navigasi Pengguna
* **Kondisi Saat Ini (Masalah):**
  * Input manual posisi Y pada sidebar editor dihitung dari bawah ke atas (`canvasHeight - centerY`), berbeda dengan standar software desain grafis (Figma/Canva) yang menempatkan Y=0 di sudut kiri atas.
  * Tombol "Buka Editor" tersembunyi di dalam halaman View Template, tidak tersedia di tabel utama template kartu.
  * Operator tidak memiliki tombol "Teks Statis" untuk menambahkan judul kartu ("KARTU TANDA ANGGOTA"), masa berlaku, atau label field ("NIM:", "Nama:"). Semua teks dipaksa terikat pada atribut dinamis anggota.

---

## 2. Checklist Pengerjaan Berdasarkan Fase

### Fase 1: Perbaikan Kritis Font Custom & Generator Backend
- [x] Perbarui validasi file upload font di `CardEditorController::uploadFont`:
  - [x] Batasi ekstensi hanya untuk `.ttf` dan `.otf` (`extensions:ttf,otf`).
  - [x] Tambahkan pesan error validasi yang ramah jika pengguna mengunggah `.woff` atau `.woff2` (jelaskan bahwa generator kartu membutuhkan font TrueType/OpenType).
- [x] Perbaiki logika pencarian file font di `CardGeneratorService::processImage`:
  - [x] Implementasikan pencarian nama file fleksibel: cek varian bobot (`{$fontFamily}-{$suffix}.ttf`, `{$fontFamily}-{$suffix}.otf`), nama keluarga langsung (`{$fontFamily}.ttf`), dan nama regular (`{$fontFamily}-Regular.ttf`).
  - [x] Pastikan font custom yang tersimpan di `storage/app/public/fonts/` terbaca dengan benar sesuai bobot (*normal* vs *bold*).
  - [x] Tambahkan logging peringatan (*warning log*) jika font custom tidak ditemukan sebelum beralih ke fallback Inter.

### Fase 2: Sinkronisasi Visual & Resolusi Distorsi Gambar (WYSIWYG)
- [x] Perbaiki pemrosesan gambar latar belakang di `CardGeneratorService`:
  - [x] Ganti pemanggilan `$bg->resize()` menjadi `$bg->cover($width, $height)` agar gambar latar tetap proporsional tanpa terdistorsi.
- [x] Perbaiki pemrosesan foto profil anggota di `CardGeneratorService`:
  - [x] Ganti pemanggilan `$photoImage->resize()` menjadi `$photoImage->cover($photoSize, $photoSize)` agar foto berorientasi portrait (3:4) tidak gepeng.
- [x] Perbaiki pemisahan baris teks (*text wrapping*) di `CardGeneratorService`:
  - [x] Hapus pemotongan paksa suku kata (`wordwrap(..., false)`).
  - [x] Sesuaikan faktor estimasi lebar teks agar tidak meluber keluar dari batas kotak teks Fabric.js.
- [x] Normalisasi titik jangkar teks (*text anchor point*) antara Fabric.js (origin center/left) dan Intervention Image (align left/center/right dan valign).

### Fase 3: Peningkatan UX & Kontrol Desain di Canvas Editor
- [x] Standarisasi Sistem Koordinat di `resources/views/card-editor/index.blade.php`:
  - [x] Ubah input posisi Y menjadi standar grafis (Y = 0 di sudut kiri atas, bertambah ke bawah).
  - [x] Sinkronkan nilai input X dan Y saat elemen digeser langsung di kanvas.
- [x] Tambahkan Fitur "Teks Statis / Bebas":
  - [x] Tambahkan tombol "Teks Statis" pada panel Tambah Elemen.
  - [x] Berikan input isi teks di panel properti agar pengguna dapat mengedit kata-kata statis (misal: "KARTU ANGGOTA", "MASA BERLAKU").
  - [x] Pastikan `CardGeneratorService` merender teks statis apa adanya tanpa menimpanya dengan data anggota.
- [x] Tambahkan Kontrol Riwayat Aksi (Undo / Redo):
  - [x] Implementasikan stack riwayat state kanvas (JSON snapshot) di JavaScript.
  - [x] Tambahkan tombol Undo (`Ctrl+Z`) dan Redo (`Ctrl+Y`) pada toolbar atas.
- [x] Tambahkan Kontrol Urutan Layer (Z-Index):
  - [x] Tambahkan tombol "Bawa ke Depan" (*Bring Forward / To Front*) dan "Kirim ke Belakang" (*Send Backward / To Back*) di panel properti elemen.
- [x] Ganti Notifikasi Primitif `alert()`:
  - [x] Implementasikan toast notification yang elegan dan non-intrusif saat berhasil menyimpan desain, gagal upload, atau terjadi error.

### Fase 4: Fitur Pratinjau Cepat & Live Mock Data
- [x] Tambahkan Tombol "Pratinjau Kartu" (*Live Preview Modal*) di Canvas Editor:
  - [x] Buat modal dialog yang memanggil endpoint preview backend tanpa harus meninggalkan halaman editor.
  - [x] Tampilkan hasil generate kartu aktual dengan data contoh lengkap (foto simulasi, nama panjang, QR code).
  - [x] Sediakan toggle untuk menguji nama pendek vs nama sangat panjang untuk memverifikasi kerapian layout teks.
- [x] Ganti Sumber Gambar Placeholder QR Code:
  - [x] Hindari pemanggilan URL eksternal pihak ketiga (`api.qrserver.com`) di kanvas editor.
  - [x] Gunakan SVG / data URI lokal agar editor tetap berfungsi optimal walau tanpa koneksi internet luar atau saat API pihak ketiga bermasalah.

### Fase 5: Integrasi Filament Admin & Alur Navigasi
- [x] Tambahkan Aksi Cepat pada Tabel Template di `CardTemplatesTable`:
  - [x] Tambahkan action button langsung **"Desain Kanvas"** / **"Buka Editor"** (`heroicon-o-paint-brush`) di baris tabel template dengan status aktif/draft.
  - [x] Pastikan aksi membuka editor di tab baru dengan otorisasi policy yang sesuai.
- [x] Tampilkan Thumbnail Pratinjau Terkini di Daftar Template:
  - [x] Tambahkan kolom gambar kecil (*preview thumbnail*) di `CardTemplatesTable` agar administrator dapat langsung mengenali template secara visual.
- [x] Sediakan Tombol "Buka Editor" di Halaman Edit Template (`EditCardTemplate`):
  - [x] Tambahkan header action tombol editor di samping tombol simpan form.

### Fase 6: Pengujian Kualitas & Verifikasi End-to-End
- [x] Uji Verifikasi Font Custom:
  - [x] Unggah font custom baru (misal: Lexend Regular & Bold).
  - [x] Buat teks dengan font tersebut, simpan desain, dan lakukan generate KTA anggota.
  - [x] Verifikasi secara visual bahwa kartu hasil cetak menggunakan font Lexend (bukan Inter).
- [x] Uji Ketahanan Rasio Gambar:
  - [x] Pasang gambar background beresolusi non-standar (panjang / lebar) dan pastikan tidak gepeng di kartu akhir.
  - [x] Pasang foto profil anggota berasio 3:4 dan pastikan wajah ter-crop rapi di tengah bingkai kotak 1:1.
- [x] Uji Teks Panjang:
  - [x] Masukkan nama anggota dengan 40+ karakter dan pastikan pemecahan baris tidak merusak kata di tengah suku kata.
- [x] Uji Alur Navigasi Lengkap:
  - [x] Buat template baru di Filament $\rightarrow$ Buka Canvas Editor $\rightarrow$ Atur layout $\rightarrow$ Cek Preview Modal $\rightarrow$ Simpan $\rightarrow$ Cetak KTA Anggota.

---

## 3. Standar Teknis & Batasan Arsitektur
* **Format Font yang Didukung Sistem:** TrueType (`.ttf`) dan OpenType (`.otf`). Format `.woff` dan `.woff2` dinonaktifkan dari antarmuka input font.
* **Driver Grafis Backend:** Menggunakan Intervention Image v3 dengan Driver GD. Semua transformasi gambar wajib menggunakan metode non-destruktif (`cover()`) untuk menjaga rasio aspek.
* **Kompatibilitas Filament:** Semua aksi pada tabel dan infolist menggunakan komponen native Filament v4 tanpa menyuntikkan template blade kustom ke dalam panel admin.
