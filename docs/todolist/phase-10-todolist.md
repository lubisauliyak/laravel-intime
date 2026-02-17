# 📝 Todolist Phase 10: On-the-Spot Registration (Registrasi di Tempat)

Dokumen ini merinci penambahan fitur pendaftaran anggota baru secara langsung melalui antarmuka scanner agar anggota yang belum terdaftar tetap bisa diabsen dan masuk sistem.

## 1. Pengembangan Antarmuka Scanner (Live Scanner) 📱
*   [ ] **Tombol "Anggota Baru":** Tambahkan tombol aksi di halaman Live Scanner untuk membuka modal pendaftaran.
*   [ ] **Form Registrasi Cepat:** Buat form sederhana (Nama Lengkap, Jenis Kelamin, Kelompok) di dalam modal tersebut.
*   [ ] **Auto-Generation:** Pastikan sistem tetap menjalankan logic pencatatan usia dan QR Code secara otomatis setelah simpan.

## 2. Logika Absensi Otomatis ⚡
*   [ ] **Dual Action:** Gabungkan proses `Member::create()` dan `Attendance::create()` dalam satu transaksi (DB Transaction).
*   [ ] **Status Default:** Set status 'hadir' dan metode 'manual' untuk anggota yang baru didaftarkan.

## 3. Notifikasi & Feedback 🔔
*   [ ] **Instant Feedback:** Tampilkan notifikasi sukses yang informatif (Contoh: "Anggota baru berhasil didaftarkan dan diabsen").
*   [ ] **Real-time Update:** Pastikan daftar riwayat absensi di bawah scanner langsung terupdate tanpa reload.

## 4. Keamanan & Validasi 🛡️
*   [ ] **Permission Check:** Pastikan hanya role Admin atau Operator yang memiliki izin akses ke fitur ini.
*   [ ] **Unique Validation:** Cek potensi duplikasi nama untuk menghindari input ganda pada saat ramai.

## 5. Definition of Done (DoD) - Phase 10
1.  Operator dapat mendaftarkan tamu/anggota baru langsung dari halaman scanner.
2.  Data anggota baru otomatis tersimpan di database utama (Tabel `members`).
3.  Anggota tersebut langsung tercatat status 'hadir' pada pertemuan aktif tersebut.
4.  Data anggota baru muncul dalam laporan ekspor (Excel/PDF) pertemuan tersebut.

---
*Status: Direncanakan (17 Feb 2026)*
