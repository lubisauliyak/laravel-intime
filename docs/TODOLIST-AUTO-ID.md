# Dokumen Rencana & Checklist: Otomatisasi ID Anggota & Pewarisan Template KTA

Dokumen ini disusun untuk memandu implementasi fitur **penomoran ID anggota otomatis berbasis hierarki grup**, **pewarisan desain template KTA**, serta integrasinya ke dalam **alur Bulk Import Anggota**. 

Prinsip utama pengerjaan:
1. **Performa Tinggi:** Menggunakan penomoran *next increment* atomik dalam transaksi database, indexing kolom kode, dan pencegahan query berulang (*N+1 problem*).
2. **Pengalaman Pengguna (UX) Humanis:** Menggunakan **100% komponen native Filament** (tanpa inject file Blade custom ke dalam Filament), memberikan informasi yang transparan dan mudah dipahami operator.
3. **Pragmatis & Anti Over-Engineering:** Solusi bertahap (MVP) yang kokoh, mudah dirawat, dan siap berkembang bila skala data bertambah.

---

## 1. Aturan Bisnis & Logika Sistem

### 1.1 Struktur Kode Hierarki Grup
* Setiap grup memiliki kolom `code` (misal: `10`, `07`). Kolom ini bersifat opsional (boleh kosong).
* Prefix ID dihitung secara berantai dari tingkat tertinggi ke tingkat terendah:  
  `[Kabupaten]` $\rightarrow$ `[Daerah]` $\rightarrow$ `[Desa]` $\rightarrow$ `[Kelompok]`.
* **Contoh Kasus:**
  * Kabupaten Sidoarjo: `(kosong)`
  * Daerah Sidoarjo Tengah: `(kosong)`
  * Desa Gedangan 1: `10`
  * Kelompok Sawotratap Timur: `07`
  * **Prefix Terbentuk:** `1007`

### 1.2 Aturan Validasi Integritas Rantai (Chain Continuity)
* **Aturan Rantai Lengkap:** Jika sebuah induk memiliki kode, seluruh anak di bawahnya sampai kelompok tujuan **wajib memiliki kode**.
  * *Contoh Invalid:* Daerah terisi `01`, Desa kosong `(null)`, Kelompok terisi `05` $\rightarrow$ **Ditolak (Invalid: Rantai terputus di tingkat Desa)**.
* **Aturan Induk Kosong (Valid):** Induk boleh kosong selama kelompok tujuan dan induk terdekatnya memiliki kode yang tidak terputus (seperti contoh Kabupaten & Daerah kosong pada poin 1.1).
* **Aturan Tanpa Kode (Safety Net):** Jika seluruh tingkatan dari atas sampai bawah sama sekali tidak memiliki kode:
  * Status tetap **Valid**.
  * Anggota disimpan dengan nilai `member_code = NULL` (sistem tidak memaksa membuat angka sembarangan).

### 1.3 Penomoran 3+ Digit Anggota (Next Increment)
* Nomor urut anggota dihitung per kelompok dengan rumus: `MAX(nomor_terakhir) + 1`.
* **Format Digit:** Minimal 3 digit dengan padding nol di depan (`001` sampai `999`).
* **Fleksibilitas Kapasitas:** Jika anggota dalam satu kelompok mencapai 1.000 orang ke atas, digit bertambah dinamis secara natural (`1000`, `1001`, dst).
* **Sifat Abadi (Immutability):** Jika di kemudian hari kode grup diubah, nomor ID anggota yang sudah diterbitkan **tidak berubah**. Hanya anggota baru yang akan menggunakan prefix baru.

### 1.4 Pewarisan & Penguncian Template KTA (Strict Lock)
* Kolom `card_template_id` ditambahkan pada data grup.
* Jika tingkat atas (misal Daerah) telah menentukan template KTA:
  * Seluruh grup di bawahnya (Desa & Kelompok) **otomatis mewarisi** template tersebut.
  * Form pemilihan template pada grup anak **terkunci otomatis (Disabled/Read-only)**.
  * Tampil helper text native Filament yang ramah: *"Template dikunci oleh induk: [Nama Induk] ([Nama Template])."*
* **Hak Override Khusus:** Penggantian template secara manual hanya diperbolehkan saat mencetak KTA perorangan pada halaman informasi anggota (`ViewMember`).

---

## 2. Checklist Pengerjaan Berdasarkan Fase

### Fase 1: Skema Database & Relasi Model
- [x] Buat file migrasi untuk menambahkan kolom `code` (string nullable, indexed) pada tabel `groups`.
- [x] Buat file migrasi untuk menambahkan relasi `card_template_id` (foreign key nullable ke `card_templates`) pada tabel `groups`.
- [x] Update properti `$fillable` dan relasi pada model `App\Models\Group`:
  - [x] Relasi `belongsTo` ke `CardTemplate`.
- [x] Tambahkan index pada kolom `member_code` di tabel `members` (jika belum ada) untuk menjamin kecepatan pencarian dan sorting.

### Fase 2: Service & Logika Kalkulasi Hierarki
- [x] Buat service class `App\Services\Groups\GroupHierarchyService`:
  - [x] Method `getCalculatedPrefix(Group $group): ?string` untuk menghitung gabungan kode hierarki dari atas ke bawah.
  - [x] Method `validateChainIntegrity(Group $group): array` untuk memeriksa apakah terdapat rantai kode yang bolong/terputus.
  - [x] Method `getEffectiveCardTemplate(Group $group): ?CardTemplate` untuk mengambil template aktif (milik sendiri atau warisan induk terdekat).
  - [x] Method `generateNextMemberCode(Group $group): ?string` dengan database lock (`lockForUpdate`) untuk mencegah duplikasi nomor saat input bersamaan.
- [x] Tulis unit/feature test sederhana untuk memastikan kalkulasi prefix, validasi rantai, dan penomoran berjalan akurat.

### Fase 3: Tampilan Form Grup (100% Native Filament)
- [x] Perbarui `App\Filament\Resources\Groups\Schemas\GroupForm`:
  - [x] Tambahkan komponen `TextInput::make('code')` (label: "Kode Grup", maxLength: 10, placeholder: "Contoh: 10 atau 07").
  - [x] Tambahkan komponen native `Placeholder::make('preview_prefix')` yang menampilkan kalkulasi prefix gabungan secara live dan contoh format ID anggota.
  - [x] Tambahkan komponen `Select::make('card_template_id')` untuk memilih template KTA:
    - [x] Beri kondisi `disabled()` jika induk di atasnya sudah menentukan template.
    - [x] Beri `helperText()` native yang menjelaskan apakah template ini hasil warisan dari induk atau ditentukan mandiri.
- [x] Perbarui `App\Filament\Resources\Groups\Tables\GroupsTable`:
  - [x] Tampilkan kolom `code` (Kode Grup) dan `effective_template` pada daftar tabel grup.

### Fase 4: Form Input Anggota Mandiri
- [x] Perbarui `App\Filament\Resources\Members\Schemas\MemberForm`:
  - [x] Pada field `member_code`, pasang `placeholder()` dinamis: jika kelompok dipilih, tampilkan perkiraan ID berikutnya (misal: *"Otomatis: 1007001"*).
  - [x] Jika field `member_code` dikosongkan oleh pengguna saat simpan, jalankan `GroupHierarchyService::generateNextMemberCode()` secara otomatis.
- [x] Perbarui aksi cetak KTA satuan di `App\Filament\Resources\Members\Pages\ViewMember`:
  - [x] Set nilai *default* pilihan template pada dialog cetak sesuai dengan template warisan kelompok anggota. Operator tetap bebas mengganti jika ada kebutuhan khusus.

### Fase 5: Integrasi pada Halaman Bulk Import Anggota
- [x] Perbarui proses validasi baris impor di `App\Services\Members\MemberImportParser` / `MemberImportValidator`:
  - [x] Deteksi kondisi baris data:
    - **ID Otomatis:** Kolom ID di Excel kosong dan kelompok memiliki kode valid.
    - **Update Data:** Kolom ID terisi dan nomor ID sudah ada di database.
    - **ID Manual Baru:** Kolom ID terisi dan nomor ID belum ada di database.
    - **Tanpa ID:** Kolom ID kosong dan kelompok sama sekali tidak memiliki kode.
    - **Error Rantai Putus:** Kelompok memiliki rantai kode yang bolong/tidak valid.
- [x] Perbarui tampilan tabel preview di `App\Filament\Resources\Members\Pages\ImportMembers`:
  - [x] Tambahkan indikator status native (menggunakan Filament `BadgeColumn` / status label):
    - 🔵 **ID Otomatis** (akan diberi nomor berikutnya).
    - 🟡 **Update Data** (data lama akan diperbarui).
    - 🟢 **ID Manual** (nomor manual unik diterima).
    - ⚪ **Tanpa ID** (disimpan tanpa ID karena kelompok belum memiliki kode).
    - 🔴 **Rantai Kode Putus** (kelompok perlu diperbaiki kodenya).
- [x] Tambahkan Section **"Opsi Tambahan Impor"** menggunakan komponen native `Checkbox` / `Toggle` pada form impor:
  - [x] `[✓] Otomatis Buat KTA Baru` (Memakai template resmi kelompok/induk).
  - [x] `[✓] Tandai sebagai Anggota Permanen` (Otomatis mengisi `is_permanent = true`).
  - [x] `[✓] Perbarui Data Lama` (Jika ID sudah ada di database, lakukan update data).
- [x] Optimasi Alur Eksekusi Pasca-Impor (Anti-Timeout):
  - [x] Simpan seluruh data anggota ke database terlebih dahulu secara instan.
  - [x] Jika opsi `Buat KTA Baru` dicentang:
    - Jika data $\le$ 30 orang: generate KTA langsung secara sinkron.
    - Jika data $>$ 30 orang: tampilkan notifikasi sukses beserta tombol aksi langsung untuk memproses kartu dalam potongan batch aman.
  - [x] Sediakan tombol aksi pasca-impor: **"Unduh Paket KTA/QR (ZIP)"** untuk memudahkan operator mengambil file hasil impor.

### Fase 6: Pengujian, Keamanan, & Verifikasi Performa
- [x] Uji skenario konkurensi (membuat 2 anggota bersamaan di kelompok yang sama untuk memastikan nomor urut tidak ganda).
- [x] Uji impor file Excel dengan variasi data:
  - [x] Baris tanpa ID di kelompok berkode lengkap.
  - [x] Baris tanpa ID di kelompok tanpa kode sama sekali.
  - [x] Baris dengan ID yang sudah ada (menguji opsi update).
  - [x] Baris di kelompok yang rantai kodenya bolong.
- [x] Verifikasi waktu respon halaman form dan tabel agar tetap di bawah 200ms.
- [x] Pastikan tidak ada pesan error kaku atau istilah teknis yang membingungkan operator.

---

## 3. Catatan Standar Kode & Antarmuka
* **Tanpa Blade Custom di Filament:** Gunakan sepenuhnya skema `Filament\Forms\Components\*` dan `Filament\Tables\Columns\*`. Hindari penggunaan `view('custom.blade')` di dalam Filament Resource/Page agar tampilan tetap selaras dengan tema sistem.
* **Bahasa Humanis:** Gunakan label bahasa Indonesia yang ramah dan jelas (contoh: gunakan istilah *"Kode Induk Belum Lengkap"* alih-alih *"Chain Validation Exception"*).
