# Dokumen Rencana & Checklist: Pembaruan Fitur Bulk Import Anggota

Dokumen ini disusun dari sudut pandang **UX Professional** dan **Product Manager (PM)** untuk merombak fitur impor data anggota massal (*bulk import*) di aplikasi **inTime**, beralih dari sistem popup modal yang sempit menjadi halaman penuh (*dedicated page*) berbasis komponen native Filament.

---

## 1. Analisis Fitur Sebelumnya (Current State)

### 1.1 Lokasi & Komponen Eksisting
* **Lokasi Tombol:** Header Action pada halaman `ListMembers` ([ListMembers.php](file:///d:/SandBox/Antigravity/inTime/app/Filament/Resources/Members/Pages/ListMembers.php)).
* **Komponen UI:** Menggunakan Filament Modal Action (`Action::make('import')->modal()`).
* **Engine Pemroses:** 
  * Validator: `App\Imports\MemberImportValidator` ([MemberImportValidator.php](file:///d:/SandBox/Antigravity/inTime/app/Imports/MemberImportValidator.php))
  * Importer: `App\Filament\Imports\MemberImporter` ([MemberImporter.php](file:///d:/SandBox/Antigravity/inTime/app/Filament/Imports/MemberImporter.php))
  * Template: `App\Exports\MemberTemplateExport` ([MemberTemplateExport.php](file:///d:/SandBox/Antigravity/inTime/app/Exports/MemberTemplateExport.php))

### 1.2 Alur Fitur Sebelumnya
1. Pengguna membuka menu Anggota (`/admin/members`) lalu menekan tombol **"Impor Data"** di pojok kanan atas.
2. Muncul dialog modal sempit berisi:
   * Accordion unduh template Excel (`MemberTemplateExport`).
   * Input upload file (`FileUpload`).
3. Ketika file diunggah, event `afterStateUpdated` memanggil `MemberImportValidator` secara sinkron.
4. Jika ditemukan kesalahan:
   * Pesan error digabung menjadi string teks panjang di dalam satu field `Textarea` yang di-*disable* (`error_display`).
   * Form submit ditolak oleh rule penahan (`is_valid` harus bernilai `true`).
5. Jika data 100% valid tanpa kesalahan:
   * Tombol modal submit **"Impor Data"** menjadi aktif.
   * Saat diklik, data diproses langsung ke database via `Excel::import(new MemberImporter, $filePath)`.
   * File dihapus dan halaman di-*refresh*.

### 1.3 Aturan Bisnis & Validasi Eksisting
* **`nama_lengkap_wajib`**: Wajib diisi, string, maksimal 255 karakter.
* **`kelompok_wajib`**: Wajib diisi, harus cocok dengan nama pada master data kelompok (`Group`) (pencarian case-insensitive dengan cache).
* **`lp_wajib`**: Wajib diisi, hanya menerima `L`, `P`, `l`, atau `p`.
* **`id_anggota`**:
  * *Opsional*. Jika kosong, sistem membuat anggota baru (ID di-generate oleh sistem).
  * Jika diisi dan sudah terdaftar: Melakukan update data anggota tersebut.
  * Jika diisi dan berada di status soft-deleted (*tong sampah*): Ditolak dengan pesan error.
* **`tanggal_lahir`**:
  * *Opsional*. Format yang diterima: `DD/MM/YYYY`, `YYYY-MM-DD`, atau serial number Excel.
  * Menghitung umur otomatis dan menentukan relasi kategori usia (`AgeGroup`). Jika kosong, otomatis masuk kategori default (*Pra Nikah*).
* **Status & Jenis Keanggotaan**: Default aktif (`status = true`) dan jenis anggota (`membership_type = ANGGOTA`).
* **Observer Trigger**: Setiap penambahan anggota memicu Observer untuk otomatis menghasilkan kode QR.

### 1.4 Masalah & Keterbatasan Fitur Sebelumnya
1. **Risiko Kehilangan Progres (UX Fragile):** Modal rentan tertutup jika pengguna tidak sengaja mengeklik area abu-abu di luar modal (*backdrop*), menghilangkan file yang baru dipilih dan hasil pemeriksaannya.
2. **Keterbatasan Ruang (Cramped Space):** Modal tidak mampu menyajikan tabel baris per baris. Pengguna kesulitan membaca detail kesalahan yang hanya berupa tumpukan teks di dalam satu textarea kecil.
3. **Pola "All-or-Nothing" yang Kaku:** Jika terdapat 100 data dan hanya 1 baris yang salah ketik nama kelompok, seluruh proses impor diblokir. Pengguna terpaksa membatalkan, mencari baris salah secara manual di file spreadsheet aslinya, lalu mengulang dari awal.
4. **Ketiadaan Konfirmasi Data (No Visual Preview):** Pengguna tidak memiliki kesempatan untuk melihat wujud tabel data yang akan masuk ke database sebelum data tersebut benar-benar tersimpan.
5. **Solusi Perbaikan Error Lambat:** Pengguna harus membaca nomor baris di textarea, lalu membuka Excel dan mencari baris tersebut satu persatu. Tidak ada fasilitas unduh baris bermasalah.

---

## 2. Solusi Baru: Halaman Dedikasi Impor (Dedicated Page)

Fitur impor dialihkan dari dialog modal ke **Filament Custom Page** tersendiri (misal: `/admin/members/import`). Seluruh komponen UI dibangun menggunakan **Filament Native Components** untuk menjamin estetika, tema gelap/terang yang konsisten, responsivitas, dan kemudahan pemeliharaan kode.

```
                    ALUR USER EXPERIENCE (TARGET)
                    
  [ListMembers Page]
          │
          ▼ Klik Tombol "Impor Data"
  [Halaman Impor: /admin/members/import]
          │
          ├─ 1. Download Template & Panduan (Button Native)
          ├─ 2. Upload File Spreadsheet (.xlsx, .xls, .csv)
          │      │
          │      ▼ (Otomatis Jalankan Dry-Run Validator)
          ├─ 3. Card Statistik Native:
          │      [ Total Baris: 100 ] [ Siap Impor: 85 (Hijau) ] [ Perlu Perbaikan: 15 (Merah) ]
          │
          ├─ 4. Area Solusi Data Error (Kondisional jika Error > 0):
          │      [ Callout Alert ] + [ Button: Unduh CSV Data Bermasalah ]
          │      (File CSV berisi seluruh data gagal + 1 kolom 'Keterangan Error')
          │
          ├─ 5. Tabel Preview Data Native (Filament Table):
          │      Tab: [ Semua (100) ] | [ Siap Diimpor (85) ] | [ Perlu Perbaikan (15) ]
          │      Kolom: Baris | Status (Badge) | ID | Nama | Kelompok | L/P | Catatan Error
          │
          └─ 6. Action Bar (Sticky / Bawah):
                 ├─ [ Batal / Kembali ]
                 ├─ [ Ganti File ]
                 └─ [ Tombol Simpan ]:
                      • Semua Valid   -> "Impor Semua Data (100)"
                      • Ada Error     -> "Impor 85 Data Valid Saja" (+ Konfirmasi Modal)
                      • Semua Error   -> Disabled (0 data valid)
```

---

## 3. Checklist To-Do List Berdasarkan Fase

### Fase 1: Perutean & Struktur Halaman Filament (Routing & Architecture)
- [x] Buat Filament Custom Page baru: `App\Filament\Resources\Members\Pages\ImportMembers.php`.
- [x] Daftarkan rute halaman baru pada `MemberResource::getPages()` dengan key `'import' => Pages\ImportMembers::route('/import')`.
- [x] Ubah tombol header **"Impor Data"** pada `ListMembers.php` dari `Action::modal()` menjadi navigasi redirect menuju `MemberResource::getUrl('import')`.
- [x] Tambahkan breadcrumb native Filament pada halaman `ImportMembers` (Beranda > Anggota > Impor Anggota) serta tombol "Kembali ke Daftar Anggota".
- [x] Terapkan pengecekan izin akses (*authorization policy*): Hanya user dengan hak `Import:Member` yang dapat mengakses halaman ini.

### Fase 2: Service / Engine Validator "Dry-Run" & Ekstraksi Data
- [x] Buat class DTO / Processor khusus: `App\Services\Members\MemberImportParser.php` untuk memisahkan data baris per baris ke dalam memori tanpa menyentuh database.
- [x] Implementasikan validasi dry-run yang mengembalikan 2 kumpulan data (*collection*):
  - `$validRows`: Koleksi data yang lolos seluruh aturan validasi beserta data pendukungnya (grup ID, usia terhitung, kategori usia).
  - `$invalidRows`: Koleksi data yang gagal validasi beserta data aslinya dan kumpulan pesan error yang spesifik.
- [x] Format pesan error yang informatif dan jelas (human-readable):
  - *Nama Kosong:* `[Nama Lengkap]: Wajib diisi (maks. 255 karakter)`
  - *Grup Tidak Ada:* `[Kelompok]: Kelompok '[Nama]' tidak ditemukan di master data`
  - *Gender Salah:* `[Jenis Kelamin]: Format tidak valid. Wajib diisi 'L' atau 'P'`
  - *Tanggal Salah:* `[Tanggal Lahir]: Format '[Input]' tidak valid. Gunakan format DD/MM/YYYY`
  - *ID Soft Delete:* `[ID Anggota]: ID '[Kode]' tidak dapat digunakan karena berada di tong sampah`
- [x] Gabungkan beberapa error dalam 1 baris menggunakan bullet list atau pemisah titik koma `;`.

### Fase 3: Tampilan Antarmuka (Filament Native UI & Preview Table)
- [x] **Bagian 1: Card Panduan & File Upload**
  - [x] Buat komponen `Section` yang dapat di-collapse berisi panduan ringkas dan tombol unduh template `MemberTemplateExport`.
  - [x] Buat `FileUpload` native Filament dengan validasi tipe file spreadsheet (`.xlsx`, `.xls`, `.csv`).
- [x] **Bagian 2: Kartu Statistik Ringkasan (Stats Cards)**
  - [x] Tampilkan 3 kartu ringkasan saat file selesai dianalisis:
    - Card 1: Total Data Dibaca (Netral/Info).
    - Card 2: Data Siap Diimpor (Warna Success/Hijau dengan icon checklist).
    - Card 3: Data Bermasalah (Warna Danger/Merah dengan icon silang).
- [x] **Bagian 3: Banner & Action Data Bermasalah**
  - [x] Munculkan callout banner peringatan jika terdapat data tidak valid.
  - [x] Sediakan tombol action native: **"Unduh CSV Data Bermasalah"** yang langsung dapat diklik.
- [x] **Bagian 4: Tabel Preview Data Native (Filament Table)**
  - [x] Implementasikan Livewire/Filament Table untuk merender baris hasil parsing file.
  - [x] Tambahkan tab filter native pada tabel:
    - Tab `Semua Data (X)`
    - Tab `Siap Diimpor (Y)`
    - Tab `Perlu Perbaikan (Z)` (otomatis aktif secara default jika ada data bermasalah).
  - [x] Sediakan kolom-kolom tabel yang rapi:
    - Kolom No / Baris Excel Asli.
    - Kolom Status: Badge hijau "Siap Impor" atau badge merah "Perlu Perbaikan".
    - Kolom ID Anggota, Nama Lengkap, Kelompok, L/P, Tanggal Lahir.
    - Kolom Keterangan Masalah (hanya terisi pada baris bermasalah).
  - [x] Berikan fitur paginasi (10/25/50 baris) agar performa browser tetap ringan saat memuat ratusan baris data preview.

### Fase 4: Generator Ekspor CSV Data Bermasalah
- [x] Buat export class: `App\Exports\MemberImportErrorsExport.php`.
- [x] Pastikan file yang dihasilkan berformat CSV/Excel dengan mempertahankan seluruh kolom asli dari file input pengguna:
  - `ID Anggota`
  - `Kelompok (Wajib)`
  - `Nama Lengkap (Wajib)`
  - `Nama Panggilan`
  - `Tanggal Lahir`
  - `L/P (Wajib)`
  - **`Keterangan Error`** *(Kolom tambahan paling kanan)*.
- [x] Pastikan bahwa jika pengguna memperbaiki file CSV ini lalu mengunggahnya kembali, kolom `Keterangan Error` akan otomatis diabaikan oleh parser sistem.

### Fase 5: Eksekusi Penyimpanan & Database Transaction
- [x] Pastikan data **TIDAK** masuk ke database sebelum tombol simpan ditekan oleh pengguna.
- [x] Logika tombol simpan:
  - [x] **Kondisi 1 (100% Valid):** Tombol bertuliskan `"Simpan & Impor Semua (X Data)"` warna hijau/primer.
  - [x] **Kondisi 2 (Ada Error & Ada Valid):** Tombol bertuliskan `"Impor X Data Valid Saja"` dengan modal konfirmasi native Filament: *"Terdapat Y data bermasalah yang akan dilewati. Lanjutkan mengimpor X data yang valid?"*.
  - [x] **Kondisi 3 (0% Valid):** Tombol disabled dengan tooltip penjelas.
- [x] Jalankan penyimpanan dalam blok `DB::transaction()`:
  - Lakukan insert data anggota baru dan update data anggota yang sudah ada sesuai ID Anggota.
  - Pastikan event Eloquent/Observer terpanggil agar QR Code anggota digenerate secara otomatis.
- [x] Tampilkan notifikasi sukses Filament (`Notification::make()->success()`) dan bersihkan file upload sementara.
- [x] Redirect pengguna kembali ke halaman utama anggota (`MemberResource::getUrl('index')`).

### Fase 6: Pengujian, Edge Cases & Finishing
- [x] Uji skenario file kosong atau hanya berisi baris header template.
- [x] Uji skenario file dengan ribuan baris untuk memastikan efisiensi memori (tidak *Memory Exhausted*).
- [x] Uji nama kelompok dengan spasi di awal/akhir (*trim*) atau variasi huruf besar-kecil.
- [x] Uji tanggal lahir dengan format bervariasi (`15/01/1990`, `1990-01-15`, format serial angka Excel).
- [x] Uji pencegahan penggunaan ID Anggota yang berstatus soft delete.
- [x] Uji alur download CSV error -> diedit di Excel -> diupload ulang -> berhasil 100%.

---

## 4. Matriks Pengecekan: Alur, Fungsi, dan Aturan Bisnis

| Komponen / Aturan | Kondisi Saat Ini | Kondisi Target Baru | Verifikasi Keselarasan |
| :--- | :--- | :--- | :--- |
| **Bentuk Tampilan** | Modal dialog sempit di `ListMembers`. | Dedicated Full Page (`/admin/members/import`). | Sesuai. Memberikan visual horizontal lega untuk tabel preview dan mengurangi risiko form tertutup mendadak. |
| **Pemicu Validasi** | `afterStateUpdated` pada FileUpload modal. | Reactive handler saat file selesai diunggah di halaman. | Sesuai. Memproses file di memori (*dry-run*) tanpa query write ke DB. |
| **Penyajian Error** | Satu field `Textarea` disabled berisi teks mentah. | Stat cards + Tab Filter Tabel + Kolom Catatan Error per baris. | Sesuai. Pengguna bisa langsung melihat baris mana yang salah dan apa alasannya. |
| **Penanganan Data Error** | User wajib memperbaiki file asli secara manual tanpa bantuan ekspor. | Tombol unduh file CSV berisi baris yang bermasalah + kolom `Keterangan Error`. | Sesuai. Mengikuti standar enterprise: tinggal unduh, edit baris salah di Excel, lalu upload ulang. |
| **Fleksibilitas Impor** | *All-or-Nothing* (1 error menggugurkan seluruh data). | Mendukung *Partial Import* (bisa memilih untuk mengimpor data valid saja). | Sesuai. Data valid tidak tertahan hanya karena segelintir baris bermasalah. |
| **Komitmen ke Database** | Langsung saat tombol modal ditekan (jika valid). | Hanya dieksekusi saat tombol simpan di halaman ditekan dengan konfirmasi jelas. | Sesuai. Menghilangkan keraguan pengguna dan mencegah data sampah masuk. |
| **Integritas Aturan Bisnis** | - Validasi NIK/ID Soft Delete<br>- Relasi Grup & Kategori Usia<br>- Auto generate QR Code | Tetap dipertahankan 100% menggunakan aturan bisnis yang sama persis. | Sesuai. Tidak ada aturan domain yang dihilangkan, hanya alur presentasi dan error handling yang disempurnakan. |
| **Konsistensi UI** | Campuran accordion dan textarea. | 100% Filament Native UI (Section, StatsCard, Badge, Native Table, Action Modal). | Sesuai. Tampilan seragam dengan tema Filament admin panel. |
