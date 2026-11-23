# Rencana Kerja & Checklist: Penataan Status Anggota, Alur Approval, & Target Kehadiran Dinamis
## Modul Manajemen Anggota, Presensi Mandiri, Live Scanner, & Kalkulasi Pertemuan

Dokumen ini disusun sebagai panduan teknis dan daftar periksa (*checklist*) standar **Product Manager (PM)** dan **UX Professional** untuk mengimplementasikan penyelarasan semantik pada status keanggotaan:
1. **`is_permanent`**: Status Persetujuan Admin (*Approval Gate / Verifikasi Keanggotaan*).
2. **`status`**: Status Target Kehadiran (*Target Wajib vs Non-Target / Floating*).
3. **`deleted_at`**: Pengarsipan Data (*Soft Delete*).

---

## 1. Matriks Konseptual & Definisi Bisnis

| Atribut / Field | Tipe | Nilai | Makna Bisnis & Perilaku Sistem | Pengaruh ke Pertemuan / Absensi |
| :--- | :--- | :--- | :--- | :--- |
| **`is_permanent`**<br>*(Approval Gate)* | Boolean | `false` | **Belum Disetujui / Pending**: Anggota mendaftar mandiri via web/scan kilat dan menunggu verifikasi admin. | Muncul indikator "Belum Verifikasi" di data anggota dan notifikasi badge admin. |
| | | `true` | **Disetujui / Permanen**: Anggota resmi yang telah diverifikasi dan disetujui oleh pengurus/admin. | Status resmi organisasi aktif. |
| **`status`**<br>*(Target Kehadiran)* | Boolean | `true` | **Target Wajib**: Anggota adalah target kehadiran resmi pada kelompoknya. | • Dihitung ke dalam kuota target awal pertemuan.<br>• Jika tidak hadir $\rightarrow$ **dihitung Alpa (menurunkan % kehadiran)**. |
| | | `false` | **Non-Target / Mengambang**: Anggota tamu, simpatisan, atau anggota percobaan. | • Tidak dibebani target awal pertemuan.<br>• Jika hadir/izin/sakit $\rightarrow$ **menambah target dinamis pertemuan (+1 target, +1 kehadiran)**.<br>• Jika tidak hadir $\rightarrow$ **TIDAK dihitung Alpa (tidak mengurangi % kehadiran)**. |
| **`deleted_at`**<br>*(Soft Delete)* | Timestamp | `not null` | **Dihapus / Diarsipkan**: Anggota dinonaktifkan permanen/dikeluarkan dari sistem. | Tidak bisa absen, tidak bisa login, tidak muncul di dropdown/pencarian aktif. |

---

## 2. Alur Pengalaman Pengguna (User Journey)

### 2.1 Alur Registrasi Mandiri & Scanner Baru
```
[Jemaat Baru Registrasi Mandiri / Scan Kilat di Gate]
                     │
                     ▼
           [Data Anggota Disimpan]
     is_permanent = false (Belum Approve)
     status       = false (Non-Target)
                     │
                     ▼
   [Bisa Langsung Hadir / Izin pada Pertemuan Tersebut]
                     │
                     ▼
 [Kehadiran Tercatat & Menambah Target Dinamis (+1) pada Pertemuan Hari Itu]
```

### 2.2 Alur Persetujuan Admin (Approval Flow)
```
[Admin Membuka Menu Anggota (Filament)]
                     │
                     ├─ Ada Badge Angka "Menunggu Persetujuan" (is_permanent = false)
                     │
                     ▼
[Admin Review Data & Klik "Setujui Anggota" (Single / Bulk Action)]
                     │
                     ├─ is_permanent diubah menjadi true
                     └─ Opsi: Aktifkan sekaligus sebagai "Target Wajib (status = true)"
```

### 2.3 Alur Mesin Kalkulasi Kehadiran Pertemuan
```
Total Target Dasar Pertemuan = COUNT(Member WHERE status = true AND [Filter Gender & Usia])
                                  │
                                  ▼
Kehadiran Non-Target         = COUNT(Attendance HADIR/IZIN/SAKIT WHERE member.status = false)
                                  │
                                  ▼
TOTAL TARGET DINAMIS         = Target Dasar + Kehadiran Non-Target
TOTAL REALISASI HADIR        = Hadir Target + Hadir Non-Target
TOTAL IZIN & SAKIT           = Izin/Sakit Target + Izin/Sakit Non-Target
ALPA (TIDAK HADIR)           = Target Dasar - (Hadir Target + Izin/Sakit Target)
                               (Non-Target TIDAK PERNAH dihitung Alpa)
```

---

## 3. Rencana Kerja & Checklist Implementasi

### FASE 1: Form, Tabel, & Manajemen Anggota (Filament)
Fokus: *Kejelasan label, helper text informatif, visual feedback, dan aksi persetujuan admin.*

- [x] **1.1 Form Anggota (`MemberForm.php`)**
  - [x] Path: `app/Filament/Resources/Members/Schemas/MemberForm.php`
  - [x] Ubah label toggle `is_permanent` menjadi **"Status Persetujuan (Permanen)"** atau **"Terverifikasi Admin"**.
  - [x] Perbarui `helperText` pada `is_permanent`: *"Aktifkan jika keanggotaan telah diverifikasi dan disetujui admin. Pendaftaran mandiri bernilai non-aktif sampai disetujui."*
  - [x] Ubah label toggle `status` menjadi **"Target Kehadiran (Status Aktif)"**.
  - [x] Perbarui `helperText` pada `status`: *"Jika aktif, anggota menjadi target kehadiran wajib dan dihitung alpa bila tidak hadir. Jika nonaktif, anggota tetap dapat hadir/izin namun tidak dibebani target dan tidak dihitung alpa."*
  - [x] Atur icon dan on/off color yang harmonis (`is_permanent`: primary/warning, `status`: success/gray).

- [x] **1.2 Tabel Anggota (`MembersTable.php`)**
  - [x] Path: `app/Filament/Resources/Members/Tables/MembersTable.php`
  - [x] Perjelas kolom status verifikasi (`is_permanent`) dengan badge status: `Disetujui` (hijau) vs `Menunggu Persetujuan` (oranye/kuning).
  - [x] Perjelas kolom target kehadiran (`status`) dengan badge: `Target Wajib` (biru) vs `Non-Target` (abu-abu).
  - [x] Sesuaikan filter `TernaryFilter` untuk `is_permanent` (label: "Persetujuan Admin") dan `status` (label: "Target Kehadiran").
  - [x] Perbarui tombol aksi `make_permanent` (Single Action):
    - Beri modal konfirmasi yang jelas: Menanyakan apakah ingin menyetujui anggota dan apakah ingin sekaligus menjadikannya target kehadiran (`status = true`).
    - Tooltip yang ramah bagi anggota yang sudah disetujui.
  - [x] Perbarui tombol aksi massal `make_permanent_bulk` (Bulk Action):
    - Modal dialog persetujuan massal dengan opsi update `status` bersamaan.
    - Notifikasi toast sukses yang informatif.

- [x] **1.3 Navigasi & Import Anggota**
  - [x] Path: `app/Filament/Resources/Members/MemberResource.php`
    - Pastikan badge pada menu samping menampilkan jumlah anggota yang menunggu persetujuan (`is_permanent = false`).
  - [x] Path: `app/Filament/Resources/Members/Pages/ImportMembers.php`
    - Sesuaikan default mapping dan checkbox form import: Anggota hasil impor file Excel/CSV default `is_permanent = true` dan `status = true` (dengan opsi toggle yang jelas pada form import).

---

### FASE 2: Endpoint Registrasi Mandiri & Live Scanner
Fokus: *Mencegah error penolakan pendaftaran mandiri dan memastikan anggota non-target tetap bisa absen tanpa hambatan.*

- [x] **2.1 Kehadiran Mandiri (`SelfPresenceController.php`)**
  - [x] Path: `app/Http/Controllers/SelfPresenceController.php`
  - [x] Pada fungsi registrasi anggota baru mandiri: Pastikan menyimpan `'is_permanent' => false` dan `'status' => false`.
  - [x] Pada fungsi pencarian daftar anggota (`getEligibleMembers` / query dropdown): Hapus pembatas hardcoded `->where('status', true)`, ganti dengan verifikasi bahwa anggota tidak di-soft delete (`whereNull('deleted_at')`), sehingga anggota non-target tetap dapat memilih namanya untuk hadir mandiri.

- [x] **2.2 Perizinan Mandiri (`SelfPermissionController.php`)**
  - [x] Path: `app/Http/Controllers/SelfPermissionController.php`
  - [x] Pada fungsi registrasi anggota baru izin: Simpan `'is_permanent' => false` dan `'status' => false`.
  - [x] Pada query validasi anggota: Izinkan anggota dengan `status = false` untuk mengajukan izin/sakit.

- [x] **2.3 Live Scanner Presensi (`LiveScannerController.php`)**
  - [x] Path: `app/Http/Controllers/LiveScannerController.php`
  - [x] Pada fungsi pendaftaran instan saat scan: Simpan `'is_permanent' => false` dan `'status' => false`.
  - [x] Pada fungsi pencarian kode QR anggota (`where('member_code', $code)`): Pastikan tidak memblokir scan hanya karena `status = false`. Cukup pastikan anggota aktif (bukan soft-deleted).

---

### FASE 3: Mesin Hitung Target & Presensi Pertemuan (Core Attendance Engine)
Fokus: *Akurasi matematis, tidak ada alpa fiktif untuk non-target, dan performa query teroptimasi.*

- [x] **3.1 Ringkasan Infolist Pertemuan (`MeetingAttendanceInfolist.php`)**
  - [x] Path: `app/Filament/Resources/Meetings/Schemas/MeetingAttendanceInfolist.php`
  - [x] Hitung **Target Dasar (Target Wajib)**: Anggota di kelompok/subkelompok dengan `status = true` yang lolos kriteria gender dan usia pertemuan.
  - [x] Hitung **Kehadiran Non-Target (Floating)**: Anggota dengan `status = false` yang memiliki record `attendances` (status `HADIR`, `IZIN`, `SAKIT`) pada pertemuan tersebut.
  - [x] Formulasikan **Total Target Pertemuan**: `Target Dasar + Kehadiran Non-Target`.
  - [x] Formulasikan **Alpa (Tidak Hadir)**: `Target Dasar - (Hadir Target + Izin/Sakit Target)`. Pastikan anggota `status = false` yang tidak hadir tidak pernah dihitung sebagai alpa.
  - [x] Sesuaikan rincian target per gender (Laki-laki / Perempuan) dan per kategori usia agar mengakomodasi tambahan non-target yang hadir.

- [x] **3.2 Tabel Rincian Kehadiran Pertemuan (`MeetingAttendanceDetails.php`)**
  - [x] Path: `app/Filament/Resources/Meetings/Pages/MeetingAttendanceDetails.php`
  - [x] Perbarui `getTableQuery()`:
    - Saat ini query membatasi `->where('members.status', true)`.
    - Ubah agar menyertakan anggota `status = true` **ATAU** anggota yang memiliki record presensi pada pertemuan tersebut (`has attendance in this meeting`).
  - [x] Kolom & Badge UX:
    - Tambahkan badge penanda pada baris nama:
      - Anggota Target Wajib: tanpa label tambahan / label netral.
      - Anggota Non-Target Hadir: Badge info/warning tipis *"Non-Target"* atau *"Tambahan"*.
      - Anggota Belum Disetujui: Badge *"Pending Approval"*.
  - [x] Sinkronisasi `getNavigationBadge()` di header halaman pertemuan.

- [x] **3.3 Tabel Statistik Kelompok (`MeetingStatsTable.php`)**
  - [x] Path: `app/Filament/Resources/Meetings/Widgets/MeetingStatsTable.php`
  - [x] Sinkronkan formula target dinamis pada tabel breakdown per kelompok agar penjumlahan kelompok sama persis dengan angka kartu ringkasan.

- [x] **3.4 Ekspor Data (Excel & Cetak PDF)**
  - [x] Path: `app/Exports/MeetingAttendanceExport.php`
  - [x] Path: Controller & Blade PDF Report Pertemuan.
  - [x] Pastikan query data ekspor menyertakan anggota non-target yang hadir dan statusnya ditampilkan jelas di berkas cetak.

---

### FASE 4: Dashboard Widgets & Statistik Global
Fokus: *Konsistensi angka statistik di seluruh aplikasi tanpa menimbulkan ambiguitas bagi pengurus.*

- [x] **4.1 Widget Populasi Target (`TargetPopulationWidget.php`)**
  - [x] Path: `app/Filament/Widgets/TargetPopulationWidget.php`
  - [x] Beri penjelasan atau label sub-judul: *"Berdasarkan anggota dengan status Target Wajib"*.
- [x] **4.2 Widget Ikhtisar Presensi & Grafik Partisipasi**
  - [x] Path: `app/Filament/Widgets/AttendanceOverview.php`
  - [x] Path: `app/Filament/Widgets/TopInsightWidget.php`
  - [x] Path: `app/Filament/Widgets/GenderActivityWidget.php`
  - [x] Path: `app/Filament/Widgets/AgeGroupParticipationWidget.php`
  - [x] Pastikan metrik tingkat kehadiran menghitung formula target dinamis pertemuan yang sudah disesuaikan.

---

### FASE 5: Validasi Pengujian & Pengawasan Performa
Fokus: *Stress test logika presensi dan benchmark query SQL.*

- [x] **5.1 Skenario Uji 1: Registrasi Mandiri Baru**
  - [x] Mendaftar via tautan mandiri pertemuan $\rightarrow$ Cek data di database: pastikan `is_permanent = false` dan `status = false`.
  - [x] Cek kartu statistik pertemuan $\rightarrow$ Target awal tidak bertambah sebelum yang bersangkutan absen.
  - [x] Saat yang bersangkutan absen hadir $\rightarrow$ Target pertemuan bertambah +1, kehadiran bertambah +1.
- [x] **5.2 Skenario Uji 2: Anggota Non-Target Tidak Hadir**
  - [x] Pastikan anggota `status = false` yang tidak hadir **tidak muncul sebagai Alpa** dan tidak menurunkan persentase kehadiran pertemuan.
- [x] **5.3 Skenario Uji 3: Persetujuan Admin (Approval)**
  - [x] Admin membuka menu Anggota $\rightarrow$ Terlihat badge counter approval.
  - [x] Admin menyetujui anggota $\rightarrow$ `is_permanent` menjadi `true`.
  - [x] Admin mengaktifkan toggle `status` $\rightarrow$ Anggota kini menjadi Target Wajib pertemuan berikutnya.
- [x] **5.4 Verifikasi Kinerja Query Database**
  - [x] Pastikan query join pada `MeetingAttendanceDetails` dan `MeetingAttendanceInfolist` menggunakan index yang tepat (`meeting_id`, `member_id`, `status`) tanpa query $N+1$.
