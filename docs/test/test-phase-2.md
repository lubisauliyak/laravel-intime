# 🧪 Manual Testing Phase 2: QR & Authorization

Dokumen ini berisi panduan pengujian manual untuk memastikan fitur-fitur pada Phase 2 berjalan sesuai spesifikasi.

## 1. Pengujian QR Code (Generation & Deletion)

| Langkah | Hasil yang Diharapkan | Status |
| :--- | :--- | :---: |
| Buat Anggota baru dengan ID Anggota `TEST-001`. | Anggota berhasil disimpan. | [x] |
| Periksa direktori `public/storage/qrcodes/`. | File `TEST-001.svg` ditemukan. | [x] |
| Lihat tabel Anggota di Filament. | Kolom "QR" menampilkan gambar QR Code yang valid. | [x] |
| Buka halaman Detail Anggota (View). | Muncul QR Code ukuran besar yang bisa di-scan. | [x] |
| Ubah ID Anggota dari `TEST-001` menjadi `TEST-XYZ`. | File lama dihapus, file `TEST-XYZ.svg` baru dibuat. | [x] |
| Hapus data Anggota `TEST-XYZ`. | File `TEST-XYZ.svg` di storage ikut terhapus otomatis. | [x] |

## 2. Pengujian Role-Based Access Control (RBAC)

| Skenario | Langkah | Hasil yang Diharapkan | Status |
| :--- | :--- | :---: |
| **Super Admin Access** | Login sebagai Super Admin. | Dapat melihat Menu "Shield" (Roles) dan semua Data Master. | [x] |
| **Admin Access** | Login sebagai Admin Wilayah. | Tidak dapat mengakses menu "Shield". Tombol "Ubah/Hapus" hanya muncul di data miliknya/bawahannya. | [x] |
| **Group Managed Logic** | Login sebagai Admin. | Aksi (Edit/Delete) hanya terlihat pada record yang `canBeManagedBy(auth()->user())`. | [x] |

## 3. Pengujian Data Scoping (Hierarchical Visibility)

*Skenario: Struktur Grup Berlapis (Pusat -> Wilayah -> Cabang).*

| Langkah | Hasil yang Diharapkan | Status |
| :--- | :--- | :---: |
| Login sebagai "Admin Wilayah". | Dapat melihat Grup Wilayah (dirinya) dan Grup Cabang (bawahannya). | [x] |
| Cek visibilitas Grup Pusat (Induk). | Grup Pusat terlihat di tabel untuk referensi (Read-only). | [x] |
| Cek tombol Aksi pada Grup Pusat. | Tombol "Ubah" dan "Hapus" **TIDAK** muncul pada record Induk. | [x] |
| Cek menu Aksi Massal di Tabel Grup. | Opsi "Pulihkan" & "Hapus Permanen" hanya ada di Aksi Massal, bukan baris. | [x] |

## 4. Validasi Format & Keamanan

| Langkah | Hasil yang Diharapkan | Status |
| :--- | :--- | :---: |
| Buka file SVG di browser. | Gambar terlihat tajam (vector-based) dan tidak pecah saat zoom. | [x] |
| Scan QR Code menggunakan HP. | Data yang terbaca sesuai dengan ID Anggota. | [x] |
| Coba akses URL Edit Grup Induk secara paksa via ID. | Muncul error 404/403 (Security check by Policy/Visibility). | [x] |

---
*Catatan:*
Jika ada test case yang gagal (FAIL), harap catat ID Anggota atau User yang digunakan untuk debugging.
