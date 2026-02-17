# 🧪 Manual Test Plan: Role & Permission Migration

Dokumen ini merinci langkah-langkah pengujian untuk memverifikasi transisi dari role hardcoded ke Spatie Permissions (Filament Shield).

## 1. Persiapan Data (Setup via UI)
*   [ ] Pastikan fitur **Shield** sudah terpasang (Menu **Akses Pengguna** -> **Peran**).
*   [ ] Buat/Pastikan ada 3 Role standar: `super_admin`, `admin`, dan `operator`.
*   [ ] Buat User Test:
    *   **User A (Admin)**: Ditempatkan di Grup Level 1 (misal: "Wilayah Utara").
    *   **User B (Admin)**: Ditempatkan di Grup Level 1 (misal: "Wilayah Selatan").
    *   **User C (Operator)**: Ditempatkan di bawah User A (Grup Level 2).

## 2. Skenario Otorisasi Berbasis Role

### A. Super Admin (Full Access)
*   [ ] Login sebagai Super Admin.
*   [ ] Buka menu **Pengguna**. Pastikan bisa melihat SEMUA user termasuk admin lain.
*   [ ] Buka menu **Pertemuan**. Pastikan tombol **Ubah**, **Hapus**, dan **Export** muncul di seluruh baris data.
*   [ ] Pastikan menu **Peran** (Shield) terlihat dan bisa diakses.

### B. Admin (Grup Barrier & Permissions)
*   [ ] Login sebagai **User A (Admin Wilayah Utara)**.
*   [ ] Buka menu **Anggota**. Pastikan hanya anggota dari "Wilayah Utara" dan cabangnya yang terlihat.
*   [ ] Coba buka menu **Pengguna**. Pastikan tidak bisa melihat user dengan role `super_admin`.
*   [ ] Buka menu **Pertemuan**.
    *   Cek baris pertemuan milik "Wilayah Utara": Tombol **Ubah** dan **Export** harus muncul.
    *   Cari pertemuan milik "Wilayah Selatan" (jika ada yang terleak): Tombol **Ubah** tidak boleh muncul jika policy bekerja benar.

### C. Operator (Restricted Access)
*   [ ] Login sebagai **User C (Operator)**.
*   [ ] Buka menu **Pengguna**. Pastikan hanya bisa melihat profil diri sendiri.
*   [ ] Buka menu **Pertemuan**. Pastikan tombol **Ubah** dan **Hapus** tersembunyi.
*   [ ] **Buka Live Scanner**:
    *   Jika permission `scan_attendance` dimatikan: Akses ke halaman scanner harus ditolak (403).
    *   Jika permission `set_excused_attendance` dimatikan: Tombol **IZIN** dan **SAKIT** pada Cari Manual harus hilang.

## 3. Skenario Dinamis (Permission Control)
*   [ ] Login sebagai Super Admin.
*   [ ] Edit Role `admin`: **Hilangkan (Uncheck)** permission `Export:Member`.
*   [ ] Login kembali sebagai **User A (Admin)**.
*   [ ] Buka menu **Anggota** atau **Rekap Kehadiran**.
*   [ ] **Ekspektasi**: Tombol **Export Excel** harus hilang dari UI secara otomatis.

## 4. Validasi Group Barrier di Policy (Security)
*   [ ] Login sebagai **User B (Admin Wilayah Selatan)**.
*   [ ] Coba akses URL Edit Pertemuan milik Wilayah Utara secara langsung via address bar (misal: `/admin/meetings/1/edit`).
*   [ ] **Ekspektasi**: Muncul halaman **403 Forbidden** (karena `MeetingPolicy` kini mengecek hierarki grup, bukan sekadar role).

## 5. Sinkronisasi Role (User Form)
*   [ ] Buka menu **Pengguna** -> **Ubah** salah satu user.
*   [ ] Ubah pilihan pada dropdown **Hak Akses (Peran)**.
*   [ ] Simpan data.
*   [ ] Buka menu **Akses Pengguna** -> **Peran** -> Klik salah satu Role.
*   [ ] Cek daftar user di bawah role tersebut.
*   [ ] **Ekspektasi**: User yang baru diubah otomatis berpindah ke daftar role yang sesuai di Spatie.

---
*Status: Siap digunakan untuk QA (17 Feb 2026)*
