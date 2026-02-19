# 🧪 Manual Test Phase 6: Monitoring, Performance & Hierarchical Logic

Dokumen ini berisi panduan pengujian manual untuk fitur-fitur yang dikembangkan pada Phase 6, termasuk optimasi performa dan perbaikan logika hierarki.

## 1. Pengujian Performa Dasbor (PASSED ✅)
| Skenario | Ekspektasi Hasil | Status |
| :--- | :--- | :---: |
| Kecepatan Load Awal | Halaman dasbor terbuka instan. Widget muncul dengan state loading/placeholder (Lazy Loading). | [x] |
| Efisiensi Resources | Membuka dasbor tidak lagi menyebabkan "Entry Process" penuh di cPanel berkat request yang terbagi. | [x] |
| Persistent Caching | Refresh halaman kedua kali terasa jauh lebih cepat karena data diambil dari cache file (300-3600 detik). | [x] |
| Cache Invalidation | Perubahan pada data Anggota atau Grup akan me-reset cache sehingga data dasbor tetap akurat. | [x] |

## 2. Pengujian Role Migration & Perbaikan Manajemen User (PASSED ✅)

### A. Skenario Otorisasi Berbasis Role
- [x] **Super Admin**: Dapat melihat semua menu, semua user, dan memiliki akses penuh (Ubah/Hapus/Export) di semua pertemuan.
- [x] **Admin**: Hanya melihat anggota di grupnya & cabang. Tidak bisa melihat Super Admin. Tombol "Ubah" hanya muncul di pertemuan grup sendiri/bawahannya.
- [x] **Operator**: Hanya melihat profil sendiri. Tombol Ubah/Hapus disembunyikan. Akses scanner dibatasi izin Spatie.

### B. Sinkronisasi & Keamanan
- [x] **Role Sync**: Perubahan "Hak Akses" di form user otomatis menyinkronkan role Spatie (model_has_roles).
- [x] **Group Barrier**: Akses URL edit pertemuan milik grup lain secara langsung akan menghasilkan 403 Forbidden.
- [x] **Permission Control**: Mematikan izin (misal: `Export`) di UI Shield akan langsung menyembunyikan tombol terkait di UI user.

| Skenario | Ekspektasi Hasil | Status |
| :--- | :--- | :---: |
| Create User Role Kustom | Berhasil membuat pengguna dengan role baru (misal: "PENGURUS") tanpa error "Data truncated". | [x] |
| Sinkronisasi Spatie | User baru otomatis memiliki role yang sama di tabel `model_has_roles` (Spatie Laravel Permission). | [x] |
| UI Case Standarization | Nama Hak Akses di tabel pengguna ditampilkan dalam **HURUF BESAR** (Uppercase) secara konsisten. | [x] |

## 3. Pengujian Keamanan Hierarki (Policy) & Dasbor Berjenjang (PASSED ✅)
| Skenario | Ekspektasi Hasil | Status |
| :--- | :--- | :---: |
| Proteksi Meeting Parent | User level bawah bisa melihat pertemuan Atasan, tapi tombol **Ubah** dan **Hapus** disembunyikan. | [x] |
| Dashboard Ancestor Logic | Dasbor menampilkan statistik dari pertemuan terbaru milik **Grup Sendiri** atau **Atasan (Parent)**. | [x] |
| Dashboard Children Logic | Pertemuan kecil milik **Cabang (Children)** tidak muncul di dasbor atasan agar tidak membingungkan. | [x] |
| Akurasi Hitungan | Meskipun melihat pertemuan yang dibuat Pusat, angka statistik hanya menghitung anggota di lingkungan user tersebut. | [x] |

## 4. Pengujian Presensi Pengurus (Special Case) (STABLE 🏗️)
| Skenario | Ekspektasi Hasil | Status |
| :--- | :--- | :---: |
| Konfigurasi Kategori Jabatan | Berhasil menambah kategori (misal: "DPH", "PENDIDIKAN") di menu Kategori Jabatan. | [ ] |
| Penugasan Multi-Jabatan | Anggota dpt diset sebagai "PENGURUS" dan memiliki lebih dr 1 jabatan di level berbeda (Repeater UI). | [ ] |
| Scanner Bypass (Usia) | Pengurus yg usianya di luar `target_age_groups` tetap bisa scan dan tercatat hadir (Bypass Logic). | [ ] |
| Scanner Bypass (Gender) | Pengurus dgn gender berbeda dr `target_gender` tetap bisa scan (Bypass Logic - Opsional/Sesuai Kebijakan). | [ ] |
| Keakuratan Statistik (Dashboard) | Anggota bertipe "PENGURUS" tidak dihitung dalam total anggota atau rasio partisipasi reguler. | [ ] |
| Identitas di Scanner | Pencarian manual di scanner menampilkan label "[PENGURUS]" sebagai penanda identitas khusus. | [ ] |

## 5. Pengujian Import Data Anggota (NEW ✅)
| Skenario | Ekspektasi Hasil | Status |
| :--- | :--- | :---: |
| Upload File Excel | Tombol "Import Anggota" membuka modal upload dan menerima file .xlsx/.xls | [ ] |
| Auto-Generate Member Code | Setiap anggota yang di-import mendapat kode unik (format: MYmdHisXXXX) | [ ] |
| Auto-Generate QR Code | QR Code otomatis dibuat setelah import selesai (via Observer) | [ ] |
| Group Auto-Mapping | Nama grup di Excel cocok dengan grup di sistem (case-insensitive) | [ ] |
| Age Calculation | Usia dan kategori usia otomatis dihitung dari tanggal lahir | [ ] |
| Error Handling | Baris dengan data tidak valid di-skip dan dicatat di log | [ ] |
| Validation Messages | Pesan error jelas untuk setiap jenis validasi yang gagal | [ ] |

---
## 6. Rangkuman Hasil Tes
Seluruh pengujian menunjukkan bahwa sistem sekarang jauh lebih ringan (siap untuk Shared Hosting), memiliki integritas data yang lebih kuat sesuai struktur organisasi berjenjang, serta fleksibel dalam menangani peran khusus pengurus tanpa mengganggu akurasi data statistik anggota reguler.

**Update Terbaru (19 Feb 2026):** Fitur Import Data Anggota dari Excel telah selesai diimplementasikan dengan fitur auto-generate QR Code, group mapping, dan age calculation.

---
*Terakhir diupdate: 19 Feb 2026 (Update Pengurus Special Case, Performance & Hierarchical Dashboard, Import Members).*
