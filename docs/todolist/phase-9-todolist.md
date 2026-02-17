# 📝 Todolist Phase 9: Fitur Perizinan Mandiri (Self-Permit System)

Dokumen ini merinci pembangunan sistem pengajuan izin mandiri oleh anggota melalui form publik dan proses verifikasi oleh Admin.

## 1. Arsitektur Data & Database 🗄️
*   [ ] **Migrasi Tabel `permits`:** Relasi ke `meeting_id`, `member_id`, `group_id`, `reason`, `evidence_path`, `status`.
*   [ ] **Model Permit:** Relationship ke `Meeting`, `Member`, dan `Group`.

## 2. Public Permit Form (Front-end) 🌐
*   [ ] **Route Publik:** `/p/{meeting_id}` (link untuk dibagikan).
*   [ ] **UI Form Premium:** Desain mobile-first, searchable select untuk nama anggota, upload lampiran.
*   [ ] **Feedback Page:** Halaman sukses setelah kirim pengajuan.

## 3. Integrasi Infolist Pertemuan 🔗
*   [ ] **Link Generator:** Field "Link Perizinan" di `MeetingInfolist.php` dengan tombol "Copy Link".
*   [ ] **Counter:** Tampilkan jumlah pengajuan yang menunggu verifikasi.

## 4. Manajemen Perizinan (Filament Resource) 🛠️
*   [ ] **PermitResource:** Table dan Form untuk verifikasi izin.
*   [ ] **Proses Approval:** Tombol "Setujui" (Otomatis buat record Attendance) dan tombol "Tolak".

## 5. Sinkronisasi Data 🔄
*   [ ] **Update Dashboard:** Widget "Perizinan Masuk Hari Ini".
*   [ ] **Excel Export:** Integrasi data izin ke laporan Excel.

## 6. Definition of Done (DoD) - Phase 9
1.  Anggota dapat mengisi form izin secara mandiri tanpa harus login.
2.  Admin dapat menyetujui/menolak pengajuan izin dengan sekali klik.
3.  Izin yang disetujui otomatis tercatat dalam sistem absensi pertemuan terkait.

---
*Status: Direncanakan (16 Feb 2026)*
